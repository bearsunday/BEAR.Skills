# BEAR clean style conventions — index

This reference distills conventions observed in `MyVendor.Cms/docs`. They are intentionally opinionated. Apply them when the user asks for this style; do not present them as universal BEAR.Sunday requirements.

Topic files live alongside this index. Load only what the current task needs.

## 1. Levels and migration categories

| Level | Category | Examples |
|---|---|---|
| 1 | Surface cleanup | `ResourceObject` return type to `static`, literal response body assignment, method order, resource dependency property names, Query/Command/SQL naming, removing generic `LogicException`/`RuntimeException` |
| 2 | Contract and QA hardening | JsonSchema response/request validation, body array-shape PHPDoc, ALPS IDs, `#[Link]`, `#[Embed]`, ApiDoc/OpenAPI, hypermedia workflow + HAL contract tests, SQL smoke, Resource smoke, SQLQuality, PHPMD complexity checks, `#[Validate]` for stateful invariants, Page not-found template guard |
| 3 | Semantic refactor | BDR/Ray.MediaQuery, Read/Write split, typed Result classes, named `Generator`, Template Projection Lift, Input DTO, FileUpload value object, AffectedRows, natural-key reselect after insert, `#[Pager]`/`PagesInterface` pagination, `#[Cacheable]` Shape A/B normalization |

Prefer Level 1 before Level 3 in large legacy projects. Level 3 changes move responsibilities and need tests, even when behaviour should remain the same.

## 2. Architecture and placement

| Concern | Clean style |
|---|---|
| Entity | `src/Entity/<Entity>.php`; often `final readonly class`; public constructor-promoted properties for hydrated rows |
| Read interface | `src/Query/<Entity>QueryInterface.php` |
| Write interface | `src/Query/<Entity>CommandInterface.php` |
| Result/projection | `src/Result/*` only for Ray.MediaQuery result objects, DML metadata, and query-local read-side projections — never domain entities, controllers, or service helpers |
| Input DTO | `src/Input/*Input.php` at the Resource boundary |
| App resource | `src/Resource/App/<Resource>.php` for API/domain resource surface |
| Page resource | `src/Resource/Page/*` for HTML page orchestration |
| Variation resources | `src/Resource/App/Variations/*` for comparison-only resources (different data shape, abstraction level, or framework axis). Not registered in the ALPS profile and must not change the canonical resource path. Use sparingly |
| SQL | `var/sql/<entity>_<verb>.sql` |
| Response JSON Schema | `var/json_schema/<entity>.json` (flat, no subdirs) |
| Input JSON Schema | `var/json_validate/<entity>_<verb>.json`, kept in sync with `#[JsonSchema(params: ...)]` |
| Fake data | `var/fake/<entity>.json` (deterministic seed such as `mt_srand(42)`). Project-local convention; not specified by the BEAR.Sunday manual |
| ALPS profile | `docs/alps.json` as the single source of truth for semantics (BEAR.ApiDoc `docDir`/`alps`) |
| Fake/test runtime | `tests/Fake/*`, `fake-` and `test-` contexts, smoke tests |

Read and Write stay split even though both interfaces live under `src/Query/`. The suffix (`QueryInterface` vs `CommandInterface`) carries the CQRS distinction and lets MediaQuery scan one directory.

### Contexts

| Context | Where it runs |
|---|---|
| `hal-api-app` | Production HTTP |
| `cli-hal-api-app` | CLI entry, composer scripts, bin scripts |
| `fake-hal-api-app` | Dev runtime against `FakeSqlQuery` (no DB) |
| `test-hal-api-app` | PHPUnit; composes `FakeModule` via `TestModule` |
| `html-hal-app` / `html-test-hal-api-app` | HTML/Page contexts that install `HtmlModule` |

`fake-` and `test-` are the canonical prefixes; do not invent variants. Module composition is two-stage: `FakeModule` provides the bindings, `TestModule` *installs* `FakeModule`. When module bindings change, clear the DI cache for every context that was used.

## 3. Topic files

Read each only when the candidate change touches that area.

| File | Covers | Load when |
|---|---|---|
| [naming.md](naming.md) | Query/Command method names, SQL filenames, resource dependency property names, ALPS/HAL rel naming | Renaming methods, files, or properties; aligning a Read/Write split |
| [resource-patterns.md](resource-patterns.md) | `ResourceObject` return type, body construction, HTTP status codes, not-found handling, method order, exceptions, pagination, named arguments | Touching `on*` handlers, return shapes, or call-site argument style |
| [hypermedia.md](hypermedia.md) | `#[Embed]` vs `#[Link]`, ALPS taxonomy/choreography split, hypermedia workflow tests with `#[Depends]` chains | Adding embeds/links, writing rel-following workflow tests |
| [cache.md](cache.md) | `#[Cacheable]` defaults, Shape A (`#[Embed]` only) vs Shape B (`fromAssoc`), anti-patterns | Adding cache, debugging stale tags, refactoring invalidation |
| [data-contract.md](data-contract.md) | Insert/write contracts, `lastInsertId` avoidance, `AffectedRows`, SELECT/Entity column-order lock-step | Touching SQL projections, entity constructors, or write paths |
| [input-validation.md](input-validation.md) | Scalar vs Input DTO decision rule, typed-array DTO pitfall, `#[Validate]` for stateful invariants | Designing a new POST/PUT signature or validation rule |
| [template-projection.md](template-projection.md) | Template Projection Lift, `IteratorAggregate` + named `Generator` traversals, `src/Result/*` placement | Loop-local domain branching or repeated display logic in templates |
| [tests.md](tests.md) | Hermetic fakes, SQL/Resource smoke, SQLQuality, PHPMD, mock policy | Adding test layers or quality gates |

## 4. Migration batch suggestions

1. Level 1A: `static` return types, obvious body literals, method order.
2. Level 1B: naming alignment for Query/Command methods, SQL ids/files, resource properties; removing generic SPL exceptions.
3. Level 2: schemas, body PHPDoc, ALPS/Link/Embed cleanup, smoke/hypermedia/SQLQuality/PHPMD, `#[Validate]` for stateful invariants, Page not-found template guards.
4. Level 3A: Template Projection Lift for one visible read path.
5. Level 3B: BDR/Query/Command/Input/FileUpload/AffectedRows migration by resource family, `#[Cacheable]` Shape A/B normalization.
