---
user-invocable: true
name: bear-preflight
description: Comprehensive pre-deployment check. Generates reports for Compile, Security, Performance, and Quality, then determines deployment readiness.
---

# BEAR.Sunday Preflight Check

Run a comprehensive pre-deployment check and generate a report.

## Check Items

### 1. Compile Check

#### Static Bindings

```bash
./vendor/bin/bear.compile 'App\Name' prod-app-context
```

- DI container compilation success
- Unbound dependency detection
- AOP weaving errors

#### Runtime Bindings

Verify bindings that cannot be detected by static compilation:

| Pattern | Verification Method |
|---------|---------------------|
| Ray.MediaQuery Entity | Execute query -> verify Entity mapping |
| FactoryInterface | Test creation via factory |
| AssistedInject | Test creation with arguments |
| Provider branching | Verify execution of each condition path |

```php
// Ray.MediaQuery Entity verification example
// Verify that SQL return values can be mapped to the Entity class
interface ArticleQueryInterface
{
    #[DbQuery('article_item')]
    public function item(string $id): Article|null;  // <- Verify Article property and column name match
}
```

### 2. Security Check

#### SAST (Static Analysis)

```bash
./vendor/bin/bear.security-scan src
```

- SQL injection
- XSS
- Path traversal
- Other OWASP Top 10

#### Sensitive Information Detection

```bash
# Search for hardcoded sensitive information
grep -r "password\s*=" src/ --include="*.php"
grep -r "api_key\s*=" src/ --include="*.php"
grep -r "secret" src/ --include="*.php"
```

#### Environment Settings

| Item | Production Setting |
|------|--------------------|
| APP_DEBUG | false |
| APP_ENV | production |
| Error display | Disabled |

### 3. Performance Check

#### Cache Configuration

```bash
# Detect resources without cache attributes
grep -rL "#\[Cacheable\]" src/Resource/App/ --include="*.php"
```

| Resource Type | Recommended Cache |
|---------------|-------------------|
| Read (onGet) | `#[Cacheable]` or `#[DonutCache]` |
| Write | No cache |
| Static content | Long TTL |

#### SQL Performance

```bash
# EXPLAIN analysis with Koriym.SqlQuality
./vendor/bin/sql-quality var/sql/
```

- Full table scans
- Inefficient JOINs
- Unused indexes

#### N+1 Detection

Detect in-loop queries from Embed resources.

### 4. Quality Check

#### Static Analysis

```bash
./vendor/bin/phpstan analyse -c phpstan.neon
./vendor/bin/psalm
./vendor/bin/phpmd src text codesize,design
```

#### Tests

```bash
./vendor/bin/phpunit
```

| Metric | Threshold |
|--------|-----------|
| Test pass rate | 100% |
| Coverage | 80% or above (recommended) |

#### Coding Standards

```bash
./vendor/bin/phpcs
# or
./vendor/bin/php-cs-fixer fix --dry-run --diff
```

### 5. Dependency Check

#### composer.lock

```bash
# Verify lock file existence
test -f composer.lock && echo "OK" || echo "MISSING"

# Ensure dev dependencies are not included in production
composer install --no-dev --dry-run
```

#### Security Advisories

```bash
composer audit
```

### 6. Configuration Check

#### Context Verification

```php
// Verify production context
// prod-app, prod-html-app, etc.
```

#### Environment Variables

Verify required environment variables exist:

```bash
# Compare .env.example with actual settings
diff <(grep -oP '^[A-Z_]+=' .env.example | sort) <(grep -oP '^[A-Z_]+=' .env | sort)
```

## Output Format

```markdown
# Preflight Check Report

Generated: 2024-01-15 10:30:00
Project: MyApp
Context: prod-app

## Summary

| Category | Status | Issues |
|----------|--------|--------|
| Compile | ✅ Pass | 0 |
| Security | ⚠️ Warn | 2 |
| Performance | ✅ Pass | 0 |
| Quality | ✅ Pass | 0 |
| Dependencies | ✅ Pass | 0 |
| Configuration | ⚠️ Warn | 1 |

**Result: ⚠️ Review Required**

## Details

### Compile
✅ bear.compile succeeded
✅ All dependencies bound
✅ Ray.MediaQuery entities verified

### Security
✅ SAST: 0 vulnerabilities
⚠️ .env: APP_DEBUG=true (should be false)
⚠️ Hardcoded credential found: src/Module/ApiModule.php:42

### Performance
✅ Cache attributes configured
✅ SQL quality: No issues
✅ No N+1 patterns detected

### Quality
✅ PHPStan: 0 errors
✅ Psalm: 0 errors
✅ Tests: 156 passed, 0 failed
✅ Coverage: 85%

### Dependencies
✅ composer.lock exists
✅ No dev dependencies in production
✅ No security advisories

### Configuration
✅ Context: prod-app
⚠️ LOG_LEVEL=debug (recommend: warning or error)

## Action Items

1. [ ] Set APP_DEBUG=false in production .env
2. [ ] Remove hardcoded credential in ApiModule.php
3. [ ] Change LOG_LEVEL to warning or error

## Recommendation

Address 3 warnings before deployment.
```

## Decision Criteria

| Status | Condition | Action |
|--------|-----------|--------|
| ✅ Pass | All items clear | Ready to deploy |
| ⚠️ Warn | Warnings present, no blockers | Deploy after review |
| ❌ Fail | Blockers present | Do not deploy |

### Blockers (Deployment Blocked)

- Compile errors
- Unbound dependencies
- Test failures
- Critical security vulnerabilities
- Security advisories (Critical/High)

### Warnings (Review Required)

- Coverage below threshold
- Resources without cache configuration
- Debug settings enabled
- Performance warnings

## When to Run

- Pre-deployment (manual)
- CI/CD pipeline (automated)
- Periodic audit (weekly/monthly)

## CI/CD Integration Example

```yaml
# GitHub Actions
- name: Preflight Check
  run: |
    composer install --no-dev
    ./vendor/bin/bear.compile 'App\Name' prod-app
    ./vendor/bin/phpunit
    ./vendor/bin/bear.security-scan src
    composer audit
```
