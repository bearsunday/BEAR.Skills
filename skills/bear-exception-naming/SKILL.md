---
user-invocable: true
name: bear-exception-naming
description: >-
  Design exceptions so a machine can read them: the class name states what went
  wrong, readonly properties carry the values, the message projects those values,
  and the doc comment holds the explanation. Applies to framework and application
  code alike - a resource throws ArticleNotFound, not RuntimeException. Use when
  the user says "exception naming", "例外命名", "セマンティック例外", "semantic
  exception", "例外設計", "exception design", "例外クラスのdoc", "domain exception",
  "ドメイン例外", "name these exceptions", or asks to make exceptions
  self-documenting, catchable, or consistent as a family.
---

# Semantic Exceptions

## Purpose

An exception has four channels, and three of them a machine can read. Put each
fact in the channel that can be extracted from:

| Channel | Holds | Extracted by |
|---------|-------|--------------|
| Class name | what went wrong | `catch`, `instanceof`, grep, rename refactoring |
| Readonly properties | the values it happened with | the catcher, a deploy tool, a renderer |
| Message | those values, projected for a human | logs, error pages |
| Doc comment | why it is an error, how the code reaches it, what to do | reflection, IDE hover, `apidoc`, review diffs |

Prose in the **message** is the only thing none of them can read. Prose in the
**doc comment** is welcome and can be as detailed as the case deserves.

`catch (Exception $e)` is the opposite of this: it throws away the one channel
that carries meaning, and leaves the reader to reconstruct it from a stack trace.
A codebase where every exception has its own class never needs that archaeology.

## Principles

1. **The class name states the failure.** Never throw `RuntimeException`,
   `LogicException`, or `Exception` directly. Name the condition -
   `ArticleNotFound`, `PharNotCompiled`, `WriteDirMismatch` - and let the name be
   the summary. Negative names are right here: the exception *is* a negative fact.
2. **Base class by kind.** `RuntimeException` for what a run can hit - a missing
   row, an unreachable service, a read-only filesystem. `LogicException` for a
   programming error - an argument that cannot be valid, a state that cannot occur.
3. **The values are properties.** Promote them in the constructor as
   `public readonly`, so a catcher can act on them instead of parsing text.
4. **The message is the values.** One value: the value itself. Several: label each
   with the one word that says which is which. Never restate the class name, never
   explain, never give a remedy - all three rot the moment the code changes.
5. **The doc comment is the explanation.** One line of what happened when the name
   needs it, then the detail: the path that reaches the throw, the design reason,
   what the caller should do, the message format when the message is values-only.
6. **Catch and test by class.** Tests assert the exception class; they never match
   message text. A test that pins prose fails on rewording and passes on real bugs.

## The renderer invariant

A values-only message is readable only where the class is rendered next to it.
BEAR.Sunday holds that end up - `ErrorLogger` logs `e:%s(%s)` with `$e::class`,
`ExceptionAsString` prints `%s(%s)` - so anything that renders an exception itself
(a worker script, a CLI entry, a custom handler) must print the class too:

```php
fwrite(STDERR, sprintf('%s: %s', $e::class, $e->getMessage()) . PHP_EOL);
```

State this in the renderer's doc comment, not in every exception.

## Shape

```php
/**
 * The article the request names is not in the repository.
 *
 * A GET of a deleted or never-published article reaches here through
 * ArticleQuery::item(), which returns null rather than an empty row. The resource
 * lets it fly: BEAR.Sunday turns it into 404 through its error handling, so a
 * resource never writes a status code for a missing row.
 *
 * Message format: the article id
 */
final class ArticleNotFound extends RuntimeException
{
    public function __construct(public readonly int $articleId)
    {
        parent::__construct((string) $articleId);
    }
}
```

Several values earn one label each, and nothing more:

```php
final class WriteDirMismatch extends LogicException
{
    public function __construct(
        public readonly string $compileDir,
        public readonly string $injectorDir,
    ) {
        parent::__construct(sprintf('compile %s, injector %s', $compileDir, $injectorDir));
    }
}
```

## A doc comment can be rich; a message cannot

The doc comment is structured text a tool reads, so use its vocabulary. A message
is one flat string with no semantics - it can link to nothing, reference nothing,
and no generator can pull it into documentation.

```php
/**
 * The compiled scripts write somewhere this boot does not.
 *
 * Recompiling writes to the script directory, which an archive or an immutable
 * image does not allow, so the boot stops here instead of failing on the write.
 *
 * Message format: script directory, compiled write directory, this boot's
 *
 * @see https://bearsunday.github.io/manuals/1.0/en/phar.html#when-the-build-stops
 * @see \BEAR\Package\Injector\PackageInjector::prodInjector() the only thrower
 */
```

Worth referencing, when it exists: the manual page that documents the failure, the
class that throws it, the design record. Length is not the measure - a reader who
hovers over the class gets the whole story, and `apidoc` publishes it.

**`@see` or `@link`?** PSR-19 gives `@see` both a structural element (an FQSEN,
which an IDE navigates to) and a URI, while `@link` takes a URI only and is
rendered as a hyperlink. So the clean split is code -> `@see`, external URL ->
`@link`. What matters more is one convention per project: BEAR.Package writes
`@see <URL>` throughout - `@see https://github.com/blongden/vnd.error` in
`ErrorHandler` predates this work - so add to that rather than mixing a second
form in. Check the project before you pick.

## In an application

- One class per condition a caller can distinguish. Two conditions with the same
  remedy can share a class; two with different remedies must not.
- Throw from the domain - a Query, an entity, a service - and let the resource
  stay clean. BEAR.Sunday maps the exception to a status; a resource that catches
  its own domain exception to set a code is doing the framework's work.
- Group them under the application's `Exception` namespace so the family is
  visible in one directory listing.
- The value a caller needs is a property, not a substring. `$e->articleId` is
  usable; `preg_match('/\d+/', $e->getMessage())` is a bug waiting for a reword.

## Workflow

1. **Read the throw sites.** For each exception, find where it is thrown and which
   values pin the instance down. Confirm from source; do not guess.
2. **Fix the name first.** Rename a generic or vague one to state the failure, and
   update every throw and catch site in lock-step.
3. **Promote the values.** Turn constructor arguments into `public readonly`
   properties, and build the message from them.
4. **Move the prose.** Whatever the message explained goes to the doc comment; a
   remedy, a reason, or a list of conditions never belongs in a runtime string.
5. **Point the tests at the class.** Replace message assertions with
   `expectException()`; keep an assertion on a property when the value matters.
6. **Verify.** Run the project's coding-standard, static-analysis and test
   commands, and confirm behaviour is unchanged.

## Anti-patterns

| Written | Why it fails |
|---------|--------------|
| `throw new RuntimeException('Article not found: ' . $id)` | the meaning is in a string; no catcher can name it |
| `class ArticleException` | one class for every article failure; the caller must read the message to tell them apart |
| `'The entry does not ship: the archive holds no .env, autoload.php, tests…'` | a remedy list in a message; it went stale the day the rule changed |
| `assertStringContainsString('not found', $e->getMessage())` | the test pins prose, not behaviour |
| `catch (Throwable $e)` around a resource body | throws away every distinction the classes were built to keep |

## Output

- List each exception touched, grouped by change: renamed, values promoted, prose
  moved to the doc comment, message shortened, tests repointed.
- Name the verification commands you ran, and state that behaviour is unchanged.
- Do not commit, push, or open PRs unless explicitly asked.
