---
user-invocable: true
name: bear-review
description: Evaluate PHP code quality for BEAR.Sunday projects. Assess using PHPMD metrics (CC, NPath, parameter count, field count) and BEAR.Sunday-specific criteria (resource design, DI, type safety). Use when user says "code review", "コードレビュー", "quality check", "品質チェック", "PHPMD", "ignore baseline", "without baseline", "no baseline", "baselineなし", or asks to review code quality.
---

# BEAR.Sunday Code Review Skill

## Invocation Modes

This skill runs in one of two modes. Pick the mode that matches the user's request:

| Mode | Trigger | Behavior |
|------|---------|----------|
| **With baseline** (default) | "code review", "コードレビュー", "PHPMD", or no mode hint | Use `phpmd.baseline.xml` if present. Reports the currently-actionable violations after suppression. |
| **Without baseline** | "ignore baseline", "no baseline", "without baseline", "baselineなし", "真の状態", "--ignore-baseline", "--no-baseline" | Temporarily disable `phpmd.baseline.xml` to reveal hidden technical debt. See the rename/restore procedure in §1.1. |

Always state the active mode at the top of the report (see Output Format) so the reader knows whether suppressed warnings are included.

When in "without baseline" mode and `phpmd.baseline.xml` exists, run §1.1's full statistics workflow rather than the single-file command in §1 — the value of disabling the baseline comes from aggregated counts.

## Evaluation Procedure

### 1. Quantitative Evaluation with PHPMD

PHPMD lives at `./vendor/bin/phpmd` in standard projects, or
`./vendor-bin/tools/vendor/bin/phpmd` in monorepo (bamarni/composer-bin)
setups. Resolve it once:

```bash
if [ -x ./vendor-bin/tools/vendor/bin/phpmd ]; then
  PHPMD=./vendor-bin/tools/vendor/bin/phpmd
else
  PHPMD=./vendor/bin/phpmd
fi
```

Retrieve metrics with the following command:

```bash
$PHPMD [file-path] text codesize,design 2>/dev/null | grep -v "^Deprecated"
```

This honors `phpmd.baseline.xml` if present. For "without baseline" mode, follow §1.1.

### 1.1 Automatic Statistics Report Generation (Recommended)

To understand the overall quality status of the project, it is strongly recommended to automatically generate a PHPMD violation statistics report.

#### Running without baseline

If `phpmd.baseline.xml` exists, temporarily disable it to understand the true quality state:

```bash
# 1. Temporarily rename baseline
mv phpmd.baseline.xml phpmd.baseline.xml.bak

# 2. Run PHPMD against all resource directories
$PHPMD src/Resource text phpmd.xml 2>&1 > phpmd_output.txt

# 3. Restore
mv phpmd.baseline.xml.bak phpmd.baseline.xml
```

#### Statistics Aggregation Commands

Automatically generate statistics from PHPMD output:

```bash
# Total violations
cat phpmd_output.txt | wc -l

# Count by category
echo "=== Category Statistics ==="
echo "LongVariable:           $(grep -c 'LongVariable' phpmd_output.txt) violations"
echo "CouplingBetweenObjects: $(grep -c 'CouplingBetweenObjects' phpmd_output.txt) violations"
echo "StaticAccess:           $(grep -c 'StaticAccess' phpmd_output.txt) violations"
echo "ElseExpression:         $(grep -c 'ElseExpression' phpmd_output.txt) violations"
echo "UnusedFormalParameter:  $(grep -c 'UnusedFormalParameter' phpmd_output.txt) violations"

# Complexity violations (high priority)
echo ""
echo "=== Complexity Violations (High Priority) ==="
echo "CyclomaticComplexity:   $(grep -c 'CyclomaticComplexity' phpmd_output.txt) violations"
echo "NPathComplexity:        $(grep -c 'NPathComplexity' phpmd_output.txt) violations"
echo "ExcessiveMethodLength:  $(grep -c 'ExcessiveMethodLength' phpmd_output.txt) violations"
echo "ExcessiveClassLength:   $(grep -c 'ExcessiveClassLength' phpmd_output.txt) violations"
echo "TooManyFields:          $(grep -c 'TooManyFields' phpmd_output.txt) violations"
```

#### Statistics Report Example

Example of execution results:

```text
=== PHPMD Statistics Report ===
Total violations: 259

[By Category]
- LongVariable:            92 (35.5%)
- CouplingBetweenObjects:  28 (10.8%)
- StaticAccess:            24 (9.3%)
- ElseExpression:          20 (7.7%)
- UnusedFormalParameter:   18 (7.0%)
...

[Complexity Violations (High Priority)]
- CyclomaticComplexity:     5
- NPathComplexity:          5
- ExcessiveMethodLength:    7
- ExcessiveClassLength:     1
- TooManyFields:            0
```

