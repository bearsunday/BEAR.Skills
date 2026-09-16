---
user-invocable: true
name: bear-cacheable
description: Read an existing BEAR.Sunday application and propose where #[Cacheable], #[CacheableResponse], #[DonutCache], #[Refresh] and #[Purge] belong, with the evidence for each proposal and the write path that must invalidate it. Read-only; reports the resources it declines and every cacheable resource that has no paired invalidation. Use when user says "add cache", "キャッシュを設定", "Cacheable", "which resources should be cached", "キャッシュ戦略", "optimize caching", or asks to apply cache attributes to a project.
---

# Cache attributes for an existing project

Getting the attributes wrong is silent in both directions. A `#[Cacheable]` resource whose writes
carry no invalidation serves stale content until its TTL; a resource with no attribute just costs
origin work. Neither fails a test. So this skill does not grep for missing attributes and add them -
it reads the write paths and the read paths together, and proposes only what the code gives evidence
for. It runs read-only: the output is a proposal, applying it is a separate step, and the proof that
the pairing works is the log (`bear-cache-gate`), not an assertion here.

## What the library does, verified in `bear/query-repository` 1.x

Every proposal below rests on these. Do not propose anything that contradicts them.

| Fact | Consequence for a proposal |
|---|---|
| `#[Cacheable]` is class-level (`TARGET_CLASS`); `#[DonutCache]`/`#[CacheableResponse]` allow class or method | Put `#[Cacheable]` on the class, never on `onGet` |
| `#[Cacheable]` and `#[CacheableResponse]` are exclusive: the first is woven by `CacheInterceptor` (TTL / tag), the second by the donut interceptors (event-driven) | Never propose both on one class |
| On a `#[Cacheable]` class, `onPost`/`onPut`/`onPatch`/`onDelete` are all intercepted by `CommandInterceptor`: the same-URI entry is purged and re-read (`RefreshSameCommand`), then any `#[Refresh]`/`#[Purge]` on the method run (`onPost` included since bear/query-repository#214 - the matcher used to skip it, dropping any `#[Refresh]`/`#[Purge]` written there with no error) | Do not propose `#[Purge]` for the resource's own URI on its own writes - it is automatic, and an explicit same-URI purge runs *after* the refresh and busts the entry it just rebuilt |
| `RefreshSameCommand`'s automatic same-URI refresh matches the write's own URI query against `onGet`'s required parameters (`MatchQuery`); a required `onGet` parameter with no default and absent from that query throws `UnmatchedQuery` | Before proposing `#[Cacheable]` on a class whose write doesn't share `onGet`'s required parameters (a create posting to a collection URI, a body-only write) - the automatic refresh a `#[Cacheable]` class gets is a crash on that write, not a silent miss |
| `#[Refresh]`/`#[Purge]` are method-level and repeatable; `uri` is required and takes `{param}` templates bound from the response body merged with the URI query, body taking precedence (`RefreshAnnotatedCommand::getUri()`) | The target URI can be spelled from a field the writer's response body carries, not only from its own method parameters |
| A `final` resource class cannot be woven (Ray.Aop subclasses it); its attributes are inert and the log shows no `cache_miss` at all | `final` + any cache attribute is a finding, not a proposal |
| Embedded children (`#[Embed]`) propagate their `Surrogate-Key` into the parent automatically, but only a child that carries an `ETag` when the parent's `onGet` returns - in practice, a child that is itself `#[Cacheable]` (`QueryRepository::setCacheDependency()` skips any embedded request with no `ETag` header) - and only when a `#[Cacheable]` parent is itself stored: the put its own miss-then-run `onGet` triggers (`CacheInterceptor::invoke()`), not a write (`#[CacheableResponse]`/`#[DonutCache]` never call `put()` at all, so this merge never runs for them) | A parent may carry a shared key and embed children at once; what matters is that every writer of the parent's data announces a tag the parent stores. A non-cacheable embedded child contributes no tag here - its data still needs its own `#[Refresh]`/`#[Purge]` pairing. Rows-derived dependency sets use `UriTagInterface::fromAssoc` (`bear-clean-style/references/cache.md`) |
| `expiry` presets: `short` 60 s, `medium` 3600 s, `long` 86400 s, `never` 31 536 000 s (an app may rebind `Expiry`). `expirySecond` overrides. `expiryAt` names a body field holding a timestamp, and the TTL becomes `max(0, strtotime(field) - now)` - a field already in the past yields `0`, which the storage stores as no expiry at all, not an immediate one | `never` still expires after a year; "until invalidation" is a tag story, not a TTL story. An `expiryAt` field that can already be past on a fresh write is a silent no-expiry, not a visible bug - pair it with an invalidation path too |
| `type: 'view'` stores the rendered representation; `value` stores the body only | `view` needs a renderer bound for the resource and a representation that is the same for every client |
| The pool is `NullAdapter` unless the app's context binds one; recording is off unless a log module is installed | Nothing in this skill proves behaviour - verification needs a store and the log |

## Procedure

### 1. Inventory the read side

For every class under `src/Resource` that extends `ResourceObject` and has `onGet`, record:

- attributes already present (`Cacheable`, `CacheableResponse`, `DonutCache`, `HttpCache`, `Embed`), and whether the class is `final`
- what `onGet` reads besides its parameters: injected services and what they touch. Flag anything request-specific - session, authenticated user, cookies/headers, `time()`/`Clock`, random, feature flags evaluated per request, A/B assignment
- embedded children and whether each child is itself cacheable
- whether a template exists for the resource (`var/templates/**/<Name>.*`) - the precondition for `type: 'view'`
- whether the body carries its own expiry (a field like `expires_at`, `valid_until`)

### 2. Inventory the write side

For every `onPost`/`onPut`/`onPatch`/`onDelete` in the project, record what data it changes - the tables, the aggregate, the entity - and any `#[Refresh]`/`#[Purge]` it already carries with the resolved target URI. Then invert it: for each read resource, which write methods change the data its body comes from. This map is the evidence for every pairing below; a proposal without a row here is a guess.

### 3. Decide, per read resource

| Decision | Evidence that decides it |
|---|---|
| Cacheable at all | `onGet` reads nothing request-specific and embeds no non-cacheable child. One request-specific read anywhere in the body → decline, or propose `#[DonutCache]` with that part as the hole |
| `#[Cacheable]` vs `#[CacheableResponse]` vs `#[DonutCache]` | Same body for every client and no per-request part, kept as value/rendered state through `CacheInterceptor` → `#[Cacheable]`. A whole rendered page invalidated only by its writes, served through the donut interceptors → `#[CacheableResponse]`. Mixed stable/per-request page → `#[DonutCache]` |
| `type` | `view` only when a template exists and the rendered form is what every client receives; otherwise `value` |
| `expiry` / `expirySecond` / `expiryAt` | A write path exists that can announce the change → tags do the work; propose no TTL argument (default `never`) or a long floor, and say which. No write path but a known staleness budget → `expirySecond: N` with the budget stated. Body carries its own deadline → `expiryAt: '<field>'`, but only when that field cannot already be past on a fresh write - otherwise the entry silently never expires by TTL |
| `#[Refresh]` vs `#[Purge]` on each paired write | The next read must be warm (a hot page, a list every request hits) → `#[Refresh]`. A bust is enough, or the re-read would be wasted (the entity may be gone) → `#[Purge]` |
| Target URI | The read resource whose body the write changes, spelled with the writer's response body or its own URI query (body wins - `RefreshAnnotatedCommand::getUri()`). If neither carries the parameter the target needs (a delete that only knows its own id, a list keyed by something else) → report: invalidation is not spellable from here |
| Dependency shape | Parent composes exactly the children it depends on via `#[Embed]`, and each is itself `#[Cacheable]` (see the facts table) → nothing to add. A composed child that is not `#[Cacheable]` propagates no tag - it still needs its own `#[Refresh]`/`#[Purge]` targeting the parent. Dependency set comes from rows → `UriTagInterface::fromAssoc`. A child that is itself cacheable must advertise the same shared key as its writers announce, or the parent rebuilds from a stale child |

### 4. Pair every cacheable with its invalidation

This is the check the issue exists for. For every `#[Cacheable]`/`#[CacheableResponse]` you propose or that already exists:

- list the write methods from step 2 that change its data
- for each, state whether invalidation already reaches this resource (same-URI automatic on `onPost`/`onPut`/`onPatch`/`onDelete` of the same class; explicit `#[Refresh]`/`#[Purge]` elsewhere; embed propagation from a child that is itself invalidated)
- a write that changes the data and reaches nothing is the finding: **unpaired cacheable**. Report it as such even when the resource already carries `#[Cacheable]` today - an existing attribute is not evidence that it is right

### 5. Report

One block per resource. The confidence is about the evidence, not about the attribute:

- **high** - `onGet` reads only its parameters and one repository; every writer of that data is in the project and paired
- **medium** - the body is pure but some writers live outside the project (another service writes the table, a cron job), so invalidation cannot be complete from here; say what is missing
- **low** - the body depends on a service whose purity you could not establish; propose nothing and say what to check

```markdown
## Cache proposal — <project>

### src/Resource/App/Article.php
- Propose: `#[Cacheable]` on the class (type value, no TTL argument)
- Evidence: onGet(id) → ArticleQuery::item(id) only; no session/clock/random. Embeds app://self/author{?id}, itself #[Cacheable].
- Pairing: onPut/onDelete on this class → automatic same-URI refresh. App\Comment::onPost changes articles.comment_count → propose `#[Refresh(uri: 'app://self/article?id={articleId}')]` on it - a write on a different resource, so the invalidation has to be placed there explicitly.
- Confidence: high

### src/Resource/Page/Dashboard.php
- Decline: onGet reads SessionInterface::userId(); the whole body is per user.
- Alternative: `#[DonutCache]` with app://self/user/menu as the hole, if the rest of the page is shared. Not proposed until the shared part is confirmed.

### src/Resource/App/Product/Stock.php
- Finding: already `#[Cacheable(expirySecond: 30)]`; Admin\Inventory::onPost changes the stock table and reaches nothing (onPost on a non-cacheable class, no attribute). The 30 s TTL is what hides it.
- Propose: `#[Purge(uri: 'app://self/product/stock?productCode={productCode}')]` on Admin\Inventory::onPost, then decide whether the TTL was a design choice or a patch.

### Unpaired cacheables
| resource | writer that reaches nothing | why |
|---|---|---|
| App\Product\Stock | Admin\Inventory::onPost | no attribute |

### Declined
| resource | reason |
|---|---|
| Page\Dashboard | reads session |
| App\Search | body depends on a ranking service whose determinism is unknown (low) |
```

## Verification is not in this skill

Applying the proposal changes behaviour that no unit test observes: a resource that stopped caching still answers correctly. After applying:

1. bind a real store and turn recording on (`bear-cache-log`, section 1)
2. run the flow read → write → read and apply the tag-intersection rule from
   `docs/reading-the-log.md`: the write's `invalidate` tags must meet the parent's `save_*` tags,
   and the second read must be a `cache_miss`
3. `bear-cache-gate` does exactly that per flow and names the first broken link; use it when the
   project has more than a handful of resources

A proposal that cannot be paired must not be applied with a short TTL "for now": the TTL hides the
missing invalidation, and the gate then passes vacuously (`bear-cache-gate`, "TTL kills the judgement").

## References

- [BEAR.Sunday Cache manual](https://bearsunday.github.io/manuals/1.0/en/cache.html)
- [docs/reading-the-log.md](https://github.com/bearsunday/BEAR.QueryRepository/blob/1.x/docs/reading-the-log.md) - the tag-intersection rule
- `bear-clean-style/references/cache.md` - dependency shapes A and B and their anti-patterns
