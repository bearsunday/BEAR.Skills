---
user-invocable: true
name: bear-cache-gate
description: Install, run, and extend a gate (var/loop) that judges a BEAR.Sunday application's cache behavior with an oracle that reads the semantic cache log. Proves store, hit, invalidation, and cost per flow (a URI to read/write/purge) from the log, and returns violations as text. Use when user says "キャッシュゲート", "var/loop", "オラクル", "cache gate", "フローを追加", "キャッシュを検証するループ", "oracle", "add a flow", "prove cache correctness", or asks to prove/extend cache correctness in a BEAR.Sunday application beyond what unit tests can see.
---

# Cache Gate (var/loop)

Green tests don't tell you whether an application's cache is correct, because **a resource with
its cache disabled keeps returning the right answer anyway**. This gate reads the semantic cache
log and judges, per flow, whether it saved, whether the second read is a hit, whether
invalidation arrives, and whether a hit is cheaper than a miss — then returns violations as text.

Track record: this caught **4 already-released defects** that BEAR.QueryRepository's 265 tests
were passing. On the application side, it found 17 missed invalidations that a 30-second TTL was
hiding, and 2 cache declarations that had never actually taken effect because of `final`.

How to read the log and what the events mean is `skill://bear-cache-log`. This skill is **how to
run it**.

## Layout