#### Identifying the Most Problematic Files

```bash
# Top 10 files by violation count
cat phpmd_output.txt | awk -F: '{print $1}' | sort | uniq -c | sort -rn | head -10

# Example:
#   5 src/Resource/Page/Content/SpecialContent.php
#   4 src/Resource/App/GlobalNav.php
#   3 src/Resource/App/Contents/Ranking.php
```

#### Comparison With and Without Baseline

Visualize the number of issues hidden by the baseline:

```bash
# Violation count without baseline
baseline_off=$(cat phpmd_output.txt | wc -l)

# Violation count with baseline (normal execution)
baseline_on=$($PHPMD src/Resource text phpmd.xml 2>&1 | wc -l)

echo "=== Baseline Comparison ==="
echo "With baseline:    ${baseline_on} violations"
echo "Without baseline: ${baseline_off} violations"
echo "Suppressed:       $((baseline_off - baseline_on)) violations"
```

#### Classification by Severity

Determine severity based on complexity values:

```bash
# Critical: CC>20 or NPath>10000
critical_cc=$(grep 'CyclomaticComplexity' phpmd_output.txt | sed -n 's/.*Complexity of \([0-9][0-9]*\).*/\1/p' | awk '{if($1>20)print}' | wc -l)
critical_npath=$(grep 'NPathComplexity' phpmd_output.txt | sed -n 's/.*complexity of \([0-9][0-9]*\).*/\1/p' | awk '{if($1>10000)print}' | wc -l)

echo "=== By Severity ==="
echo "Critical (CC>20 or NPath>10000): $((critical_cc + critical_npath)) violations"
```

#### How to Use the Statistics

1. **Visualize technical debt**: Understand the total number of issues hidden by the baseline
2. **Prioritize**: Address complexity violations first
3. **Improvement plan**: Create a phased improvement plan based on per-category counts
4. **Trend analysis**: Run periodically to monitor improvement progress

### 2. Evaluation Criteria

#### Cyclomatic Complexity (CC)

| Grade | CC Value | Status | Action |
|-------|----------|--------|--------|
| **A** | 1-10 | Very good. Simple and easy to test | State to maintain |
| **B** | 11-20 | Acceptable. Slightly complex but maintainable | Acceptable for complex logic |
| **C** | 21-30 | Warning. Hard to test and prone to bugs | Refactoring recommended |
| **D** | 31+ | Failing. Unmaintainable | Immediate action required |

#### NPath Complexity

| Grade | NPath | Status |
|-------|-------|--------|
| **A** | 1-200 | Good |
| **B** | 201-500 | Acceptable |
| **C** | 501-1000 | Warning |
| **D** | 1001+ | Failing |

#### ExcessiveParameterList

| Grade | Parameter Count | Status |
|-------|----------------|--------|
| **A** | 1-10 | Good |
| **B** | 11-15 | Acceptable (consider DTO) |
| **C** | 16-20 | Warning (recommend wrapping in object) |
| **D** | 21+ | Failing |

#### TooManyFields

| Grade | Field Count | Status |
|-------|------------|--------|
| **A** | 1-15 | Good |
| **B** | 16-22 | Acceptable (consider splitting) |
| **C** | 23-30 | Warning |
| **D** | 31+ | Failing |

### 3. BEAR.Sunday-Specific Evaluation

#### Resource Design (Resource classes only)

| Grade | Criteria |
|-------|----------|
| **A** | Proper use of `#[Embed]`, single responsibility, appropriate HTTP methods |
| **B** | Follows basic resource patterns |
| **C** | Bloated logic, unclear responsibilities |
| **D** | Non-resource code (controller-like implementation) |

#### Code Quality Checklist

Evaluate each item below. See `references/code-quality-checklist.md` for detailed examples and patterns.

