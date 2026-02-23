---
user-invocable: true
name: bear-cacheable
description: Scan resource classes and add cache attributes. Detect resources without cache declarations and apply appropriate attributes.
---

# BEAR.Sunday Cache Attribute Addition Skill

## Purpose

Detect resources without cache declarations and add appropriate cache attributes.

## Execution Steps

### 1. Detect Resources Without Cache Declarations

```bash
# Find files that have onGet but no Cacheable
grep -rl "function onGet" src/Resource | xargs grep -L "Cacheable"
```

### 2. Classify Each Resource

Read the resource and determine:
- Content API — `#[Cacheable]`
- Computation API — `#[Cacheable(expirySecond: N)]`
- Not cacheable — State the reason in a comment

### 3. Add Cache Attributes

```php
use BEAR\RepositoryModule\Annotation\Cacheable;

#[Cacheable]
public function onGet(int $id): static
```

## Resource Classification

### Content API (Cacheable)

Primary purpose is data retrieval and display. Same input produces same output.

| Characteristics | Examples |
|----------------|----------|
| Articles, pages | Article, Page, Post |
| List display | Articles, List, Index |
| Master data | Category, Tag, User |
| Static content | About, Help, Guide |

**Applicable attributes:**
```php
#[Cacheable]
#[CacheableResponse(maxAge: 3600)]
#[DonutCache]  // Partial caching
```

### Computation API (Not cacheable / Short-lived)

Requires real-time data or has side effects.

| Characteristics | Examples |
|----------------|----------|
| Real-time data | Stock, Rate, Weather |
| User-specific | Cart, Session, Preference |
| Aggregation/calculation | Analytics, Report, Stats |
| Write operations | POST/PUT/DELETE |

**Applicable attributes:**
```php
#[Cacheable(expirySecond: 60)]  // Short-lived cache
// Or no attribute (no caching)
```

## Decision Flow

```text
Read the resource class
    |
onGet only? --No--> Do not cache (write operation)
    | Yes
Depends on external API? --Yes--> Short-lived cache or no cache
    | No
User-specific? --Yes--> No cache or Vary: Cookie
    | No
Time-dependent? --Yes--> Short-lived cache
    | No
Content API --> Apply #[Cacheable]
```

## Application Steps

### 1. Scan and Classify Resources

```php
// Classification results
$contentApis = [
    'App\Resource\App\Article',      // Article
    'App\Resource\App\Category',     // Category
    'App\Resource\Page\Index',       // Top page
];

$computationApis = [
    'App\Resource\App\Cart',         // Cart (user-specific)
    'App\Resource\App\Search',       // Search (diverse parameters)
    'App\Resource\App\Analytics',    // Analytics (real-time)
];
```

### 2. Add Cache Attributes to Content APIs

```php
use BEAR\RepositoryModule\Annotation\Cacheable;

#[Cacheable]
class Article extends ResourceObject
{
    public function onGet(int $id): static
}
```

### 3. Short-lived or No Cache for Computation APIs

```php
// Short-lived cache (60 seconds)
#[Cacheable(expirySecond: 60)]
class Ranking extends ResourceObject

// No cache (no attribute)
class Cart extends ResourceObject
```

## Cache Strategy Selection

### #[Cacheable] - Resources with Clear Dependencies

Used for resources with high predictability and obvious dependency relationships.

```php
// Applicable: Clear dependency (depends only on article ID)
#[Cacheable]
public function onGet(int $id): static

// Applicable: Auto-invalidation via ETag
#[Cacheable]
#[Embed(rel: 'author', src: 'app://self/user{?id}')]
public function onGet(int $id): static
```

**Application conditions:**
- Depends only on input parameters
- Not time-dependent
- Not dependent on external state
- Embed dependencies are also auto-tracked

### #[DonutCache] - Partially Dynamic Pages

Most of the page is cacheable, but some parts are dynamic (user information, etc.).