| File | Role |
|---|---|
| `harness/verify-cache.php` | The oracle. `php var/loop/verify-cache.php <flow>` judges one flow and exits non-zero on a violation |
| `harness/verify-all.sh` | The gate. Every flow plus the application's test suite. **Only exit 0 is green** |
| `harness/run-loop.sh` | The autonomous loop (a worker implements one flow → the gate runs → a different model judges it) |
| `templates/worker.md` | Instructions for the worker. **Rewrite it per campaign** (the copy shipped here is for BeMart's "move a Page's query into an app resource") |
| `templates/verifier.md` | Instructions for the judge. Lists the conditions for `done=true` |

## Setup

```bash
mkdir -p var/loop
cp <skill>/harness/* var/loop/
chmod +x var/loop/*.sh
```

Only three places need editing:

1. **`FLOWS` in `verify-cache.php`** — the application's URIs. This is the bulk of the
   app-specific part
2. **The `for flow in ...` line in `verify-all.sh`** — the flow names to run. **Defining one in
   `FLOWS` but forgetting to list it here means it never runs** (this actually happened: adding
   it turned the gate red and surfaced the `final` defect)
3. **The environment defaults** — `DATABASE_URL` / `CACHE_DSN` / `APP_CONTEXT`. All overridable
   via env

`var/loop/last-*.txt` holds run results. Do not track it in the repository (add it to
`.gitignore`).

## Running it

```bash
redis-server --daemonize yes                        # the store CACHE_DSN points at
PHP=/opt/homebrew/opt/php@8.4/bin/php ./var/loop/verify-all.sh
echo $?                                             # anything but 0 is red
```

The first three lines are the certificate of what was judged:

```text
php            8.4.25
context        cli-fake-hal-app (cache redis://127.0.0.1:6379)
library        1.x 06b5902
```

The `library` line only appears when `vendor/bear/query-repository` is a path-repository
checkout. **A gate that doesn't say which commit a green run belongs to hides a fixed defect
coming back.** `NOT A CHECKOUT` means the verdict is not tied to a library commit.

**Don't read the tail.** Read the `exit` code. There is a real case where one flow's FAIL didn't
show in the last 5 lines and the run falsely reported green.

## Adding a flow

One entry in `FLOWS` is one flow. Keys:

| Key | Meaning |
|---|---|
| `read` | The URI being judged (`app://self/…` or `page://self/…`) |
| `write` | The URI to write. Checks whether invalidation arrives after the write. `null` if none |
| `purge` | The **child** URI to purge. Checks whether the parent falls with it (an embed dependency) |
| `purgeTags` | Tags to invalidate — a shared surrogate key like `['product-corpus']` |
| `embeds` | Whether the parent embeds the child |
| `mode` | `'per-request'` (**must not be cached**), `'cdn'`, or `'cache-down'` (the store is down) |
| `childCached` | `false` when the child is also not a cache target |
| `dsn` | Use a different store for this flow only (for `cache-down`) |

`embeds` only says there is a child to look for. Which evidence the oracle then demands comes from
the parent's declaration, which it reads off the log — no entry here selects it.

Verdicts are numbered violation strings (`27: hit was not cheaper than the miss …`). **Never
remove a number, only add new ones.**

### What a dependency looks like depends on the parent's declaration

"The parent stays stale after the child is updated" is one symptom, and what proves the link is
there differs per declaration. The oracle reads the declaration off the parent's own `save_*`
events, prints it as `parent kind`, and asks only for the evidence that declaration records. Each
violation names the two tag sets it compared.

| Parent's declaration | What it records about the child | How a broken link reads |
|---|---|---|
| `#[Cacheable]` — `save_value` / `save_view` | A `depends_on` edge, and the child's tags merged into the parent's save tags | `3: no depends_on edge`, then `3: the child tags [..] are absent from the parent save tags [..]` |
| `#[CacheableResponse]` — `save_donut_view` | No edge. The child's URI tag is on the parent's `save_etag` / `save_donut_view` tags and on the response `Surrogate-Key` | `3: the child tags [..] are absent from the parent save tags [..]` — the same words, with no edge to precede them |
| `#[DonutCache]` — `save_donut` alone | Nothing. Only the template is stored, and the hole is refilled from the child on every read | Nothing to report — judge the child's entry instead |

A `#[Cacheable]` parent with no edge at all is usually a missing `#[Embed]`, or a body that
replaced the embedded request before the put. A `final` parent never gets this far: it receives no
interceptor, stores nothing, and fails 1.

**Reporting "no dependency" on a `#[DonutCache]` parent is wrong.** Its child's tags are kept off
`save_donut` deliberately, so demanding a `depends_on` edge — or a child tag on the parent's save
tags — fails a page that is behaving as designed. What has to hold is that the purged child's own
`get` closes `cache_miss`; a child that still hits is the page's staleness
(`5: the child still hits after being purged`).

The declaration decides two more verdicts. **5**: after an invalidation a `#[Cacheable]` parent
must close `cache_miss`, while a donut parent stays a `cache_hit` and carries `refresh_donut` —
its template survives on purpose, and a hit without `refresh_donut` is the stale one. **4**: a
`#[DonutCache]` parent stores no page entry, so it has no tag set for an announcement to meet and
the intersection is not judged.

The write side breaks independently of all three:

| Violation | Broken link | Where to fix it |
|---|---|---|
| `28: the write only cleaned up its own entry` | The write's `invalidate` is **only** the one immediately after `pre_write_cleanup` — it cleared its own entry and told nobody about the change | The write side's `#[Refresh]`/`#[Purge]`, or its `invalidateTags()` call |
| `4: the write invalidated [..], which does not meet the read tags [..]` | It did announce, but the tag doesn't intersect what the parent saved | How the tags were chosen (URI tag vs. shared surrogate key) |

Why 28 stands on its own: the cleanup `invalidate` and a real `invalidate` can carry **the same
tags** (both are the URI tag of the resource that was written). Tag correlation cannot tell them
apart — only adjacency to the marker can. This is the same rule the library's retention policy
(`KeepMutationsAndFailures`) uses.

The `cdn on write` line is the same `invalidate`'s three-valued `cdn`. `skipped` means no purger
is bound (normal for a local verdict); `failed` is violation 29 — the local copy cleared but the
edge stayed stale.

**When the log can't answer**: the oracle binds and flushes its own logger, so it is unaffected
by the retention policy. Trying the same judgment against a log written by the application's
`ProdQueryRepositoryLogModule` finds the healthy read that saved the parent already dropped —
only one side of the intersection remains. State plainly that production logs only show the
purge side.

### One cycle = one flow + one invariant

Adding several at once loses track of which verdict guards what. Running ten cycles this way,
each cycle surfaced one defect or design decision.

### Never widen KNOWN

`KNOWN` holds **only unfixed defects with an issue number.** Adding one just to make a run pass
is forbidden. Remove it once the issue closes — if the defect comes back, the next cycle fails.

### A TTL kills the verdict

Attaching a short TTL makes a missed invalidation "fix itself in a few seconds," hiding it.
Decide first **whether the design genuinely needs a TTL, or whether it's just concealing the
leak.** The questions for deciding are in `skill://bear-cache-log`.

## The autonomous loop (`run-loop.sh`)

```bash
./var/loop/run-loop.sh [max-iterations]   # default 3
```

A worker implements one flow → the gate runs → a different model judges it against
`verifier.md` → anything other than `done=true` stops the loop.

**Honest track record: 3 runs, 0 commits worth keeping.** Two runs dropped to idle at 0 tokens.
What actually worked was a human enforcing the discipline (one flow per cycle, judged by the
log). If you use it:

- `WORKER_PROVIDER` / `WORKSPACE` / `JUDGE_MODEL` are machine-specific. **Always rewrite them**
- `worker.md` **is the campaign's goal itself.** The copy shipped here is for a finished
  campaign — do not reuse it as-is
- Confirm each condition in `verifier.md` **can be judged from the output of the command written
  right there.** `git show --stat` only names files, so it cannot judge "what is being injected"

## Pitfalls

- **The pool defaults to `NullAdapter`.** If the context binds no store, the application's tests
  save zero entries. The gate can judge because it passes a store via `CACHE_DSN`, but **the
  regular test suite is not looking at the cache at all**
- **A `final class` cannot receive an interceptor.** If the log is empty (not even a miss), first
  check whether weaving happened: `get_class($injector->getInstance($class)) !== $class`
- **Run `composer clean` after a weaving change.** A stale compiled DI graph means weaving still
  doesn't happen even after the fix
- **Do not judge cache effectiveness by timing.** Within a process, even a refill takes
  single-digit milliseconds and is indistinguishable from a hit
- **Do not symlink `vendor` when verifying inside a worktree.** Autoload's absolute paths point
  at the main checkout's `src`, so you end up **measuring the main checkout, not the worktree's
  changes.** Use `cp -a vendor` plus `composer dump-autoload` instead
- **A flow that opens an HTTP connection needs a port.** There is a real case of one talking to
  another project's server. Make fixed values overridable via env

## Reference implementation

BeMart's (`be-framework/BeMart`) `var/loop`: 12 flows, 27 verdicts. The test-side example:
[tests/Resource/AgentCorpusCacheTest.php](https://github.com/be-framework/BeMart/blob/1.x/tests/Resource/AgentCorpusCacheTest.php)
