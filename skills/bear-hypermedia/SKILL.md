---
user-invocable: true
name: bear-hypermedia
description: >-
  Add #[Link] attributes to resource classes. Use when user says "add links",
  "ハイパーメディア", "HATEOAS", "API transitions", or asks to add hypermedia
  links or improve API design.
---

# BEAR.Sunday Hypermedia Implementation Skill

## Purpose

Add `#[Link]` to resource classes to declare navigable actions.

Workflow tests are selected from ALPS tags and implemented as hypermedia
stories. Smoke tests check endpoint liveness; hypermedia tests execute a user
story across linked resources.

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

## ALPS Tags Before Workflow Tests

Before designing hypermedia workflow tests, inspect the ALPS profile. If the
project has `docs/tag.md`, read it first and follow that tag taxonomy.

- Add or verify `tag` on State (Taxonomy) descriptors and Transition
  (Choreography) descriptors.
- Do not add workflow-selection tags to atomic Ontology descriptors such as
  `id`, `title`, or `email`.
- Keep `tag` as an ASD-compatible space-delimited string, not a JSON array.
- Use `flow-*` tags for user journeys, such as `flow-browse` or
  `flow-publish`.
- Use `actor-*` tags for the user role, such as `actor-reader` or
  `actor-editor`.
- Treat `flow-browse actor-reader` as a reader story candidate and
  `flow-publish actor-editor` as an editor story candidate.

If the tags are missing or unclear, update and validate the ALPS profile with
the `bear-to-alps` skill before adding workflow tests.

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
| onPatch | idempotent transition |

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

After generation, use the `bear-to-alps` skill to:
- Validate: `asd --validate profile.json`
- Generate diagrams: `asd profile.json`
- Get improvement suggestions

## References

- [BEAR.Sunday Resource](https://bearsunday.github.io/manuals/1.0/en/resource.html)
- [BEAR.Sunday Testing](https://bearsunday.github.io/manuals/1.0/en/test.html)
- [ALPS Specification](https://alps-io.github.io/spec/)
