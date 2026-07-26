# ResourceObject patterns

## Return type

Use `static` for `on*` methods that return `$this`:

```php
public function onGet(int $id): static
```

## Body construction

`$this->body` is the single output channel. The `Embed` interceptor injects `Request` objects into `$this->body[$rel]` *before* `onGet` runs. Therefore:

- **`onGet` with `#[Embed]`**: use `+=` (no-overwrite union). This protects the embed-injected slots and makes the intent explicit ("add own data, do not touch what was already there"):
  ```php
  $this->body['author']->addQuery(['id' => $article->authorId]);
  $this->body['tagList']->addQuery(['articleId' => $article->id]);

  $this->body += [
      'id' => $article->id,
      'slug' => $article->slug,
  ];
  ```
- **`onGet` without `#[Embed]`, `onPost`, `onPut`, `onDelete`, error paths**: literal `$this->body = [...]`. The shape is readable top-to-bottom as JSON.
- **Sequential `$this->body['k'] = $v;` is not used.** It hides the response shape across many lines and provides no semantic over `+=` or literal.

`+` ("union") is "do not overwrite", not "left wins by priority". Embed rels use taxonomy nouns, body fields are scalar — no collision is possible, so `+=` is the most semantically precise operator.

## HTTP status codes

| Method | Success | Not found | Validation failure |
|---|---:|---:|---:|
| GET | 200 | 404 | n/a |
| POST creating an addressable resource | 201 + `Location` | n/a | 422 |
| POST action / non-creating exchange | 200 + body | n/a | 422 |
| PUT | 200 | 404 | 422 |
| DELETE | 204 | 404 | n/a |
| Unique-key conflict | — | — | 409 |

**POST is not always creation.** `201 + Location` only applies when the POST adds a new addressable resource. Action-style POSTs (auth code exchange, password reset confirm, "log this event") return `200` with the result body and no `Location` header.

## Not found

- **App resource read miss**: set `$this->code = Code::NOT_FOUND` and a literal error body. Do not throw for ordinary read misses.
- **Page template for a primary entity**: guard against null and throw the entity-specific `*NotFoundException` at the top of the template so the framework's `catch (Throwable)` path routes to `templates/Error.php`.

  ```php
  <?php
  /** @var \MyVendor\Cms\Entity\Article|null $article */
  if (! isset($article) || $article === null) {
      throw new \MyVendor\Cms\Exception\ArticleNotFoundException();
  }
  ```

Exceptions are per-entity (`ArticleNotFoundException`, `AuthorNotFoundException`, …), not shared. Only the *primary* entity needs the guard; list-shaped vars are always lists (possibly empty), not null. Every Page test for such a resource should include a `testNotFoundRendersErrorTemplate` regression case.

## Method order

1. `__construct`
2. public `on*` handlers in HTTP order: `onGet`, `onPost`, `onPut`, `onDelete`
3. private helpers, after every public method

Reading top-to-bottom should mirror the public surface first, the implementation detail last. Helpers above the handlers force the reader to skim past internal plumbing before reaching the entry point.

## Exceptions

- No generic `LogicException` / `RuntimeException` / `InvalidArgumentException` in `src/`. Define a domain-named subclass under `<Vendor>\<Project>\Exception\<DomainName>Exception` for any thrown exception.
- Read misses use `$this->code = Code::NOT_FOUND`, not a thrown exception. Page templates re-raise the per-entity `*NotFoundException` to reach the error template.

## Pagination

Collection reads use Ray.MediaQuery's `#[Pager]` and return `PagesInterface`:

```php
interface ArticleQueryInterface
{
    #[DbQuery('article_list'), Pager(perPage: 20, template: '/article{?page}')]
    public function list(): PagesInterface;
}
```

Resource code reads `$pages[$page]`, maps the returned Page object's associative `data` rows through an entity factory, and surfaces `total`, `hasNext`, and `maxPerPage` in `$this->body`. The DB-free fake implements the same contract with Pagerfanta's `ArrayAdapter` so tests exercise the same pagination shape without PDO.

## Named arguments

Positional is the default. Use named arguments only where positional breaks the reader's ability to decode the call. The PHP 8.0 RFC introduced named arguments for exactly two situations:

1. **A literal `true` / `false` is passed.** `execute($sql, true, false)` cannot be decoded from type or order.
   ```php
   $query->execute($sql, cache: true, strict: false);
   ```
   Bool passed via a *variable* (`$query->execute($sql, $useCache)`) carries meaning in the variable name; positional is fine.
2. **A middle optional argument is skipped.**
   ```php
   htmlspecialchars($s, double_encode: false);
   ```

Stay positional otherwise — even for many-arg calls — when type and verb order make the call decodable:

```php
new Point($x, $y);
$fs->move($src, $dst);
$cache->remember($key, $ttl, $callback);
$command->add($slug, $title, $body, $excerpt, $status,
              $publishedAt, $authorId, $categoryId);
```

Explicitly **not** adopted (these are signature-design problems, not call-site problems): "3+ args → named", "5+ args → named", "same-typed 2+ → named", "any nullable → named", "any `bool` → named".

When named keeps creeping in, the signature is the smell: aggregate into a value object, split the method, drop `bool` parameters in favour of an enum.
