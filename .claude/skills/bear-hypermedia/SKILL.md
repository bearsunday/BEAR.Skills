---
user-invocable: true
name: bear-hypermedia
description: Add #[Link] to resource classes and express use cases through HyperMedia tests. Use when improving API design.
---

# BEAR.Sunday Hypermedia Implementation Skill

## Purpose

1. Add `#[Link]` to resource classes to declare navigable actions
2. Express use cases (workflows) through HyperMedia tests

## Procedure

### 1. Analyze Resource Classes

Read the resource and identify possible actions:

- Can be edited -> `rel: 'edit'`
- Can be deleted -> `rel: 'delete'`
- Has details -> `rel: 'item'`
- Can return to list -> `rel: 'collection'`
- Has next/previous -> `rel: 'next'` / `rel: 'prev'`

### 2. Add #[Link]

```php
use BEAR\Resource\Annotation\Link;

#[Link(rel: 'edit', href: '/article/{id}/edit')]
#[Link(rel: 'delete', href: '/article/{id}', method: 'delete')]
#[Link(rel: 'comments', href: '/article/{id}/comments')]
public function onGet(int $id): static
```

### 3. Implement HyperMedia Tests

Express use cases as tests:

```php
/**
 * Article edit workflow
 *
 * [Article List] --item--> [Article Detail] --edit--> [Edit] --update--> [Article Detail]
 */
public function testArticleEditWorkflow(): void
{
    // Get article list
    $articles = $this->resource->get('app://self/articles');

    // Navigate to the first article's detail
    $article = $this->resource->href('item', $articles);
    $this->assertSame(200, $article->code);

    // Navigate to edit
    $edit = $this->resource->href('edit', $article);
    $this->assertSame(200, $edit->code);

    // Execute update
    $updated = $this->resource->href('update', $edit, ['title' => 'New Title']);
    $this->assertSame(200, $updated->code);
}
```

## Use Case Examples

### Article Management

```text
[Article List] --item--> [Article Detail] --edit--> [Edit Form] --update--> [Article Detail]
                              |
                              +--delete--> [Article List]
                              |
                              +--comments--> [Comment List]
```

### User Registration

```text
[Top] --signup--> [Registration Form] --create--> [Confirmation] --verify--> [Complete]
```

## Generate ALPS from Resource Classes

Analyze resource classes to generate ALPS profiles.

### Mapping

| BEAR.Sunday | ALPS |
|-------------|------|
| Resource class | State |
| `#[Link(rel, href)]` | Transition |
| `#[Embed(rel, src)]` | Embedded state |
| Method arguments | Semantic descriptor |
| onGet | safe transition |
| onPost | unsafe transition |
| onPut/onDelete | idempotent transition |

### Generation Steps

1. Read the resource class
2. Class name -> State ID
3. `#[Link]` -> Transition (determine go/do from rel)
4. `#[Embed]` -> Embedded reference
5. Arguments -> Semantic descriptor
6. Generate and validate with the ALPS skill

### Example: ALPS from Article Resource

```php
// Resource
#[Link(rel: 'edit', href: '/article/{id}/edit')]
#[Link(rel: 'delete', href: '/article/{id}', method: 'delete')]
#[Embed(rel: 'author', src: 'app://self/user{?id}')]
#[Embed(rel: 'comments', src: 'app://self/article/{id}/comments')]
public function onGet(int $id): static
```

Generated:

```json
{
  "id": "ArticleDetail",
  "title": "Article Detail",
  "descriptor": [
    {"href": "#articleId"},
    {"href": "#Author"},
    {"href": "#Comments"},
    {"href": "#goEdit"},
    {"href": "#doDelete"}
  ]
}
```

### Integration with ALPS Skill

After generation, use the `/alps` skill to:
- Validate: `asd --validate profile.json`
- Generate diagrams: `asd profile.json`
- Get improvement suggestions

## References

- [BEAR.Sunday Resource](https://bearsunday.github.io/manuals/1.0/en/resource.html)
- [BEAR.Sunday Testing](https://bearsunday.github.io/manuals/1.0/en/test.html)
- [ALPS Specification](http://alps.io/spec/)
