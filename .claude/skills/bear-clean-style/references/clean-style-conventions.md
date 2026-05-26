# BEAR clean style conventions

This reference distills conventions observed in `MyVendor.Cms/docs`. They are intentionally opinionated. Apply them when the user asks for this style; do not present them as universal BEAR.Sunday requirements.

## 1. Levels and migration categories

| Level | Category | Examples |
|---|---|---|
| 1 | Surface cleanup | `ResourceObject` return type to `static`, literal response body assignment, method order, resource dependency property names, Query/Command/SQL naming |
| 2 | Contract and QA hardening | JsonSchema response/request validation, body array-shape PHPDoc, ALPS IDs, `#[Link]`, `#[Embed]`, ApiDoc/OpenAPI, hypermedia tests, SQL smoke, Resource smoke, SQLQuality, PHPMD complexity checks |
| 3 | Semantic refactor | BDR/Ray.MediaQuery, Read/Write split, typed Result classes, named `Generator`, Template Projection Lift, Input DTO, FileUpload value object, AffectedRows, natural-key reselect after insert |

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
| SQL | `var/db/sql/<entity>_<verb>.sql` |
| Response JSON Schema | `var/json_schema/<entity>.json` (flat, no subdirs) |
| Input JSON Schema | `var/json_validate/<entity>_<verb>.json`, kept in sync with `#[JsonSchema(params: ...)]` |
| Fake data | `var/fake/<entity>.json` (deterministic seed such as `mt_srand(42)`) |
| ALPS profile | `var/alps/profile.json` as the single source of truth for semantics |
| Fake/test runtime | `tests/Fake/*`, `fake-` and `test-` contexts, smoke tests |

Read and Write stay split even though both interfaces live under `src/Query/`. The suffix (`QueryInterface` vs `CommandInterface`) carries the CQRS distinction and lets MediaQuery scan one directory.

## 2a. Contexts

| Context | Where it runs |
|---|---|
| `hal-api-app` | Production HTTP |
| `cli-hal-api-app` | CLI entry, composer scripts, bin scripts |
| `fake-hal-api-app` | Dev runtime against `FakeSqlQuery` (no DB) |
| `test-hal-api-app` | PHPUnit; composes `FakeModule` via `TestModule` |
| `html-hal-app` / `html-test-hal-api-app` | HTML/Page contexts that install `HtmlModule` |

`fake-` and `test-` are the canonical prefixes; do not invent variants. Module composition is two-stage: `FakeModule` provides the bindings, `TestModule` *installs* `FakeModule`, so prod/cli/fake/test contexts can compose differently. When module bindings change, clear the DI cache for every context that was used.

## 3. Query, Command, SQL, and property naming

### Interfaces

- Read: `<Entity>QueryInterface`
- Write: `<Entity>CommandInterface`
- Avoid mixing read methods into Command interfaces or write methods into Query interfaces.

### Read method names

| Purpose | Method | SQL id / file |
|---|---|---|
| Primary-key item | `item(int $id)` | `<entity>_item` / `<entity>_item.sql` |
| Natural key item | `bySlug`, `byEmail`, `byFilename` | `<entity>_by_slug.sql` etc. |
| Collection | `list()` | `<entity>_list.sql` |
| Filtered collection | `listBy<Article|Author|...>()` | `<entity>_list_by_<x>.sql` |

### Write method names

Use imperative verbs: `add`, `update`, `delete`. Link-table commands may use `clear` and `link`.

### Resource dependency property names

| Dependency | Property pattern | Example |
|---|---|---|
| Main read interface | `$<entity>` | `private ArticleQueryInterface $article` |
| Main write interface | `$<entity>Cmd` | `private ArticleCommandInterface $articleCmd` |
| Link/helper write interface | `$<entity><Role>` | `private ArticleTagCommandInterface $articleTagCmd` |

This asymmetry is intentional: `$this->article->item($id)` reads as a queryable source; `$this->articleCmd->add(...)` reads as a command tool.

## 4. ResourceObject patterns

### Return type

Use `static` for `on*` methods that return `$this`:

```php
public function onGet(int $id): static
```

### Body construction

Use `ResourceObject::$body` as the single response channel.

- With `#[Embed]`: mutate injected `Request` slots, then use no-overwrite union.
  ```php
  $this->body['author']->addQuery(['id' => $article->authorId]);
  $this->body['tagList']->addQuery(['articleId' => $article->id]);

  $this->body += [
      'id' => $article->id,
      'title' => $article->title,
  ];
  ```
- Without `#[Embed]` or on write/error paths: assign a full literal.
  ```php
  $this->body = [
      'id' => $article->id,
      'title' => $article->title,
  ];
  ```
