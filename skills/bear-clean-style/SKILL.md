---
user-invocable: true
name: bear-clean-style
description: >-
  Apply opinionated BEAR.Sunday clean-style refactors derived from MyVendor.Cms
  (Level 1 mechanical cleanup, Level 2 contract/test hardening, Level 3 semantic
  refactors — BDR/Ray.MediaQuery, Query/Command split, Result Generator
  projections, Input DTOs, JsonSchema, ALPS/HAL Link/Embed, smoke tests,
  SQLQuality, PHPMD, FileUpload, ResourceObject body/status, Cacheable Shape
  A/B, Pager pagination, Validate application validation, hypermedia workflow
  tests). Use when user says "clean style", "clean-style", "クリーンスタイル",
  "MyVendor.Cms style", "apply clean style", "Level 1/2/3 cleanup", "BDR
  refactor", "Template Projection Lift", or asks to align an existing
  BEAR.Sunday project with the clean-style conventions. Treat as project style,
  not framework law.
---

# BEAR Clean Style

## Purpose

Apply an opinionated, MyVendor.Cms-derived BEAR.Sunday clean style to existing projects. This is an execution skill: make small, reviewable code changes that preserve behaviour unless the user explicitly asks for API or schema changes.

Use `bear-clean-style-consultant` first when the user is still deciding whether a change should be applied, which level to choose, or how to split migration batches.

## Operating stance

- Say “clean style” or “project style”; do not present these rules as universal BEAR.Sunday law.
- Separate mechanical fixes, contract hardening, and semantic refactors. Do not mix levels unless the user asks for a broader migration.
- Preserve public behaviour, HTTP contracts, schemas, and generated docs unless scope explicitly includes changing them.
- Confirm style findings by reading source before editing; scanner output is only a candidate list.
- Keep patches surgical and cite important files in the final report.

## Levels

| Level | Name | Apply when | Typical changes |
|---|---|---|---|
| 1 | Surface cleanup | User asks for safe cleanup or “名前だけ/returnだけ” | `ResourceObject` return type to `static`, literal `$this->body`, method order, dependency property naming, Query/Command/SQL naming alignment, removing generic `LogicException`/`RuntimeException` in favour of domain exceptions |
| 2 | Contract and QA hardening | User asks for schemas, docs, tests, or confidence before migration | JsonSchema in/out, body array-shape PHPDoc, ALPS IDs, `#[Link]`/`#[Embed]` rel cleanup, ApiDoc/OpenAPI output, hypermedia workflow + HAL contract tests, SQL smoke, Resource smoke, SQLQuality, PHPMD complexity gates, `#[Validate]` for stateful invariants, Page not-found template guard |
| 3 | Semantic refactor | User asks for BDR, architecture, projection, or “semantic” migration | BDR/Ray.MediaQuery adoption, Query/Command split, `src/Result/*`, typed SELECT results, named `Generator`, Template Projection Lift, Input DTO, FileUpload value object, AffectedRows, natural-key reselect after insert, `#[Pager]`/`PagesInterface` pagination, `#[Cacheable]` Shape A/B normalization |

## Workflow

1. **Establish scope**
   - Read project-local guidance first (`AGENTS.md`, `CLAUDE.md`, docs, coding standard files).
   - Detect namespace, resource layout, SQL layout, test layout, composer scripts, and existing uncommitted changes.
   - Determine requested level. If unclear, default to the narrowest level that satisfies the user’s explicit request.

