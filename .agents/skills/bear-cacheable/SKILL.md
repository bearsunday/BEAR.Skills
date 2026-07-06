---
user-invocable: true
name: bear-cacheable
description: Scan resource classes and add cache attributes. Detect resources without cache declarations and apply appropriate attributes. Use when user says "add cache", "キャッシュを設定", "Cacheable", "optimize caching", or asks to configure cache attributes for resources.
---

# BEAR.Sunday Cache Attribute Addition Skill

## Purpose

Detect resources without cache declarations and add appropriate cache attributes.

## Procedure

### 1. Detect Resources Without Cache Declarations

```bash
# Find files that have onGet but no Cacheable
grep -rl "function onGet" src/Resource | xargs grep -L "Cacheable"
```

### 2. Classify Each Resource

Read each resource and classify it:

| Class | Characteristics | Cache strategy |
|-------|----------------|----------------|
| **Content API** | Data retrieval/display; same input → same output (Article, Page, Category, Tag) | `#[Cacheable]` (leaf), `#[DonutCache]` (partially dynamic), `#[CacheableResponse]` (CDN/browser) |
| **Computation API** | Real-time, user-specific, aggregation, or known update frequency (Stock, Cart, Analytics, Ranking) | `#[Cacheable(expirySecond: N)]` (short-lived) or no attribute |
| **Write operation** | POST/PUT/DELETE | Not cacheable |

### 3. Apply Cache Attributes

```php
use BEAR\RepositoryModule\Annotation\Cacheable;

// Content API: clear dependencies, not time-dependent
#[Cacheable]
public function onGet(int $id): static

// Computation API: known update frequency
#[Cacheable(expirySecond: 300)]
public function onGet(): static

// Not cacheable: state the reason in a comment (there is no #[NoCache] attribute)
/** @note Not cacheable: Depends on user session */
public function onGet(): static
```

## Decision Flow

```text
Read the resource class
    |
Write operation (POST/PUT/DELETE)? --Yes--> Not cacheable
    | No
User-specific / session-dependent? --Yes--> Not cacheable
    | No
Clear dependencies (input params only, not time/external-state)?
    | --Yes--> #[Cacheable]  (use #[DonutCache] if partially dynamic,
    |                        #[CacheableResponse] for CDN/browser)
    No
Acceptable staleness is known? --Yes--> #[Cacheable(expirySecond: N)]
    |
    No--> Not cacheable (state the reason in a comment)
```

## Cache Strategies

### `#[Cacheable]` — clear dependencies (leaf)

For resources whose output depends only on input parameters and is not time- or external-state-dependent. Embed dependencies are auto-tracked.

```php
#[Cacheable]
#[Embed(rel: 'author', src: 'app://self/user{?id}')]
public function onGet(int $id): static
```

### `#[DonutCache]` — partially dynamic pages

Most of the page is cacheable, but a part is dynamic (user menu, etc.).

```php
#[DonutCache]
#[Embed(rel: 'article', src: 'app://self/article{?id}')]   // cached
#[Embed(rel: 'sidebar', src: 'app://self/sidebar')]         // cached
#[Embed(rel: 'user_menu', src: 'app://self/user/menu')]     // dynamic (hole)
public function onGet(int $id): static
```

### `#[CacheableResponse]` — CDN/browser cache

HTTP response-level cache (instructs CDN and browsers).

```php
#[CacheableResponse(maxAge: 3600, sMaxAge: 86400)]
public function onGet(int $id): static
// Cache-Control: max-age=3600, s-maxage=86400
```

### `#[Cacheable(expirySecond: N)]` — known TTL

Even computation APIs can be cached if the update frequency is known.

| Resource | Acceptable delay | TTL |
|----------|------------------|-----|
| Ranking | 5 min | 300 |
| Exchange rate | 1 min | 60 |
| Weather | 10 min | 600 |
| Stock count | 30 sec | 30 |
| News list | 1 min | 60 |

**TTL questions:** How many seconds old can this data be and still be acceptable? How frequently is it updated? Will users notice stale data?

### No cache — truly unpredictable

No attribute, with a comment stating the reason:

```php
/** @note Not cacheable: real-time chat data */
public function onGet(): static
```

**Conditions for no cache:** depends on user session; real-time data is absolutely required (chat); write operations.

## Resources Without Cache Declarations = Problem

Having no cache attribute means "cache consideration was missed." All GET resources should explicitly declare a cache strategy.

**Review checklist:**
- Does the `onGet` method have a cache attribute?
- If not, is the reason explicitly stated in a comment?

## Cache Invalidation

| Attribute | Timing | Behavior |
|-----------|--------|----------|
| `#[Purge]` | On PUT/DELETE | Delete cache |
| `#[Refresh]` | On PUT | Regenerate and update |

## Cross-resource Cache Dependencies

When a `#[Cacheable]` resource aggregates other resources, the dependency set
must be expressed in one of two shapes. **Never mix them on the same response.**

| Shape | When | How |
|-------|------|-----|
| **A — `#[Embed]` only** | The parent composes exactly the children it depends on via `#[Embed]` | Just `#[Cacheable]`; `QueryRepository::setCacheDependency` auto-merges child Surrogate-Key tags. No manual `Header::SURROGATE_KEY`. |
| **B — explicit `fromAssoc`** | The dependency set is N URIs whose count/parameters come from the database (not `#[Embed]`-expressible) | Read rows, map via `UriTagInterface::fromAssoc('<template>', $assocList)`, assign to `Header::SURROGATE_KEY`. Leave unset when the list is empty. |

### Anti-patterns

- Writing the self URI into `Header::SURROGATE_KEY` (the framework already does this).
- Calling `DonutRepositoryInterface::invalidateTags()` from `onPut`/`onDelete`
  (`CommandInterceptor` + `RefreshSameCommand` already purge the self URI tag).
- Mixing `#[Embed]` and `fromAssoc()` on the same response — assigning
  `Header::SURROGATE_KEY` manually short-circuits the body-walk auto-merge and
  silently drops embed child tags. Pick A **or** B, never both.

For the full clean-style cache conventions (Shape A/B, anti-pattern scanner
checks), see `bear-clean-style/references/cache.md`.

## Output Example

```markdown
## Cache Strategy Report

### Content APIs (Recommend applying #[Cacheable])
- src/Resource/App/Article.php
- src/Resource/App/Category.php
- src/Resource/Page/Index.php
- src/Resource/Page/Article.php

### Computation APIs (No cache or short-lived)
- src/Resource/App/Cart.php - User-specific
- src/Resource/App/Search.php - Diverse parameters
- src/Resource/App/Ranking.php - Recommend #[Cacheable(expirySecond: 300)]

### Write APIs (Not cacheable)
- src/Resource/App/Article.php (onPost, onPut, onDelete)
```

## References

- [BEAR.Sunday Cache](https://bearsunday.github.io/manuals/1.0/en/cache.html)
