---
user-invocable: true
name: bear-migration
description: >-
  Migrate legacy PHP applications (Symfony/EC-CUBE style) to BEAR.Sunday
  safely. Reference-first migration workflow that preserves known behaviour
  and avoids inventing unapproved infrastructure. Use when the user says
  移植, マイグレーション, legacy migration, route migration, Symfony to BEAR,
  EC-CUBE to BEAR, or asks how to port an existing PHP application to
  BEAR.Sunday without custom framework code.
---

# BEAR.Sunday Migration Skill

Migrate existing applications to BEAR.Sunday by preserving known behavior, consulting references first, and avoiding unapproved framework invention.

## Non-negotiable guardrails

1. **No large mechanism without consent**
   - Do not introduce routing layers, AOP interceptors, test harnesses, code generators, schema systems, auth flows, or broad rewrites without an explicit proposal and user approval.
   - If a change affects many files or creates a new architectural primitive, stop at a plan/issue unless the user already approved that exact mechanism.

2. **Information sufficiency gate**
   - Before implementation, list what is known, unknown, and assumed.
   - If behavior depends on the original application, inspect the original code/routes/templates/config/tests first.
   - If key context is unavailable, ask or create a narrow reversible slice; do not fill gaps with invention.

3. **Reference-first, custom-last**
   - Check BEAR.Sunday, BEAR.Skeleton, BEAR.Resource, Ray.Di, installed packages, and nearby mature BEAR apps before writing custom framework code.
   - Prefer existing BEAR modules and conventions over bespoke replacements.
   - For path parameters and named route generation, consider `bear/aura-router-module` / Aura.Router before writing a custom router.

4. **ALPS stays semantic**
   - ALPS describes states and transitions, not URLs, HTTP methods, PHP classes, or implementation status.
   - Keep implementation coverage, route coverage, and ALPS descriptor coverage as separate axes.

5. **Migration is not greenfield design**
   - Preserve original route names, wire parameters, redirects, status codes, templates, and user-visible behavior unless a change is approved.
   - Do not count placeholder/safe fallback pages as implemented business behavior.

## Migration workflow

### 1. Baseline inventory

Capture the current state before changing code:

```bash
git status --short --branch
rg -n "TODO|unsupported|ActionRedirect|Not Implemented|未実装" src var/templates tests docs || true
composer validate
```

Record existing failing tests separately from failures introduced by the migration.

### 2. Original behavior map

For each feature slice, inspect the source application first:

- route name, path pattern, HTTP methods
- controller/action or handler
- template names and form actions
- request parameters and aliases
- redirects, flash messages, status codes
- persistence/query behavior
- security/CSRF/auth requirements

Produce a small mapping table before implementing:

| Original route/action | BEAR Resource | ALPS ID | Status | Notes |
| --- | --- | --- | --- | --- |

### 3. Reference check

Before adding a new mechanism, search references and local dependencies:

```bash
rg -n "AuraRouterModule|RouterInterface|WebRouter|RouterContainer" vendor src /path/to/reference 2>/dev/null || true
rg -n "bindInterceptor|MethodInterceptor|JsonSchema|Alps|Purge" vendor src /path/to/reference 2>/dev/null || true
```

Decision rule:

- **Existing BEAR/package support exists** → use or adapt it.
- **Only small app-specific mapping is missing** → add a thin catalog/adapter.
- **New infrastructure seems necessary** → write a proposal with trade-offs and wait for approval.

### 4. Approval gate for architectural changes

Use this template before any broad mechanism:

```markdown
## Proposed mechanism

Problem:
Existing reference/convention checked:
Options considered:
Chosen option:
Files affected:
Rollback plan:
Tests that prove parity:
Open questions:
```

Do not implement until the user approves.

### 5. Thin vertical slice

Implement one representative route/resource first. Verify:

- original route name still generates the expected URL
- browser-visible HTML uses GET/POST only
- internal BEAR Resource method can be `get/post/put/delete` as appropriate
- ALPS descriptor exists and is not overloaded with implementation detail
- fake/in-memory tests and render tests pass for the slice

Then expand mechanically.

### 6. Documentation and status tracking

Keep status tables honest:

- **ALPS対応**: descriptor exists and matches the transition/state meaning
- **Route対応**: original route name/path/method maps to BEAR
- **実装状態**: real business Resource vs safe fallback/redirect
- **テスト状態**: only include tests that actually assert the behavior

Never merge these into a single optimistic label.

## Anti-patterns to avoid

- Writing a custom router when Aura.Router / BEAR router modules cover matching and generation.
- Introducing app-wide AOP, custom dispatchers, or global context stacks before proving the framework lacks the capability.
- Treating a generated placeholder, redirect, or no-op as feature completion.
- Renaming public route names or request fields just to fit new code.
- Starting large mechanical rewrites while the working tree has unrelated changes or no test baseline.
- Describing private reference projects, paths, or names in public issues/PRs.

## Preferred outputs

When asked for a migration plan, return:

1. current-state audit
2. original/reference sources consulted
3. mapping table
4. risks and approval gates
5. smallest first slice
6. test plan
7. explicit out-of-scope items

When asked to implement, keep changes small and commit only when the user asks or the repository instructions require it.
