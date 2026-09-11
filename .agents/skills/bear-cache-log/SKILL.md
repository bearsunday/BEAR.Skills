---
user-invocable: true
name: bear-cache-log
description: Install, read, and verify the semantic cache log (BEAR.QueryRepository) in a BEAR.Sunday project. Proves the invisible behavior of caching (hit/miss, TTL, CDN headers, purge, 304 decisions) through the log. Use when user says "キャッシュログ", "cache log", "キャッシュが効いてるか確認", "purgeが効かない", "304が返らない", "CDNに何を送ったか", "is the cache working", "purge not working", "what was sent to the CDN", "check the log module wiring", or asks to observe/debug BEAR.Sunday caching behavior.
---

# BEAR.Sunday Semantic Cache Log

BEAR.QueryRepository's cache behavior (hit/miss, save, invalidation, CDN headers, purge, 304
decisions) is recorded as a tree of typed open/event/close entries and can be validated against
public JSON Schemas. Use this skill in the order "install → read → verify → answer questions".

**Prerequisite**: `bear/query-repository` must be a version that includes the semantic log
(from PR [#178](https://github.com/bearsunday/BEAR.QueryRepository/pull/178) onward; while
unreleased, use the corresponding branch). This skill does not apply to earlier versions.

## Canonical documentation (read this first)

**Note**: `docs/` and `demo/` are `export-ignore`d via `.gitattributes` — they are **not
included** in a dist install's vendor directory (a normal `composer require`). If they are not
present locally, fetch them from GitHub or via
`composer reinstall bear/query-repository --prefer-source`.

| What you want to know | Where |
|---|---|
| The questions the log answers and the boundary of its guarantees | [docs/what-the-log-proves.md](https://github.com/bearsunday/BEAR.QueryRepository/blob/1.x/docs/what-the-log-proves.md) |
| The full event vocabulary (one line per context) | The Cache Log section of https://bearsunday.github.io/BEAR.QueryRepository/llms-full.txt |
| **The whole vocabulary and how to read it (start here)** | [docs/reading-the-log.md](https://github.com/bearsunday/BEAR.QueryRepository/blob/1.x/docs/reading-the-log.md) |
| Design rationale, measured cost, and why it defaults to off | [docs/why-the-log-records-everything.md](https://github.com/bearsunday/BEAR.QueryRepository/blob/1.x/docs/why-the-log-records-everything.md) |
| The schema for each context | [docs/schemas/context/](https://github.com/bearsunday/BEAR.QueryRepository/tree/1.x/docs/schemas/context) (each event's `schemaUrl` is the canonical URL) |
| A working example (self-validating) | [demo/run*.php](https://github.com/bearsunday/BEAR.QueryRepository/tree/1.x/demo) |

## 1. Install (recording is off by default)

`QueryRepositoryModule` binds `SemanticLoggerInterface` → `NullSemanticLogger`. **Nothing is
recorded, nothing accumulates, and there is no flush responsibility.** To record, install a
module:

```php
// dev — 1 request = 1 file + latest.json
$this->install(new DevQueryRepositoryLogModule($appDir . '/var/log/query-repository', module: new QueryRepositoryModule()));

// prod — accumulates sessions and decides at flush time (keeps only mutations / failures / a sample)
$this->install(new ProdQueryRepositoryLogModule('php://stdout', sampleRate: 1000, module: new QueryRepositoryModule()));
```

- **Wrapping with `module:` is mandatory.** `install()` does not overwrite existing bindings
  (Ray.Di's `Container::merge()` is `+=`), so installing `QueryRepositoryModule` separately
  leaves you **silently stuck on null**
- **The wrapped module replaces, it does not add to, the existing one.** If the app has already
  installed `QueryRepositoryModule` (e.g. via BEAR.Package) and an override adds
  `new DevQueryRepositoryLogModule(..., module: new QueryRepositoryModule())` on top,
  **Ray.Aop's pointcuts accumulate and the interceptor runs twice** — one request produces two
  reads and two writes, and the log shows the same URI nested inside itself. To add recording to
  an existing graph without wrapping, replace only the single binding
  `bind(SemanticLoggerInterface::class)->annotatedWith(CacheLog::class)`
- **Flushing is the module's job.** `LogSinkInterface` (`ShutdownFlush`) arms one shutdown flush
  per process. The application writes **zero lines** of flush code
- The record survives an early `exit()` on 304, and it survives an uncaught exception (shutdown
  runs after either)
- A broken write destination never throws. It reports to `error_log()` and stays silent (the log
  is a side channel)
- **Concurrent runtimes**: inside RoadRunner (`RR_MODE`) or a Swoole coroutine, the sink refuses
  to arm and **recording stops entirely** (the reason goes to `error_log`). A resident worker CLI
  consumer cannot be detected this way, so do not install there
- A session includes the URI (query string included), the client validator, and raw exception
  text. `LogFileWriter` creates files as 0700/0600. Scrubbing or rate-limiting is done by
  decorating `LogWriterInterface`

Tree view: `vendor/bin/stree var/log/query-repository/latest.json` (takes a file argument; see
`--help` for options).

### 1b. Inspect the wiring (per context)

Because recording defaults to off, **"there is no log" and "the module is not installed" cannot
be told apart from the output alone.** A wrong install is silent too (the `module:` trap above).
So inspect the bindings, then prove it with one live request at the end.

**Procedure**: build an injector for each context (dev / prod / test / CLI) and pull the
following, tabulated. Do not guess from file names.

```php
$injector->getInstance(SemanticLoggerInterface::class, CacheLog::class);  // Null means nothing is recorded
$injector->getInstance(LogWriterInterface::class);                         // the effective write destination
$injector->getInstance(LogSinkInterface::class);                           // the flush boundary
```

| Check | Failure it catches |
|---|---|
| Is the `#[CacheLog]` logger a `SafeSemanticLogger`? | Still `NullSemanticLogger` = not wrapped with `module:`, or silenced by a separate install |
| dev uses `LogFileWriter`, prod uses `LogStreamWriter` (or `PsrLogWriter`) | A dev-shaped log in production: every session written out, no retention policy |
| Is the `LogFileWriter` directory **dedicated**? | Past `keep`, it deletes old `YYYYMMDD-HHMMSS-*.json`-shaped files. Sharing the app's `var/log` deletes unrelated files of the same shape |
| Does `LogStreamWriter` point at `php://stdout`/`stderr`/`output`, or a path the process can write? | Wrapper strings (`php://filter/…`, etc.) are rejected at construction, but an unwritable path only shows up in `error_log` at runtime |
| Is the host one process per request? | RoadRunner / Swoole coroutines make the sink refuse and recording stops (reason in `error_log`). FrankenPHP workers, ReactPHP, Amp, and resident CLIs are **not detected** — do not recommend for a host you cannot classify; ask instead |
| Is `SemanticLoggerInterface` bound elsewhere without `Scope::SINGLETON`? | A second logger arming the same sink means it never flushes. The sink logs "a second logger armed an already-armed sink" to `error_log`, but nobody reads it |
| Does a context that serializes the injector (a compiled app) have `PsrLogWriter`? | It holds the host's logger (Monolog, which carries closures), and the compiled graph can no longer be unserialized |

**Prove it**: don't stop at claims about the wiring. Send one request through and watch it
arrive.

```bash
# dev: latest.json is updated and contains a get scope
php public/index.php get /   # or curl the resource in question
vendor/bin/stree var/log/query-repository/latest.json

# prod (stdout): one line of JSON comes out. A healthy read is dropped by the retention
# policy though, so check with a request that writes or fails (or set sampleRate: 1 temporarily)
```

If nothing arrives, check `error_log` output (the SAPI error log for FPM). A sink refusal, a
double arm, or an unwritable destination all show up there.

## 2. Read — operational questions mapped to events

| Question | Event to look at |
|---|---|
| Was this response saved, for how long, under which key | `{tags, ttl, saved}` on `save_value` / `save_view` / `save_etag` / `save_donut` / `save_donut_view` |
| What did the CDN get told to cache, for how long | `headers` on `cdn_headers` (the literal response headers — **the setter's default values show up here too**). No lifetime header means the CDN will not cache it (a putDonut kind) |
| What did purge target, and did it take effect | `{tags, roPool, etagPool, cdn}` on `invalidate`. `cdn` is a three-valued `purged`/`failed`/`skipped`, fail-closed |
| Did edge revalidation (304) hit | A `conditional_request` scope closing with `cache_hit{layer: etag}` = a 304 without running the resource |
| Why wasn't it saved | `{reason, code}` on `put_skipped` (`etag-present`/`error-code`/`not-cacheable`) |
| Was the miss a genuinely empty store, or could the store not be read | If the same scope also has `cache_error{operation: read}`, that's the unreadable side (degraded). Otherwise it's cold. **Read-only sessions are not retained in production**, so this distinction is a development-time one — count degradations through the app's warning channel (`trigger_error`) |
| Who wrote / deleted it | `source` on the `command` scope; direct calls are `manual_store`/`manual_purge`/`manual_invalidate`. An `invalidate` immediately after `pre_write_cleanup` is pre-write cleanup, not a real invalidation |
| Dependency propagation | The parent's declaration decides the evidence: `#[Cacheable]` emits `depends_on` and merges the child's URI tag into its own `save_*` `tags` (cross-check those against `invalidate`'s `tags`); `#[CacheableResponse]` emits no edge — the child's tag is on `save_etag` / `save_donut_view`, never on `save_donut`; `#[DonutCache]` records none, so the child's own entry decides freshness |

Follow llms-full.txt for the detailed reading rules (do not fill gaps with your own
interpretation).

## 3. Verify — turn the log into a CI asset

The application can use the same three layers as the demo:

1. **Schema validation (offline)**: `SemanticLogValidator` takes a local schema directory. A
   dist vendor install has no schemas, so the reliable approach is to **copy
   `docs/schemas/context/*.json` into the application and keep it under version control** (the
   schema is a public contract — pinning it on the app side has real meaning). With a
   `--prefer-source` install, `vendor/bear/query-repository/docs/schemas/context` can be used
   directly
2. **Observation from tests**: since the default is null, bind `SafeSemanticLogger` in a test
   module (without a sink — the test flushes itself, when it wants to read). Pull it from the
   injector, flush it after the operation, and assert the event sequence (pinning things like
   "the parent's state disappears after purge" on **both the effect and the log**)
3. **The demo pattern**: turn the main scenarios into a single script, print the tree, and raise
   an exit code from schema validation
   ([demo/validate.php](https://github.com/bearsunday/BEAR.QueryRepository/blob/1.x/demo/validate.php)
   is the template)

### Assumptions when writing tests on the application side

- **The pool defaults to `NullAdapter`.** If the context binds nothing, a test saves zero cache
  entries. A resource with its cache disabled fails nothing, so **provision a store first**.
  Binding `AdapterInterface` to `ArrayAdapter` with `#[ResourceObjectPool]` in a test override
  module needs no Redis
- **The qualifiers are `BEAR\RepositoryModule\Annotation\*`** (`CacheLog`, `ResourceObjectPool`,
  `TagsPool`, `EtagPool`). `BEAR\QueryRepository\Annotation\` has classes of the same name, and
  mixing them up throws no exception — your own `getInstance()` answers with the same wrong key,
  so it looks like "the binding works." **Confirm by counting what's actually in the pool**
  (`ArrayAdapter::getValues()`)
- **Do not install a module in the override**, use bind only. Installing a storage module
  doubles up `QueryRepositoryModule`, stacking Ray.Aop's pointcuts so the interceptor runs twice
- **A `final class` cannot receive an interceptor.** Ray.Aop weaves by subclassing, so
  `#[Cacheable]` on a `final` resource is silently disabled and the log stays empty — not even a
  miss. Check whether weaving happened with
  `get_class($injector->getInstance($class)) !== $class`; check which interceptors got attached
  via the woven object's `bindings` property (method name => interceptor)
- **Do not judge by timing.** Within a process, even a refill takes single-digit milliseconds, so
  a hit and a miss are indistinguishable that way. Always judge by events (`save_value` /
  `cache_hit` / `cache_miss`)
- **The preset values** live in `Expiry`: `short` 60 / `medium` 3600 / `long` 86400 / `never`
  31536000 seconds. Since an app can override these with `StorageExpiryModule`, resolve them in
  tests by pulling `Expiry` from the injector and calling `getTime()` — do not copy the numbers
  by hand

### The log cannot decide whether you need tags or a TTL

Declaring only tags on `#[Cacheable]` keeps only the values that **have a write path announcing
that tag** up to date. If the body copies in a value from elsewhere with no path announcing it,
the tag never arrives and the TTL becomes the only floor. Questions to settle from the code:

1. For each value that ends up in this body, what is **the write path that changes it**?
2. Does that path call invalidate (if not, TTL is the only eviction)?
3. If **another copy** of the same data exists, is the copy's lifetime at most the original's (if
   longer, the copy returns a value the original has already discarded)?

Question 3 can be checked mechanically from attributes. Example:
[BeMart `tests/Resource/AgentCorpusCacheTest.php`](https://github.com/be-framework/BeMart/blob/1.x/tests/Resource/AgentCorpusCacheTest.php)

### A gate that judges the whole application

An oracle that judges store/hit/invalidation from the log per flow (`var/loop`) lives, working
copy included, at `skill://bear-cache-gate`. See that skill for installing it, running it, and
adding flows.

## 4. Troubleshooting patterns

- **"The cache isn't working"**: look at the `get` scope for the URI in question. If it closes
  with `cache_hit`, it's working. `put_skipped{etag-present}` means your own ETag is the cause;
  `put_skipped{error-code}` means a non-200 response
- **"I purged it but it's still stale"**: check whether the target key is in `invalidate`'s
  `tags`. `cdn: skipped` means no purger is configured (only the local copy is cleared, the CDN
  keeps it). `cdn: failed` means an exception should have propagated (fail-closed)
- **"The CDN holds it too long"**: look at the lifetime header in `cdn_headers.headers`. Often
  the cause is the default applied when `sMaxAge` is unset (generic 10 seconds / Fastly and
  Akamai 31536000 seconds)
- **"304 is never returned"**: if `conditional_request` closes with `cache_miss{layer: etag}`,
  the validator is stale. If the scope is missing entirely, `If-None-Match` never arrived
  (suspect a proxy or firewall)

## Out of scope (asking the log is pointless)

The CDN's actual behavior (propagation delay, edge-side eviction), `#[HttpCache]`'s static header
configuration, custom setters using non-standard header names, and expiry in real wall-clock
time. See the "What the log does not record" section of `what-the-log-proves.md` for details.
