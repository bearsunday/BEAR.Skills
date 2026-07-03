# Hypermedia — `#[Embed]`, `#[Link]`, and workflow tests

## Reachability (premise)

Before choosing an embed, decide **where the information lives**. The prior axis is 到達可能性 (reachability): information must be operable in the App context.

- Domain information that should exist on the App surface is defined as an App resource (`app://`). The Page **references** it, never owns it. Being an App resource means it is reachable from ALL of: HAL API, CLI (`#[Cli]`), `#[Embed]`, `#[Link]`, `#[Cacheable]`, JSON Schema, ALPS.
- The context is one-directional: Page → App may reference; App → Page does not exist. Information placed in the Page is trapped on the HTML island and invisible from the App context.
- The decision is one question: **"should this information exist on the App surface?"** Yes → App, No → Page. Even at 1:1 the Page reads the App — not to avoid duplication, but to keep the information resident in the App context.
- **Hole (severe, forbidden):** the Page alone assembles information with no corresponding `app://` — a reachability hole, invisible from API/CLI/Embed.
- **Duplication (minor):** the Page re-assembles information that exists in App — hurts DRY but preserves reachability; lean toward referencing.
- Only pure presentation derivatives may be Page-owned: `bodyHtml`, CSRF token, form display state, empty-list messages, auth toggles, not-found guards.

App representation is bound from the outside by context: an App resource holds **state** only; its representation is bound by the outer context (HTML templates under `templates/App/*` in an HTML context, JSON via HalRenderer in an API context). The App is indifferent to its own representation.

## Choosing the embed kind

- **Show the child representation as-is → normal embed** (base form). The App holds an HTML template used in the display; the child enters under the `{rel}` namespace, so `{child.name}` keeps a DTO-like unit of meaning consistent across body, template, and HAL output.
- **Fuse several children's data into one integrated view the Page lays out itself → self embed** (`rel: _self`): flatten the child body into the parent top-level and discard the child representation.
- A resource embedded as `_self` MUST use `#[Cacheable]` (value cache). `#[CacheableResponse]`/`#[DonutCache]` restore only the view on a cache hit, not the body, so `linkSelf` (which reads body) breaks — the framework enforces this with a domain exception.
- Self-embed flat-merge collides on multiple children (both `user.name` and `contact.name` become `name`) and loses provenance. The namespaced normal embed is safer; prefer it unless the Page genuinely needs one fused view.

## `#[Embed]` vs `#[Link]`

`#[Embed]` is only a GET representation-composition concern. Do not apply it to POST/PUT/DELETE workflows.

Use `#[Embed]` when:

- an `onGet` response includes related resource representations;
- the rel is a taxonomy noun such as `author`, `category`, or `tagList`;
- the target URI can be declared and filled with `addQuery()`.

Do not force `#[Embed]` when:

- ResourceClient/resource calls are used for transient orchestration, validation, authorization, or write workflow decisions;
- data is fetched only to branch or compute status and is not part of the final GET representation;
- the composition is body-derived variable-length dependency tracking that needs explicit cache tags (see [cache.md](cache.md) — that is Shape B territory).

Keep HAL rel layers separate:

- `#[Link]` rels come from ALPS choreography transitions: `goArticleList`, `goAuthor`, `doCreateArticle`.
- `#[Embed]` rels come from ALPS taxonomy nouns: `author`, `category`, `tagList`.
- Avoid `#[Embed(rel: 'goAuthor')]`; `go*` names are transitions, not embedded taxonomy instances.

## Hypermedia workflow tests

A workflow test in `tests/Hypermedia/` is a user story told by linking small steps with `#[Depends]` — the `ResourceObject` returned by one step is the input the next step follows a rel from. **One file per story.** Class name is `<Actor><Verb>Test` (e.g. `ReaderBrowsesByTagTest`, `EditorManagesArticleTest`); method names are the steps in third-person narrative present, so PHPUnit's testdox output reads top-to-bottom as the story.

```text
Editor Manages Article (MyVendor\Cms\Hypermedia\EditorManagesArticle)
 ✔ Creates an article
 ✔ Reads back the new article
 ✔ Revises the article
 ✔ Retires the article
```

### Rules

1. **One file per story.** Contract pins such as the HAL envelope shape live in their own `*ContractTest` class, not in stories.
2. **Exactly one hard-coded URI per story — the entry point.** Every subsequent transition goes through `ResourceInterface::href($rel, $vars, $ro)`, which reads `#[Link]` off the source resource. Renaming an ALPS Choreography transition will break the chain and surface here.
3. **One step per `#[Depends]`-linked test method.** First test performs the entry GET/POST and returns the `ResourceObject`; each follow-up declares `#[Depends('previousStep')]` and receives that object as its first parameter.
4. **Pass the specific id explicitly when crossing entities.** `Anchor::href()` merges the source body into the URI Template, so `follow($article, 'goAuthor')` looks correct but silently feeds the article's `id` into the author slot when every resource exposes `id` as its primary key. Always write `follow($article, 'goAuthor', ['id' => $article->body['authorId']])` so the cross-entity flow is visible at the call site.
5. **Per-step shape validation belongs to `#[JsonSchema]`, not workflow tests.** Workflow tests assert status codes, rel chains, and business invariants (an edit must be visible to the next read). Field-level shape checks belong in resource smoke tests or the schema attribute.
6. **Canonical lifecycle for write-capable resources: create → read → edit → read → delete → 404.** This is the minimum coverage and is the spine of `EditorManagesArticleTest`.
7. **`_embedded` vs `_links` is pinned in a contract test, not in stories.** Taxonomy nouns under `_embedded`, Choreography verbs under `_links`.
8. **`Location` after `POST` is the navigation cue.** A hypermedia client cannot guess the URL of a just-created resource, so `onPost` returns `Location: /<noun>?id=<id>` and the workflow test follows it the same way a browser would. PUT and DELETE are unsafe transitions invoked directly by HTTP method — they are not advertised as `_links` rels by design.

A test that hard-codes `app://self/article` mid-chain, asserts JsonSchema-shaped fields, or compresses an entire story into one method body is a workflow test in name only.
