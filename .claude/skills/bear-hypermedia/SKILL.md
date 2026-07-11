---
user-invocable: true
name: bear-hypermedia
description: >-
  Add #[Link] attributes to resource classes. Use when user says "add links",
  "ハイパーメディア", "HATEOAS", "API transitions", "workflow test",
  "ワークフローテスト", "hypermedia story", "flow tags", or asks to add
  hypermedia links or add state transitions to responses.
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

**Rel naming on clean-style / ALPS projects:** name rels with `go*`/`do*`
choreography IDs (`goEdit`, `doDelete`) instead of the plain rels above;
`bear-to-alps` "Add Attributes" mode renames plain rels to these IDs. Also,
on clean-style projects PUT/DELETE transitions are usually invoked directly
by HTTP method rather than advertised as `_links`.

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

## Write Workflow Tests

Implement each selected story (one per `flow-*` tag) as one test class in
`tests/Hypermedia/`. Chain the steps with `#[Depends]`: each step fetches a
resource, asserts the expected `_links` rel exists, and follows its `href` to
the next step. Smoke tests (`bear-smoke-test`) remain the liveness layer;
these tests verify the story, not each endpoint.

```php
namespace MyVendor\MyProject\Hypermedia;

use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use MyVendor\MyProject\Injector;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

class BrowseWorkflowTest extends TestCase // one class per flow-* story
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $this->resource = Injector::getInstance('app')->getInstance(ResourceInterface::class);
    }

    public function testIndex(): ResourceObject
    {
        $index = $this->resource->get('/index');
        $this->assertSame(200, $index->code);

        return $index;
    }

    #[Depends('testIndex')]
    public function testFollowArticles(ResourceObject $response): ResourceObject
    {
        $links = json_decode((string) $response)->_links;
        $this->assertTrue(isset($links->articles));
        $articles = $this->resource->get($links->articles->href);
        $this->assertSame(200, $articles->code);

        return $articles;
    }
}
```

## Generate ALPS from Resource Classes

Generating an ALPS profile from resource classes (mapping resource → state,
`#[Link]`/`#[Embed]` → transitions/embedded references, method arguments →
semantic descriptors, with `go*`/`do*` choreography ID inference) is owned by
the **`bear-to-alps`** skill. Use `bear-to-alps` for the full extraction,
validation (`asd --validate`), and diagram generation (`asd`), and to add
`#[Alps]` attributes to the resources.

This skill focuses on adding `#[Link]` attributes and the hypermedia workflow
tests that follow them.

## References

- [BEAR.Sunday Resource](https://bearsunday.github.io/manuals/1.0/en/resource.html)
- [BEAR.Sunday Testing](https://bearsunday.github.io/manuals/1.0/en/test.html)
- [ALPS Specification](https://alps-io.github.io/spec/)