```php
// Page resource: Donut cache the entire page
#[DonutCache]
#[Embed(rel: 'article', src: 'app://self/article{?id}')]      // Cached
#[Embed(rel: 'sidebar', src: 'app://self/sidebar')]           // Cached
#[Embed(rel: 'user_menu', src: 'app://self/user/menu')]       // Dynamic (hole)
public function onGet(int $id): static
```

```text
+-----------------------------+
|  Header (cached)            |
+-----------------------------+
|  Article body (cached)      |
|                             |
|  +---------------------+   |
|  | User menu            |   |  <-- Donut hole (dynamic)
|  | (fetched every time) |   |
|  +---------------------+   |
|                             |
|  Sidebar (cached)           |
+-----------------------------+
```

### #[CacheableResponse] - CDN/Browser Cache

Cache at the HTTP response level. Instructs CDN and browsers.

```php
#[CacheableResponse(maxAge: 3600, sMaxAge: 86400)]
public function onGet(int $id): static
// Cache-Control: max-age=3600, s-maxage=86400
```

### #[Cacheable(expirySecond: N)] - Computation APIs with Known TTL

Even computation APIs can be cached if the update frequency is known.

```php
// Ranking: Updating every 5 minutes is sufficient
#[Cacheable(expirySecond: 300)]
public function onGet(): static

// Exchange rate: 1 minute is sufficient
#[Cacheable(expirySecond: 60)]
public function onGet(string $currency): static

// Weather: 10 minutes is sufficient
#[Cacheable(expirySecond: 600)]
public function onGet(string $city): static
```

**Questions for determining TTL:**
- How many seconds old can this data be and still be acceptable?
- How frequently is it updated?
- Will users notice stale data?

| Resource | Acceptable Delay | TTL Example |
|----------|-----------------|-------------|
| Ranking | 5 min | 300 |
| Exchange rate | 1 min | 60 |
| Weather | 10 min | 600 |
| Stock count | 30 sec | 30 |
| News list | 1 min | 60 |

### No Cache - Truly Unpredictable Resources

```php
// No cache: User-specific, session-dependent
public function onGet(): static  // No attribute
```

**Conditions for no cache:**
- Depends on user session
- Real-time data is absolutely required (chat, etc.)
- Write operations (POST/PUT/DELETE)

### Resources Without Cache Declarations = Problem

Having no cache attribute means "cache consideration was missed." All GET resources should explicitly declare a cache strategy.

```php
// Bad: No cache declaration (oversight)
public function onGet(int $id): static

// Good: Explicitly cached
#[Cacheable]
public function onGet(int $id): static

// Good: Explicit TTL
#[Cacheable(expirySecond: 300)]
public function onGet(): static

// Good: If not cacheable, state explicitly with #[NoCache] or a comment
/** @note Not cacheable: Depends on user session */
public function onGet(): static
```

**Review checklist:**
- Does the onGet method have a cache attribute?
- If not, is the reason explicitly stated?

## Decision Matrix

| Condition | Cache Strategy |
|-----------|---------------|
| Clear dependencies + not time-dependent | `#[Cacheable]` |
| Page is mostly static, partially dynamic | `#[DonutCache]` |
| Cache at CDN/browser | `#[CacheableResponse]` |
| Acceptable delay is known | `#[Cacheable(expirySecond: N)]` |
| User-specific / session-dependent | No cache |

## Strategy Selection Flow

```text
Analyze the resource
    |
Write operation? --Yes--> Not cacheable
    | No
User-specific? --Yes--> Not cacheable
    | No
Clear dependencies? --Yes--> #[Cacheable]
    | No
Acceptable delay? --Yes--> #[Cacheable(expirySecond: N)]
    | No
Not cacheable
```

## Cache Invalidation

| Attribute | Timing | Behavior |
|-----------|--------|----------|
| `#[Purge]` | On PUT/DELETE | Delete cache |
| `#[Refresh]` | On PUT | Regenerate and update |

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