- Avoid scattered sequential assignments when the final response shape can be read as one literal.

### HTTP status codes

| Method | Success | Not found | Validation failure |
|---|---:|---:|---:|
| GET | 200 | 404 | n/a |
| POST creating an addressable resource | 201 + `Location` | n/a | 422 |
| POST action/non-creating exchange | 200 + body | n/a | 422 |
| PUT | 200 | 404 | 422 |
| DELETE | 204 | 404 | n/a |
| Unique-key conflict | 409 | n/a | n/a |

### Not found

- App resource read miss: set `$this->code = Code::NOT_FOUND` and a literal error body; do not throw for ordinary read misses.
- Page template for a primary entity: guard against null and throw the entity-specific `*NotFoundException` at the top of the template so the framework's `catch (Throwable)` path routes to `templates/Error.php`.

  ```php
  <?php
  /** @var \MyVendor\Cms\Entity\Article|null $article */
  if (! isset($article) || $article === null) {
      throw new \MyVendor\Cms\Exception\ArticleNotFoundException();
  }
  ```

  Exceptions are per-entity (`ArticleNotFoundException`, `AuthorNotFoundException`, …), not shared. Only the *primary* entity needs the guard; list-shaped vars are always lists (possibly empty), not null. Every Page test for such a resource should include a `testNotFoundRendersErrorTemplate` regression case.

### Method order

1. `__construct`
2. public `on*` handlers in HTTP order: `onGet`, `onPost`, `onPut`, `onDelete`
3. private helpers, after every public method

Reading top-to-bottom should mirror the public surface first, the implementation detail last.

### Exceptions

- No generic `LogicException` / `RuntimeException` in `src/`. Define a domain-named subclass under `<Vendor>\<Project>\Exception\<DomainName>Exception` for any thrown exception.
- Read misses use `$this->code = Code::NOT_FOUND`, not a thrown exception. Page templates re-raise the per-entity `*NotFoundException` to reach the error template.

### Pagination

Collection reads use Ray.MediaQuery's `#[Pager]` and return `PagesInterface`:

```php
interface ArticleQueryInterface
{
    #[DbQuery('article_list'), Pager(perPage: 20, template: '/article{?page}')]
    public function list(): PagesInterface;
}
```

Resource code reads `$pages[$page]`, maps the returned Page object's associative `data` rows through an entity factory, and surfaces `total`, `hasNext`, and `maxPerPage` in `$this->body`. The DB-free fake implements the same contract with Pagerfanta's `ArrayAdapter` so tests exercise the same pagination shape without PDO.

## 5. Embed and hypermedia

`#[Embed]` is only a GET representation-composition concern. Do not apply it to POST/PUT/DELETE workflows.

Use `#[Embed]` when:

- an `onGet` response includes related resource representations;
- the rel is a taxonomy noun such as `author`, `category`, or `tagList`;
- the target URI can be declared and filled with `addQuery()`.

Do not force `#[Embed]` when:

- ResourceClient/resource calls are used for transient orchestration, validation, authorization, or write workflow decisions;
- data is fetched only to branch or compute status and is not part of the final GET representation;
- the composition is body-derived variable-length dependency tracking that needs explicit cache tags.

Keep HAL rel layers separate:

- `#[Link]` rels come from ALPS choreography transitions: `goArticleList`, `goAuthor`, `doCreateArticle`.
- `#[Embed]` rels come from ALPS taxonomy nouns: `author`, `category`, `tagList`.
- Avoid `#[Embed(rel: 'goAuthor')]`; `go*` names are transitions, not embedded taxonomy instances.

## 6. Insert, write, and DML metadata contracts

- Avoid `lastInsertId`; after `add`, re-select by the natural key supplied by the client (`bySlug`, `byEmail`, `byFilename`) to obtain the generated id.
- Canonical Resource-facing Command methods usually return `void`.
- If write metadata is genuinely needed, create an explicit command returning a MediaQuery result object such as `Ray\MediaQuery\Result\AffectedRows`.
- `DbQueryInterceptor` routes `#[DbQuery]` calls through return types. Fake implementations should mirror the same path; do not depend on direct `exec()` calls for normal MediaQuery writes.

## 7. SQL/entity contract

When using `FetchNewInstance` / `PDO::FETCH_FUNC`, SELECT column order must match the entity constructor parameter order exactly — Ray.MediaQuery binds positionally, so a swap silently routes the wrong value to the wrong field. Update entity constructor and SQL projection in lock-step.

