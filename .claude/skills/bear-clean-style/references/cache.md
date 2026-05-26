# `#[Cacheable]` and cross-resource invalidation

`#[Cacheable]` resources canonicalize two patterns. Anything else is an anti-pattern.

## Default — user-zero-code leaf

A read resource that does not aggregate other resources needs only `#[Cacheable]`. The framework writes the self URI tag and `RefreshSameCommand` purges it on `PUT` / `POST` / `PATCH` / `DELETE` to the same URI. **Do not** touch `Header::SURROGATE_KEY`, inject `UriTagInterface`, or call `DonutRepositoryInterface::invalidateTags()` on a leaf resource. Each duplicates framework behaviour and breaks the `CacheDependency::depends()` assertion that forbids mixing manual and automatic Surrogate-Key writes on the same response.

## Cross-resource — two shapes only

Pick by whether the dependency set is statically expressible at declaration time.

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

### Shape B — explicit `fromAssoc` (dynamic / body-derived)

When the dependency set is N URIs whose count or parameters come from the database, `#[Embed]` cannot statically express it. Read the rows and map them through `UriTagInterface::fromAssoc('<template>', $assocList)`, assigning the result to `Header::SURROGATE_KEY`.

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

Pick A whenever the dependency set is `#[Embed]`-expressible. Reach for B only when the count or parameters are body-derived.

## Anti-patterns

The scanner emits findings for each of these.

- **Writing the self URI into `Header::SURROGATE_KEY`** — the framework's `SurrogateKeys::setSurrogateHeader` already does this.
- **Calling `DonutRepositoryInterface::invalidateTags()` from `onPut` / `onDelete`** — `CommandInterceptor` + `RefreshSameCommand` already purge the self URI tag on writes to `#[Cacheable]` resources.
- **Mixing `#[Embed]` and `fromAssoc()` on the same response** — assigning `Header::SURROGATE_KEY` manually short-circuits the body-walk auto-merge (`setCacheDependency` early-returns when the header is already set), so the embed's child tags are silently dropped unless you include them in your `fromAssoc` list yourself. Pick one shape per resource — A or B, never both.
- **Reaching for `fromAssoc()` when there is no cross-resource dependency** — the default `#[Cacheable]`-only leaf is the correct shape.
