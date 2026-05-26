# Data contract — writes, DML metadata, and SQL/Entity lock-step

## Insert and write contracts

- Avoid `lastInsertId`; after `add`, re-select by the natural key the client supplied (`bySlug`, `byEmail`, `byFilename`) to obtain the generated id. Natural-key re-select gives back the assigned id without driver-dependent state.
- Canonical Resource-facing Command methods usually return `void`.
- If write metadata is genuinely needed, create an explicit command returning a MediaQuery result object such as `Ray\MediaQuery\Result\AffectedRows`.
- `DbQueryInterceptor` routes `#[DbQuery]` calls through return types. Fake implementations should mirror the same path; do not depend on direct `exec()` calls for normal MediaQuery writes.

## SELECT/Entity column-order lock-step

When using `FetchNewInstance` / `PDO::FETCH_FUNC`, SELECT column order **must** match the entity constructor parameter order exactly — Ray.MediaQuery binds positionally, so a swap silently routes the wrong value to the wrong field. Update entity constructor and SQL projection in lock-step.

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

The scanner's `find_select_entity_column_order` check enforces this for `<entity>_item.sql`, `<entity>_list.sql`, and `<entity>_by_<key>.sql` files whose matching `src/Entity/<Entity>.php` is `final readonly`. It skips files with `*`, function expressions, `UNION`, or different field sets (only pure-reorder mismatches are flagged as P1, to avoid noise on JOIN-derived projections).

## Why DTOs are not pushed through the Command interface

Ray.MediaQuery natively supports Input DTOs in `#[DbQuery]` interfaces. The clean style deliberately does **not** use it. The Resource layer here is not a passthrough — `Article::onPost` / `onPut` unpacks the DTO into named scalar args at the Command boundary because the Resource also runs `syncTags()`, performs a `bySlug` round-trip, and may rearrange write/read sequencing. Hiding that work behind a single DTO pass would misrepresent what the Resource does.

Coupling `<Entity>CommandInterface` to a per-resource `Input` shape would also erase the Read/Write layer split. Positional unpacking at the call site is consistent with the named-arguments rule (see [resource-patterns.md](resource-patterns.md)) — clear verb-then-fields order, no literal bool, no skipped middle.
