# Naming — Query, Command, SQL, and resource property names

Same vocabulary across attribute, signature, SQL filename, and property name. `#[DbQuery('article_item')] public function item(int $id)` should speak one language.

## Interfaces

- Read: `<Entity>QueryInterface`
- Write: `<Entity>CommandInterface`
- Never mix read methods into Command interfaces or write methods into Query interfaces.

## Read method names

| Purpose | Method | SQL id / file |
|---|---|---|
| Primary-key item | `item(int $id)` | `<entity>_item` / `<entity>_item.sql` |
| Natural key item | `bySlug`, `byEmail`, `byFilename` | `<entity>_by_slug.sql` etc. |
| Collection | `list()` | `<entity>_list.sql` |
| Filtered collection | `listBy<Article|Author|...>()` | `<entity>_list_by_<x>.sql` |

`item` (canonical PK lookup) and `by<NaturalKey>` (alternate access path) are intentionally distinct shapes: PK is the technical identity handle, natural keys (`slug`, `email`, `filename`) are domain-meaningful alternates. `item` ↔ `list` form a lexical pair that mirrors BEAR's resource shapes (`Article` item ↔ `Articles` collection).

After INSERT, fetch the new row by natural key via `by<NaturalKey>`, not `lastInsertId`. The natural key is what the client supplied; re-SELECT gives back the assigned id without driver-dependent state.

## Write method names

Imperative verbs: `add`, `update`, `delete`. Link-table commands may use `clear` and `link` (e.g. `ArticleTagCommandInterface::clear`/`link`).

## SQL filenames

- Pattern: `<entity>_<verb>.sql` in `var/db/sql/`
- Verbs match the method names above
- Examples: `article_item.sql`, `article_by_slug.sql`, `article_list.sql`, `article_add.sql`, `article_update.sql`, `article_delete.sql`, `article_tag_clear.sql`, `article_tag_link.sql`

## Resource dependency property names

Reads are queryable nouns; writes are action tools.

| Dependency | Property pattern | Example |
|---|---|---|
| Main read interface | `$<entity>` | `private ArticleQueryInterface $article` |
| Main write interface | `$<entity>Cmd` | `private ArticleCommandInterface $articleCmd` |
| Link/helper write interface | `$<entity><Role>Cmd` | `private ArticleTagCommandInterface $articleTagCmd` |

The asymmetric naming carries information:

- `$this->article->item($id)` reads as "the article-source's item by id" — receiver is a queryable noun, method qualifies the query.
- `$this->articleCmd->add(...)` reads as "the article command, add" — receiver is a tool, method names the action.

In a Resource focused on a single entity (`Article`, `Author`, …), the unsuffixed property name reserves the read role for the primary entity, distinguishing it from auxiliary write-only links.

## ALPS ontology

Entity-prefixed: `articleId`, `articleSlug`, `articleTitle`, `categoryParentId`, `mediaAlt`. Not bare `id` / `slug` (collision risk across entities).

## HAL rel naming — split by ALPS layer

ALPS has two distinct layers and HAL has two distinct collections (`_links` and `_embedded`); align them.

| Where | Source layer | Examples |
|---|---|---|
| `#[Link]` rel | ALPS **Choreography** (transition verbs) | `goArticleList`, `goAuthor`, `doCreateArticle`, `doDeleteTag` |
| `#[Embed]` rel | ALPS **Taxonomy** (entity nouns) | `author`, `category`, `tagList` |

Do not mix: `#[Embed(rel: 'goAuthor', ...)]` is wrong because `go*` is a Choreography (client-followable transition), while embed is a server-included taxonomy instance. The HAL envelope shape is pinned in a contract test (see [tests.md](tests.md)) so slips fail one isolated test.
