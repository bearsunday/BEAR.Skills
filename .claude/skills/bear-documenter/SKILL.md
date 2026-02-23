---
user-invocable: true
name: bear-documenter
description: Auto-generate PHPDoc comments for constant classes and resource classes in BEAR.Sunday projects. Infers intent from names, values, and context, and assigns comments with confidence levels.
---

# Auto Documentation Generation Skill

## Overview

Auto-generate PHPDoc comments for constant classes and resource classes. Infers intent from names, values, and context, and assigns comments with confidence levels.

## Common Flow

### Checking @todo Markers

Before generating comments, explain the following and ask for a choice:

---

**Would you like to add @todo markers?**

Adding `@todo needs-review(confidence X):` markers will:

- **Appear in the IDE's TODO list** — Review unreviewed comments at a glance
- **Make confidence levels explicit** — Prioritize reviewing low-confidence comments
- **Prevent review oversights** — Remaining markers = unreviewed

Without markers:

- Only comments are generated
- You need to review everything yourself
- Recommended for high-confidence targets

**Options:**
- **Yes**: Add `@todo needs-review(confidence X):` to all comments
- **No**: Generate comments only

---

### Sample Run

Select one file, generate comments, and display the results:

**Would you like to continue with this style?**

- **Yes**: Apply to remaining files
- **Needs adjustment**: Adjust the style before continuing
- **Cancel**: Stop after this file only

### Post-Review Work

After completing the review, remove the `@todo needs-review(confidence X):` portions and keep only the comments.

### Cases Where Comments Are Not Generated

- Targets that already have PHPDoc comments (no overwriting)
- Clearly deprecated code
- Test code

## 1. Constant Documentation

Auto-generate PHPDoc comments for constant classes. Infer intent from constant names, values, and context.

### Generation Examples

#### With @todo Markers

```php
<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * @todo needs-review(confidence high): Constants for HTML meta tags
 *
 * Defines title and description templates for SEO/OGP.
 */
final class MetaTag
{
    /** @todo needs-review(confidence high): Common title suffix for all pages */
    public const TITLE_DEFAULT_SUFFIX = '｜Web eclat（ウェブエクラ）';

    /** @todo needs-review(confidence high): OGP (Open Graph Protocol) property identifier */
    public const PROPERTY_OGP = 'ogp';

    /** @todo needs-review(confidence low): Aggregation threshold? Needs investigation */
    public const THRESHOLD = 100;
}
```

#### Without @todo Markers

```php
/**
 * Constants for HTML meta tags
 *
 * Defines title and description templates for SEO/OGP.
 */
final class MetaTag
{
    /** Common title suffix for all pages */
    public const TITLE_DEFAULT_SUFFIX = '｜Web eclat（ウェブエクラ）';
}
```

### Confidence Criteria (Constants)

#### Confidence: High

- Constant name is clear and intent is readable
  - `TITLE_PREFIX_*`, `DESCRIPTION_*`, `MAX_*_COUNT`
- Value is self-explanatory
  - `'ogp'`, `'twitter'`, `'draft'`, `'published'`
- Constant name and value match or correspond
  - `STATUS_DRAFT = 'draft'`
- Industry-standard terminology
  - `OGP`, `TTL`, `HTTP_*`

#### Confidence: Medium

- Domain-specific terms but inferable from context
  - `HANAGUMI`, `JMADAM` (site-specific but purpose is clear)
- Abbreviations but common
  - `API_URL`, `DB_HOST`
- Intent is readable from array structure

#### Confidence: Low

- Magic numbers with unclear intent
  - `100`, `3600`, `256`
- Abbreviations only with no context
  - `TH`, `CT`, `FLG`
- Multiple interpretations possible
  - `LIMIT` (count? size? time?)
- Cannot determine without checking usage locations

### Constant Name Inference Patterns

| Pattern | Inference |
|---------|-----------|
| `*_URL`, `*_ENDPOINT` | URL endpoint |
| `*_TIMEOUT`, `*_TTL` | Time setting (check seconds/milliseconds) |
| `*_LIMIT`, `*_MAX`, `*_MIN` | Limit value |
| `*_PREFIX`, `*_SUFFIX` | String prefix/suffix |
| `STATUS_*`, `STATE_*` | Status value |
| `TYPE_*`, `KIND_*` | Type identifier |
| `DEFAULT_*` | Default value |
| `ENABLE_*`, `DISABLE_*` | Flag |

### Value Inference Patterns