- **Body assignment**: Assign all at once (`$this->body = [...]`) rather than sequentially
- **Private method arguments**: Repeated argument passing indicates excessive responsibility; delegate to services
- **Loops inside resources**: Complex loops should be delegated to the domain layer
- **Domain vs Service distinction**: Business logic in Domain, external integration in Service, data access in Query
- **Embed usage**: Use `#[Embed]` instead of procedural `$this->resource->get()` where possible
- **Return type**: Resource methods should return `static` (not `ResourceObject` or `self`)
- **Dependency injection**: Constructor injection preferred; setter injection via traits is deprecated
- **`new` usage**: OK for domain/value objects and DTOs; services and repositories must use DI
- **Exception design**: Use specific domain exceptions, not generic `Exception` or `Throwable`
- **try-catch**: Avoid large try-catch blocks; let the framework handle exceptions
- **Type safety**: Full type declarations preferred; minimize `mixed` usage
- **PHP 8 attributes**: Use `#[Embed]`, `#[Inject]`, `#[Named]` instead of Doctrine annotations
- **Constants**: Environment-dependent values must be injected; app structure definitions OK as constants
- **DB access**: No direct DB operations in resources; delegate to Query layer
- **Debug code**: No `error_log()`, `var_dump()`, `print_r()`; use LoggerInterface
- **File size**: Under 200 lines good; over 400 lines excessive
- **Method arguments**: Use explicit scalar arguments or `#[Input]` + DTO, not `array<string, mixed>`
- **Trivial getters**: Avoid methods that only return a stored field (`return $this->x;`). Prefer public readonly properties for value/context/BDR objects; keep methods only for behaviour, framework contracts, validation, lazy creation, transformation, I/O, or throws.
- **Web context**: No superglobal access; use `#[QueryParam]`, `#[CookieParam]` attributes
- **ResourceParam**: Use `#[ResourceParam]` for inter-resource dependencies instead of procedural fetching
- **File upload**: Use `#[UploadFiles]` instead of `$_FILES`
- **Composition over inheritance**: Prefer DI over traits or parent class methods
- **Provider usage**: Use `toConstructor` for simple bindings; Provider only for complex creation logic
- **Global references**: No `define` constants or direct static method calls
- **Entrypoint/context separation**: Entrypoints choose Bootstrap/default context; `APP_CONTEXT` is only an override; modules keep Fake, diagnostics, and presentation separate

#### HTTP and REST Patterns

Evaluate HTTP status codes, Location headers, and page resource restrictions. See `references/http-patterns.md` for details.

- **Status codes**: Return appropriate codes (201 for creation, 204 for deletion, etc.)
- **201 + Location**: `onPost` creating resources must set `$this->code = 201` and `Location` header
- **Page resources**: Should only use `onGet` and `onPost`

#### Advanced Patterns

Evaluate validation, AOP, caching, and authentication patterns. See `references/advanced-patterns.md` for details.

- **Validation**: Use `#[JsonSchema]` for declarative validation instead of manual checks
- **AOP**: Cross-cutting concerns (logging, transactions) should use interceptors
- **Authentication**: Use attribute + interceptor pattern, not inline auth checks in resources
- **Caching**: Use `#[Cacheable]` attribute instead of manual cache logic

## Output Format

```text
## File Evaluation: [file-path]

**Mode:** With baseline | Without baseline
**Baseline status:** present (N violations suppressed) | absent | disabled for this run

### PHPMD Metrics

| Metric | Value | Grade |
|--------|-------|-------|
| Cyclomatic Complexity | X | A/B/C/D |
| NPath Complexity | X | A/B/C/D |
| Parameters | X | A/B/C/D |
| Fields | X | A/B/C/D |

### BEAR.Sunday-Specific Evaluation

| Item | Grade | Comment |
|------|-------|---------|
| Resource design | A/B/C/D | ... |
| Body assignment | OK/Problem | Assign all at once to make structure explicit, not sequential |
| Embed usage | OK/Problem | Check if resource->get() sets to body |
| Return type | OK/Recommended | Whether static is used |
| Dependency injection | A/B/C/D | ... |
| Exception design | OK/Problem | @throws Exception is problematic, use domain exceptions |
| try-catch | OK/Problem | Large try-catch, catching Throwable/Exception is problematic |
| Type safety | A/B/C/D | ... |

### Overall Grade: [A/B/C/D]

### Improvement Suggestions

1. ...
2. ...
```

## References

- [BEAR.Sunday Single Page](https://bearsunday.github.io/llms-full.txt)
- [Resource](https://bearsunday.github.io/manuals/1.0/en/resource.html)
- [Resource Parameters](https://bearsunday.github.io/manuals/1.0/en/resource_param.html)
- [DI](https://bearsunday.github.io/manuals/1.0/en/di.html)
- [AOP](https://bearsunday.github.io/manuals/1.0/en/aop.html)
- [Validation](https://bearsunday.github.io/manuals/1.0/en/validation.html)
- [Database](https://bearsunday.github.io/manuals/1.0/en/database.html)
- [Coding Guide](https://bearsunday.github.io/manuals/1.0/en/coding-guide.html)
- [PHPMD Code Size Rules](https://phpmd.org/rules/codesize.html)
