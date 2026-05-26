---
user-invocable: true
name: bear-clean-style
description: Apply opinionated BEAR.Sunday clean-style refactors derived from MyVendor.Cms (Level 1 mechanical cleanup, Level 2 contract/test hardening, Level 3 semantic refactors: BDR/Ray.MediaQuery, Query/Command split, Result Generator projections, Input DTOs, JsonSchema, ALPS/HAL Link/Embed, smoke tests, SQLQuality, PHPMD, FileUpload, ResourceObject body/status, #[Cacheable] Shape A/B, #[Pager] pagination, #[Validate] application validation, hypermedia workflow tests). Use when user says "clean style", "clean-style", "クリーンスタイル", "MyVendor.Cms style", "apply clean style", "Level 1/2/3 cleanup", "BDR refactor", "Template Projection Lift", or asks to align an existing BEAR.Sunday project with the clean-style conventions. Treat as project style, not framework law.
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

2. **Load the rule catalogue**
   - Read `references/clean-style-conventions.md` for conventions and examples.
   - Prefer project-local conventions when they intentionally conflict with this style. Surface conflicts rather than silently overriding them.

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

## Template Projection Lift

Use this Level 3 refactor when Qiq/Twig/PHP templates contain loop-local domain branching or display computations that belong to a read-side projection.

Good candidates:

- `foreach` over entities with repeated `if ($article->isPublished())`, `summary()`, `publishedAtLabel()`, URL/date/relative-time calculations, or `continue`-style filters.
- The same card/feed/list item display logic duplicated across multiple templates.
- Template loops that can read better as `foreach ($articles->published() as $article)` or `foreach ($articles->feed($now) as $item)`.

Preferred shape:

```php
interface ArticleSelectionQueryInterface
{
    #[DbQuery('article_selection_list', factory: ArticleFactory::class)]
    public function list(string|null $status = null): ArticleSelection;
}
```

```php
final readonly class ArticleSelection implements IteratorAggregate, Countable
{
    /** @return Generator<int, Article> */
    public function published(): Generator { /* yield filtered rows */ }

    /** @return Generator<int, ArticleFeedItem> */
    public function feed(DateTimeImmutable|null $now = null): Generator { /* yield read models */ }
}
```

Do not remove every template `if`. Keep presentation-local conditions such as empty-list messages, selected form options, validation error display, layout/auth toggles, and Page not-found guards in the template when they are clearer there.

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

## Notes
- Project-local convention conflicts: ...
- Checks not run: ...
```

## Output format after editing

- Summarize changed files by level/category.
- Mention behaviour-preserving assumptions.
- Report exact verification commands and results.
- Do not commit, release, bump versions, push, or open PRs unless explicitly requested.
