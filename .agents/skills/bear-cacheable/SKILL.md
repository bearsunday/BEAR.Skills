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
| On a `#[Cacheable]` class, `onPut`/`onPatch`/`onDelete` are intercepted by `CommandInterceptor`: the same-URI entry is purged and re-read (`RefreshSameCommand`), then any `#[Refresh]`/`#[Purge]` on the method run | Do not propose `#[Purge]` for the resource's own URI on its own writes - it is automatic, and an explicit same-URI purge runs *after* the refresh and busts the entry it just rebuilt |
| **`onPost` on a `#[Cacheable]` class is not intercepted at all** - no same-URI refresh, and a `#[Refresh]`/`#[Purge]` written on it is silently dropped (the `RefreshInterceptor` that handles those attributes is bound only for non-`#[Cacheable]` classes) | A create that must invalidate a cached list is a finding: propose the invalidation on a non-cacheable writer, or report that the list has no reachable invalidation |
| `#[Refresh]`/`#[Purge]` are method-level and repeatable; `uri` is required and takes `{param}` templates bound from the method arguments | The target URI must be spellable from the writer's own parameters |
| A `final` resource class cannot be woven (Ray.Aop subclasses it); its attributes are inert and the log shows no `cache_miss` at all | `final` + any cache attribute is a finding, not a proposal |
| Embedded children (`#[Embed]`) propagate their `Surrogate-Key` into the parent automatically (`CacheDependency`); assigning `Header::SURROGATE_KEY` by hand short-circuits that merge | Dependency shape is A (embed only) or B (`fromAssoc` only) - see `bear-clean-style/references/cache.md`; mixing them is a finding |
| `expiry` presets: `short` 60 s, `medium` 3600 s, `long` 86400 s, `never` 31 536 000 s (an app may rebind `Expiry`). `expirySecond` overrides. `expiryAt` names a body field holding a timestamp, and the TTL becomes `strtotime(field) - now` | `never` still expires after a year; "until invalidation" is a tag story, not a TTL story |
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
| `#[Cacheable]` vs `#[CacheableResponse]` vs `#[DonutCache]` | Same body for every client and no per-request part → `#[Cacheable]`. A whole page whose invalidation is only its writes and which needs an ETag → `#[CacheableResponse]`. Mixed stable/per-request page → `#[DonutCache]` |
| `type` | `view` only when a template exists and the rendered form is what every client receives; otherwise `value` |
| `expiry` / `expirySecond` / `expiryAt` | A write path exists that can announce the change → tags do the work; propose no TTL argument (default `never`) or a long floor, and say which. No write path but a known staleness budget → `expirySecond: N` with the budget stated. Body carries its own deadline → `expiryAt: '<field>'` |
| `#[Refresh]` vs `#[Purge]` on each paired write | The next read must be warm (a hot page, a list every request hits) → `#[Refresh]`. A bust is enough, or the re-read would be wasted (the entity may be gone) → `#[Purge]` |
| Target URI | The read resource whose body the write changes, spelled with the writer's parameters. If the writer lacks the parameter the target needs (a delete that only knows its own id, a list keyed by something else) → report: invalidation is not spellable from here |
| Dependency shape | Parent composes exactly the children it depends on via `#[Embed]` → shape A, nothing to add. Dependency set comes from rows → shape B with `UriTagInterface::fromAssoc`. Both present → finding |

### 4. Pair every cacheable with its invalidation

This is the check the issue exists for. For every `#[Cacheable]`/`#[CacheableResponse]` you propose or that already exists:

- list the write methods from step 2 that change its data
- for each, state whether invalidation already reaches this resource (same-URI automatic on `onPut`/`onPatch`/`onDelete` of the same class; explicit `#[Refresh]`/`#[Purge]` elsewhere; embed propagation from a child that is itself invalidated)
- a write that changes the data and reaches nothing is the finding: **unpaired cacheable**. Report it as such even when the resource already carries `#[Cacheable]` today - an existing attribute is not evidence that it is right
- `onPost` on the cacheable class itself is always in this list (see the facts table) - it never invalidates

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
- Pairing: onPut/onDelete on this class → automatic same-URI refresh. App\Comment::onPost changes articles.comment_count → propose `#[Refresh(uri: 'app://self/article?id={articleId}')]` on it (Comment is not #[Cacheable], so the attribute is honoured).
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
| App\Article\Listing | App\Article::onPost | onPost on a #[Cacheable] class is not intercepted |

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
