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
| Result/projection | `src/Result/*` only for Ray.MediaQuery result objects, DML metadata, and query-local read-side projections |
| Input DTO | `src/Input/*Input.php` at the Resource boundary |
| App resource | `src/Resource/App/<Resource>.php` for API/domain resource surface |
| Page resource | `src/Resource/Page/*` for HTML page orchestration |
| SQL | `var/db/sql/<entity>_<verb>.sql` |
| JSON Schema | `var/json_validate/*` or project-local schema directory, kept in sync with `#[JsonSchema]` |
| Fake/test runtime | `tests/Fake/*`, fake contexts, smoke tests |

Read and Write stay split even though both interfaces live under `src/Query/`. The suffix (`QueryInterface` vs `CommandInterface`) carries the CQRS distinction and lets MediaQuery scan one directory.

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
- Page template for a primary entity: guard against null and throw the entity-specific `*NotFoundException` at the top of the template so the error template path is used.

### Method order

1. `__construct`
2. public `on*` handlers in HTTP order: `onGet`, `onPost`, `onPut`, `onDelete`
3. private helpers

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

When using `FetchNewInstance` / `PDO::FETCH_FUNC`, SELECT column order must match the entity constructor parameter order exactly. Update entity constructor and SQL projection in lock-step.

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

Use JSON Schema for shape/range constraints. Use an injected validation service for stateful invariants such as duplicate slug checks. Validators should return validation errors and let the interceptor/resource boundary map them to 422.

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

- Prefer hermetic fake tests for the default suite.
- Add SQL smoke tests for placeholder/parameter coverage and basic prepare/execute validity.
- Add MediaQuery/Resource smoke tests for broad wiring coverage.
- Add hypermedia tests for user workflows and HAL envelope contracts.
- Use Koriym.SqlQuality for SQL plan/performance checks when available or requested.
- Use PHPMD complexity reports to prioritize large semantic refactors.
- Avoid mocks for internal dependencies when fake contexts provide a better behavioural surface.

## 12. Migration batch suggestions

1. Level 1A: `static` return types, obvious body literals, method order.
2. Level 1B: naming alignment for Query/Command methods, SQL ids/files, resource properties.
3. Level 2: schemas, body PHPDoc, ALPS/Link/Embed cleanup, smoke/hypermedia/SQLQuality/PHPMD.
4. Level 3A: Template Projection Lift for one visible read path.
5. Level 3B: BDR/Query/Command/Input/FileUpload/AffectedRows migration by resource family.