| Pattern | Inference |
|---------|-----------|
| `3600`, `86400` | Time in seconds (1 hour, 1 day) |
| `1024`, `2048` | Byte size (KB, MB) |
| `200`, `404`, `500` | HTTP status code |
| Japanese strings | UI display labels, SEO text |
| URL format | External service endpoint |

### Post-Review Search

```bash
# Search for unreviewed constants
grep -r "@todo needs-review" src/Constants/

# Search for low confidence only
grep -r "confidence low" src/Constants/
```

## 2. Resource Documentation

Auto-generate PHPDoc comments for resource classes. Infer intent from class name, HTTP methods, and parameters.

### Generation Examples

#### With @todo Markers

```php
<?php

declare(strict_types=1);

namespace App\Resource\App;

use BEAR\Resource\ResourceObject;

/**
 * @todo needs-review(confidence high): Article resource
 *
 * Provides retrieval, creation, update, and deletion of articles.
 */
class Article extends ResourceObject
{
    /**
     * @todo needs-review(confidence high): Retrieve an article
     *
     * @param int $id Article ID
     * @return static
     */
    public function onGet(int $id): static
    {
        // ...
    }

    /**
     * @todo needs-review(confidence high): Create an article
     *
     * @param string $title Title
     * @param string $body Body
     * @return static 201 Created
     */
    public function onPost(string $title, string $body): static
    {
        // ...
    }

    /**
     * @todo needs-review(confidence high): Delete an article
     *
     * @param int $id Article ID
     * @return static 204 No Content
     */
    public function onDelete(int $id): static
    {
        // ...
    }
}
```

### Confidence Criteria (Resources)

#### Confidence: High

- Class name is a clear noun
  - `Article`, `User`, `Order`, `Product`
- Standard CRUD pattern
  - `onGet($id)` — Single item retrieval
  - `onGet()` — List retrieval
  - `onPost(...)` — Creation
  - `onPut($id, ...)` — Update
  - `onDelete($id)` — Deletion
- Parameter names are self-explanatory
  - `$id`, `$title`, `$body`, `$email`

#### Confidence: Medium

- Class name is domain-specific
  - `Hanagumi`, `Flagshop`
- Compound operations
  - `onPost` also performs updates
- Many parameters (5 or more)

#### Confidence: Low

- Class name is abbreviated
  - `Art`, `Usr`, `Ord`
- Non-standard method patterns
  - `onGet` has side effects
- Parameter intent is unclear
  - `$data`, `$params`, `$options`
- Complex business logic

### Inference from Class Name

| Pattern | Inference |
|---------|-----------|
| `Article`, `Post`, `Blog` | Article/post resource |
| `User`, `Member`, `Account` | User resource |
| `Order`, `Purchase` | Order resource |
| `Product`, `Item` | Product resource |
| `Category`, `Tag` | Classification resource |
| `Comment`, `Review` | Comment/review resource |
| `*List`, `*Index` | List resource |
| `*Detail` | Detail resource |

### Inference from Methods

| Method | Parameters | Inference |
|--------|------------|-----------|
| `onGet` | `int $id` | Single item retrieval |
| `onGet` | None or pagination | List retrieval |
| `onGet` | Search criteria | Search/filter |
| `onPost` | Creation data | Create new (201) |
| `onPut` | `$id` + data | Full update (200) |
| `onPatch` | `$id` + partial data | Partial update (200) |
| `onDelete` | `int $id` | Delete (204) |

### Inference from Parameter Names

| Parameter | Inference |
|-----------|-----------|
| `$id`, `$articleId` | Resource identifier |
| `$title`, `$name` | Name |
| `$body`, `$content` | Body text |
| `$email`, `$phone` | Contact information |
| `$page`, `$limit`, `$offset` | Pagination |
| `$sort`, `$order` | Sort |
| `$q`, `$keyword`, `$search` | Search keyword |

### Inference from Attributes

| Attribute | Additional Information |
|-----------|----------------------|
| `#[Embed]` | Has embedded resources |
| `#[Link]` | Links to related resources |
| `#[Cacheable]` | Cacheable |
| `#[JsonSchema]` | Has input validation |

### Post-Review Search

```bash
# Search for unreviewed resources
grep -r "@todo needs-review" src/Resource/

# Search for low confidence only
grep -r "confidence low" src/Resource/
```

## Workflow

1. Specify target files
2. Choose whether to add @todo markers (present the explanation above)
3. Analyze code and generate comments
4. Determine and assign confidence levels
5. Apply to files
6. Format with `composer cs-fix`
