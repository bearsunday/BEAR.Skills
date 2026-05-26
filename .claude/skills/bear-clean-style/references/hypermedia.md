# Hypermedia — `#[Embed]`, `#[Link]`, and workflow tests

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
