---
user-invocable: true
name: bear-hypermedia
description: Add #[Link] attributes to resource classes. Use when improving API design.
---

# BEAR.Sunday Hypermedia Implementation Skill

## Purpose

Add `#[Link]` to resource classes to declare navigable actions.

For workflow tests based on hypermedia links, use `/bear-smoke-test`.

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