The scanner's `find_select_entity_column_order` check enforces this for `<entity>_item.sql`, `<entity>_list.sql`, and `<entity>_by_<key>.sql` files whose matching `src/Entity/<Entity>.php` is `final readonly`. It skips files with `*`, function expressions, `UNION`, or different field sets (only pure-reorder mismatches are flagged as P1, to avoid noise on JOIN-derived projections).

```php
final readonly class Article
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
    ) {}
}
```

```sql
SELECT id, slug, title FROM article WHERE id = :id
```

## 8. Input shape, FileUpload, and validation

Do not apply DTOs everywhere. Choose by shape.

Use scalar parameters when:

- the parameter list is short and flat;
- every parameter maps 1:1 to a JSON Schema property;
- there is no nested structure or tri-state/partial-update semantic.

Use an `Input` DTO when:

- there are many fields and a flat signature becomes unreadable;
- a field has tri-state semantics (`null` vs `[]` vs non-empty list);
- fields form a named structure meaningful at the Resource boundary, such as an OAuth `code`/`state` exchange;
- upload fields should become FileUpload value objects rather than ad-hoc `$_FILES`/array shapes.

DTOs belong at the Resource boundary. Do not pass DTOs through to Command interfaces unless the Resource-to-Command path is truly a transparent passthrough. Resource code should unpack named scalar arguments when it also orchestrates side effects such as tag syncing or natural-key re-select.

### Typed array DTO pitfall

If validation runs after DTO hydration, a typed `array` property may throw `TypeError` before JSON Schema sees invalid input. For array-shaped request fields, accept `mixed` in the DTO constructor, coalesce omitted values intentionally, perform the minimal `is_array` guard, and let JSON Schema validate item details.

### Application validation

Use JSON Schema for shape/range constraints. Use parameter-level `#[Validate(Service::class, 'method')]` for stateful invariants that depend on injected collaborators (uniqueness checks, cross-entity rules):

```php
public function onPost(
    #[Input, Validate(ArticleValidator::class, 'create')] ArticleCreateInput $input,
): static
```

The validator method receives the already-materialised argument and returns `ValidationErrors`; it must not throw for expected violations. `ValidationInterceptor` merges all such errors and raises `ValidationFailedException` (422) only after the validation pass. Page resources catch the same exception and re-render the form with the field messages. Keep JSON Schema responsible for shape/range; keep `#[Validate]` for invariants the schema cannot know.

## 9. Template Projection Lift

Move loop-local domain branching and display computation from Qiq/Twig/PHP templates to typed read-side projections when the logic is not purely presentational.

Candidates:

