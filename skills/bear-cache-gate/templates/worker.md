You are extending cache coverage in the BeMart application (the repository root, branch cache-app-layer), one flow per iteration. bear/query-repository is symlinked from the BEAR.QueryRepository checkout (branch loop-integration) — you may fix the library there on its own branch when it is the library that is wrong.

## This iteration

Pick the next storefront flow that still queries the database from a Page resource:

    grep -rln "QueryInterface" src/Resource/Page | grep -v Admin

Then, for that one flow only:

1. Move the query into an `app://self/…` resource that owns the data and its row shape.
2. Have the Page embed it with `#[Embed]` and keep only per-request work (session values, sort, paging, CSRF). The response shape is fixed by its JsonSchema (`additionalProperties: false`), so consume the embed and unset the rel.
3. Declare a cache strategy on every resource you touch, one line each:
   - changes through a write path or has no stateable staleness budget → `#[CacheableResponse]`
   - has a budget you can say in seconds → `#[Cacheable(expirySecond: N)]`
   - carries a session-specific value in its body (CSRF token, customer name) → no attribute; if only part of the page is per-session, `#[DonutCache]` with that part as the hole
   - `#[Cacheable]` with no argument is a 31536000-second TTL. Never use it for content that changes.
4. Add a flow entry to var/loop/verify-cache.php (FLOWS) for what you built: `mode: 'per-request'` for a page that must not be cached, `purge` for a parent whose child a write would invalidate.
5. Add the smoke fixture BeMart requires for each new resource target (tests/Smoke/ResourceSmokeTest.php) and `#[CsrfProtected]` on any mutating method.

## Rules

- The oracle is the judge, not your reading: `./var/loop/verify-all.sh` must exit 0.
- When an invariant fails, decide where the defect lives before fixing: the library (fix on a branch in BEAR.QueryRepository, with a test that fails without the fix), the skill (the bear-cache-strategy skill), or the application.
- Never widen KNOWN in verify-cache.php to make a run pass. It holds tracked upstream defects only, with an issue reference.
- Commit on cache-app-layer with a message that says what moved and why the strategy is what it is. Do not push, do not open PRs.
- `composer.json` and `composer.lock` are dirty on purpose: they carry path repositories to the local `BEAR.QueryRepository` and `Be.Framework` checkouts, which is how the loop tests library changes before they are released. Never stage them, and never revert them either.
- Skip formatters and full-project linters; `./var/loop/verify-all.sh` is the gate.

## Progress this iteration means

One more flow's data lives in the resource graph, its cache strategy is declared, the oracle judges it, and the gate exits 0.
