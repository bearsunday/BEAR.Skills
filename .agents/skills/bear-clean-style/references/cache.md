# `#[Cacheable]` and cross-resource invalidation

`#[Cacheable]` resources canonicalize the default leaf and three cross-resource shapes. Anything else is an anti-pattern.

## Default — user-zero-code leaf

A read resource that does not aggregate other resources needs only `#[Cacheable]`. The framework writes the self URI tag and `RefreshSameCommand` purges it on `PUT` / `POST` / `PATCH` / `DELETE` to the same URI. **Do not** touch `Header::SURROGATE_KEY`, inject `UriTagInterface`, or call `DonutRepositoryInterface::invalidateTags()` on a leaf resource. Each duplicates framework behaviour a leaf resource does not need.

## Cross-resource — declare the dependency

Pick by how the parent gets the data: by embedding it, by reading it itself, or from a set with no URI of its own. Each shape is declared, with `fromAssoc()` for rows held as arrays as the one hand-built tag; a shared string key such as `latest_article` or `invalidateTags()` in a write or a service is what these replace.

### Shape A — `#[Embed]` alone (automatic)

When the parent composes exactly the children it depends on via `#[Embed]`, no manual cache code is required. `QueryRepository::setCacheDependency` walks the body before HAL renders, materializes each `AbstractRequest`, and merges every Cacheable child's Surrogate-Key into the parent.

```php
#[Cacheable]
final class AuthorProfile extends ResourceObject
{
    #[Embed(rel: 'author', src: 'app://self/cache/author')]
    public function onGet(int $id): static
    {
        $this->body['author']->addQuery(['id' => $id]);
        return $this;
    }
}
```

### Shape B — `#[DependsOn]` (what `onGet` reads itself)

When `onGet` reads the data itself (a query, a row list), declare what it read. On `onGet`, the template variables are its parameters; on a row class, they are the row's public properties, and each row in the stored body adds its tag:

```php
#[DependsOn(uri: 'app://self/article?id={id}')]
final class ArticleRow
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
```

Rows held as arrays have no class to declare on. Only then, read the rows and map them through `UriTagInterface::fromAssoc('<template>', $assocList)`, assigning the result to `Header::SURROGATE_KEY`.

```php
#[Cacheable]
final class ArticleTags extends ResourceObject
{
    public function onGet(int $articleId): static
    {
        $items = $this->articleTag->listByArticle($articleId);
        if ($items !== []) {
            $this->headers[Header::SURROGATE_KEY] =
                $this->uriTag->fromAssoc('app://self/cache/tag{?id}', $items);
        }
        $this->body = ['tags' => $items];
        return $this;
    }
}
```

When `$items === []`, leave the header unset — `fromAssoc([])` returns `''` and the tag-aware cache adapter rejects empty tags.

Pick A whenever the dependency set is `#[Embed]`-expressible. Reach for B when `onGet` reads the data itself (a query, a row list, a service), and for `fromAssoc()` only when the rows are arrays. Every row adds a tag to `Surrogate-Key` (about 24 KB for 1,000 rows, beyond Fastly's 16,384-byte header limit), so a long list takes Shape C instead.

### Shape C — a virtual resource for a set with no URI

A set that several resources read but that has no URI of its own (the latest articles) is named by an empty resource class with no methods. Readers of the set declare `#[DependsOn(uri: 'app://self/articles')]`, and every write to an article declares `#[Purge(uri: 'app://self/articles')]`. The class gives the URI a home, so qr-graph draws it and warns on a misspelled `#[DependsOn]`; a misspelled `#[Purge]` is not warned about and shows only as a purge that reaches nothing. A paginated list needs no virtual resource: when `page` has a default, purging the list's own URI reaches every page through its group tag.

## Anti-patterns

The scanner flags the first three; the rest must be confirmed by reading the resource.

- **Writing the self URI into `Header::SURROGATE_KEY`** — the framework's `SurrogateKeys::setSurrogateHeader` already does this.
- **Calling `DonutRepositoryInterface::invalidateTags()` from `onPut` / `onDelete`** — `CommandInterceptor` + `RefreshSameCommand` already purge the self URI tag on writes to `#[Cacheable]` resources.
- **Mixing `#[Embed]` and `fromAssoc()` on the same response** — harmless as of the current `CacheDependency::depends()` (it merges into whatever `Header::SURROGATE_KEY` is already set; there is no early-return), but a hand-built header next to the embeds makes the resource's actual dependency set unclear to read. `#[Embed]` with `#[DependsOn]` is fine: both are declarations.
- **Reaching for `fromAssoc()` when there is no cross-resource dependency** — the default `#[Cacheable]`-only leaf is the correct shape.
- **A shared string key or an interceptor-wide key** — `latest_*` keys, or one key an interceptor adds to every page. The dependency it encodes belongs in a declaration (Shape B or C) on the resources that actually read the data; a key on every page purges pages that never read it.