- `foreach` over entities with status checks, summary/date/url calculations, or null filtering.
- Repeated card/feed/list item logic across templates.
- Template loops that can become a named traversal such as `published()` or `feed()`.

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
    public function published(): Generator;

    /** @return Generator<int, ArticleFeedItem> */
    public function feed(DateTimeImmutable|null $now = null): Generator;
}
```

`ArticleFeedItem`-style read models are disposable query-side projections for one display concern. Keep them in `src/Result/*` when they are tied to a query result, not in `src/Entity/*`.

Leave these in templates when clearer: empty-list messages, selected form options, validation error display, layout/auth toggles, Page not-found guards, and trivial HTML structure conditions.

## 10. Named arguments

Default to positional calls. Use named arguments only when they add information PHP syntax cannot carry:

1. passing literal `true` / `false`;
2. skipping optional arguments to reach a later parameter.

Do not use “3+ args”, “5+ args”, “same type args”, or “nullable args” as automatic named-argument triggers. If a call is unreadable because it has too many arguments, fix the signature or introduce a value object/DTO where appropriate.

## 11. Tests, smoke, and quality gates

- Prefer hermetic fake tests for the default suite. The default `vendor/bin/phpunit` run must not require a database.
- Add SQL smoke tests for placeholder/parameter coverage and basic prepare/execute validity.
- Add MediaQuery/Resource smoke tests for broad wiring coverage.
- Add hypermedia workflow tests for user-story rel chains (see §11a) and a separate contract test for the HAL envelope shape.
- Use Koriym.SqlQuality for SQL plan/performance checks when available or requested.
- Use PHPMD complexity reports to prioritize large semantic refactors, not to mandate a single style.
- Avoid mocks for internal dependencies when fake contexts provide a better behavioural surface. External services use Docker; internal dependencies use Fake classes from `tests/Fake/`.

### 11a. Hypermedia workflow tests

Workflow tests in `tests/Hypermedia/` are user stories told by linking small steps with `#[Depends]` — the `ResourceObject` returned by one step is the input the next step follows a rel from. **One file per story.** Class name is `<Actor><Verb>Test` (e.g. `ReaderBrowsesByTagTest`, `EditorManagesArticleTest`); method names are the steps in third-person narrative present, so PHPUnit's testdox output reads top-to-bottom as the story.

Rules:

1. **One file per story.** Contract pins such as the HAL envelope shape live in their own `*ContractTest` class.
2. **Exactly one hard-coded URI per story — the entry point.** Every subsequent transition goes through `ResourceInterface::href($rel, $vars, $ro)`, which reads `#[Link]` off the source resource. Renaming an ALPS Choreography transition will break the chain and surface here.
3. **One step per `#[Depends]`-linked test method.** First test performs the entry GET/POST and returns the `ResourceObject`; each follow-up declares `#[Depends('previousStep')]`.
4. **Pass the specific id explicitly when crossing entities.** `Anchor::href()` merges the source body into the URI Template, so `follow($article, 'goAuthor')` looks correct but silently feeds the article's `id` into the author slot when every resource exposes `id` as its primary key. Always write `follow($article, 'goAuthor', ['id' => $article->body['authorId']])` so the cross-entity flow is visible at the call site.
5. **Per-step shape validation belongs to `#[JsonSchema]`, not workflow tests.** Workflow tests assert status codes, rel chains, and business invariants (an edit must be visible to the next read). Field-level shape checks belong in resource smoke tests or the schema attribute.
6. **Canonical lifecycle for write-capable resources: create → read → edit → read → delete → 404.**
7. **`_embedded` vs `_links` is pinned in a contract test, not in stories.** Taxonomy nouns under `_embedded`, Choreography verbs under `_links`.
8. **`Location` after `POST` is the navigation cue.** A hypermedia client cannot guess the URL of a just-created resource, so `onPost` returns `Location: /<noun>?id=<id>` and the workflow test follows it the same way a browser would. PUT and DELETE are unsafe transitions invoked directly by HTTP method — they are not advertised as `_links` rels by design.

## 11b. `#[Cacheable]` and cross-resource invalidation

`#[Cacheable]` showcase resources canonicalize two patterns. Anything else is an anti-pattern.

### Default — user-zero-code leaf

A read resource that does not aggregate other resources needs only `#[Cacheable]`. The framework writes the self URI tag and `RefreshSameCommand` purges it on `PUT`/`POST`/`PATCH`/`DELETE` to the same URI. **Do not** touch `Header::SURROGATE_KEY`, inject `UriTagInterface`, or call `DonutRepositoryInterface::invalidateTags()` on a leaf resource. Each duplicates framework behaviour and breaks the `CacheDependency::depends()` assertion that forbids mixing manual and automatic Surrogate-Key writes.

### Cross-resource — two shapes only

Pick by whether the dependency set is statically expressible at declaration time.

**Shape A — `#[Embed]` alone (automatic).** When the parent composes exactly the children it depends on via `#[Embed]`, no manual cache code is required. `QueryRepository::setCacheDependency` walks the body before HAL renders and merges every Cacheable child's Surrogate-Key into the parent.

**Shape B — explicit `fromAssoc` (dynamic / body-derived).** When the dependency set is N URIs whose count or parameters come from the database, `#[Embed]` cannot statically express it. Read the rows and map them through `UriTagInterface::fromAssoc('<template>', $assocList)`, assigning the result to `Header::SURROGATE_KEY`. When `$items === []`, leave the header unset — `fromAssoc([])` returns `''` and the tag-aware cache adapter rejects empty tags.

Pick A whenever the dependency set is `#[Embed]`-expressible. Reach for B only when the count or parameters are body-derived.

### Anti-patterns

- Writing the self URI into `Header::SURROGATE_KEY` (framework already does this).
- Calling `DonutRepositoryInterface::invalidateTags()` from `onPut` / `onDelete` (`RefreshSameCommand` already purges).
- Mixing `#[Embed]` and `fromAssoc()` on the same response — assigning `Header::SURROGATE_KEY` short-circuits the body-walk auto-merge and the embed's child tags are silently dropped. Pick one shape per resource.
- Reaching for `fromAssoc()` when there is no cross-resource dependency — the default `#[Cacheable]`-only leaf is the correct shape.

## 12. Migration batch suggestions

1. Level 1A: `static` return types, obvious body literals, method order.
2. Level 1B: naming alignment for Query/Command methods, SQL ids/files, resource properties.
3. Level 2: schemas, body PHPDoc, ALPS/Link/Embed cleanup, smoke/hypermedia/SQLQuality/PHPMD.
4. Level 3A: Template Projection Lift for one visible read path.
5. Level 3B: BDR/Query/Command/Input/FileUpload/AffectedRows migration by resource family.
