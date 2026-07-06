# Template Projection Lift

Move loop-local domain branching and display computation from Qiq/Twig/PHP templates to typed read-side projections when the logic is not purely presentational.

## Candidates

- `foreach` over entities with repeated `if ($article->isPublished())`, `summary()`, `publishedAtLabel()`, URL/date/relative-time calculations, or `continue`-style filters.
- The same card/feed/list item display logic duplicated across multiple templates.
- Template loops that can read better as `foreach ($articles->published() as $article)` or `foreach ($articles->feed($now) as $item)`.

## Preferred shape

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

`ArticleFeedItem`-style read models are disposable query-side projections for one display concern. Keep them in `src/Result/*` when they are tied to a query result, not in `src/Entity/*`.

## When to leave logic in the template

Do not remove every template `if`. Keep these in the template:

- Empty-list messages.
- Selected form options and validation error display.
- Layout / auth toggles.
- Page not-found guards (the per-entity `*NotFoundException` throw is presentation-routing, not domain logic — see [resource-patterns.md](resource-patterns.md)).
- Trivial HTML structure conditions.