2. **Load only the rule files you need (progressive disclosure)**
   - Start with `references/clean-style-conventions.md` — the index. It defines Levels and architecture; do not read other files until classification picks them.
   - Then load topic files matching the change. The index lists them; common pairings:
     - Renaming methods/files/properties → `naming.md`
     - `on*` handler bodies, status codes, exceptions, pagination, named args → `resource-patterns.md`
     - `#[Embed]`/`#[Link]` or workflow tests → `hypermedia.md`
     - `#[Cacheable]` decisions → `cache.md`
     - Insert paths, SQL projections, entity constructors → `data-contract.md`
     - POST/PUT signature design, `#[Validate]` → `input-validation.md`
     - Loop-local domain branching in templates → `template-projection.md`
     - Test layers and quality gates → `tests.md`
   - Prefer project-local conventions when they intentionally conflict with this style. Surface conflicts rather than silently overriding them (see "Conflict reporting" below).

3. **Scan and classify candidates**
   - Optional broad scan from the target project root:
     ```bash
     python3 /path/to/bear-clean-style/scripts/scan_bear_clean_style.py .
     ```
   - Confirm each candidate manually before editing.
   - Classify each finding by level and risk.

4. **Apply focused batches**
   - Level 1: keep changes mechanical and behaviour-preserving.
   - Level 2: add/adjust contracts and tests with exact expected outputs.
   - Level 3: migrate one semantic slice at a time, such as one resource family or one template projection.
   - Rename in lock-step: PHP symbols, SQL IDs, SQL filenames, tests, schemas, ALPS/OpenAPI references, and docs.

5. **Handle Embed correctly**
   - `#[Embed]` is for GET representation composition. Do not discuss POST/PUT/DELETE as Embed candidates.
   - Prefer `#[Embed]` when an `onGet` response embeds related resource representations and the rel is a taxonomy noun.
   - Keep ResourceClient/resource calls when the fetched data is transient orchestration, validation, authorization, branching, or write workflow data rather than part of the final GET representation.

6. **Verify**
   - Run the narrowest meaningful project checks first: targeted PHPUnit, smoke tests, composer scripts, static analysis, or syntax checks.
   - If checks cannot run, report why and what remains unverified.

## Conflict reporting

This style is opinionated and not universal. When project-local guidance (`AGENTS.md`, `CLAUDE.md`, `docs/conventions.md`, coding-standard files, or in-code comments marked as deliberate decisions) contradicts a clean-style rule, **defer to the project** and report the conflict — do not silently override it.

Surface every conflict explicitly in the final report:

```markdown
## 規約衝突 (Convention conflicts)

| Project rule | Clean-style rule | Source | Resolution | Action |
|---|---|---|---|---|
| Resource handlers may return `ResourceObject` for legacy types | Use `static` return type | `docs/conventions.md:42` | Followed project rule | Not applied |
| Generic `RuntimeException` accepted in `src/Boot` | Domain-named subclass under `Exception\` | `AGENTS.md:118` | Followed project rule | Not applied for `src/Boot`; applied elsewhere |
```

Rules:

- One row per distinct conflict, with the project source cited as `path:line`.
- "Resolution" is always **Followed project rule** unless the user explicitly overrode it in the current request.
- "Action" names the candidates that were left unchanged because of the conflict.
- If a conflict scope is partial (e.g. project rule only covers `src/Boot`), the action row says exactly where the project rule wins and where the clean style was still applied.
- Do not edit project-local docs to align them with this style. Surface, defer, move on.

If no conflicts were found, omit the section.

## Output format for report-only or dry-run requests

```markdown
## BEAR clean style findings

| Level | Priority | File | Finding | Suggested change | Confidence |
|---|---|---|---|---|---|
| 1 | P1 | src/... | ... | ... | High |

## Proposed batches
1. Level 1 mechanical cleanup
2. Level 2 contract/test hardening
3. Level 3 semantic refactor slice

## 規約衝突
(see Conflict reporting — omit when empty)

## Notes
- Checks not run: ...
```

## Output format after editing

- Summarize changed files by level/category.
- Mention behaviour-preserving assumptions.
- Report exact verification commands and results.
- Include the **規約衝突** section when any candidate was skipped because of a project-local conflict.
- Do not commit, release, bump versions, push, or open PRs unless explicitly requested.
