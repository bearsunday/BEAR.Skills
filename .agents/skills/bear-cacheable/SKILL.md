---
user-invocable: true
name: bear-cacheable
description: Read an existing BEAR.Sunday application and propose where #[Cacheable], #[CacheableResponse], #[DonutCache], #[DependsOn], #[Refresh] and #[Purge] belong, with the evidence for each proposal and the write path that must invalidate it. Read-only; reports the resources it declines, every cacheable resource that has no paired invalidation, and every handwritten invalidation a declaration should replace. Use when user says "add cache", "キャッシュを設定", "Cacheable", "which resources should be cached", "キャッシュ戦略", "optimize caching", or asks to apply cache attributes to a project.
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
| An embedded child (`#[Embed]`) is a dependency of its parent when it carries an `ETag` or a `Surrogate-Key` - from a cache attribute, from `#[DependsOn]`, or from what its `#[ResourceParam]` reads - and the test is the same for a `#[Cacheable]` parent and a donut page. A child carrying neither adds no dependency ([Cache Dependencies](https://github.com/bearsunday/BEAR.QueryRepository/blob/1.x/docs/llms-full.txt)) | An untagged child the parent reads is the shape that goes stale: make it cacheable, give it a `#[DependsOn]`, or render it as a hole |
| `#[DependsOn(uri: ...)]` on `onGet` or on a row class tags the stored entry with the URI it expands to, so a write that purges or refreshes that URI invalidates it. On `onGet`, every template variable must be an `onGet` parameter (not one `#[ResourceParam]` binds); on a row class, the variables are its public properties | A read inside `onGet` (a query, a row list, a `resource->get()` in a service it calls) is declared with `#[DependsOn]`, not with a handwritten `Surrogate-Key` or `invalidateTags()` |
| A list with no URI of its own (the latest articles, every page of a paginated list) can be named by an empty resource class with no methods - a virtual resource: its readers declare `#[DependsOn]` on it, and every write to that kind of item declares `#[Purge]` on it | Prefer a virtual resource over a shared string key such as `latest_article`: the dependency becomes a declaration, and qr-graph sees it and warns on a misspelled URI |
| A `#[Cacheable]` resource, and a class-level `#[CacheableResponse]` page with its holes, is stored with what it embeds and what `#[ResourceParam]` reads as they were when stored, and serves that until a dependency is invalidated or it expires. A `#[DonutCache]` page runs its holes on every request | Freezing a value that is not cached (a random pick, an external API, an expensive aggregate) is legitimate. A per-user value is not: it belongs in a `#[DonutCache]` hole or an uncached page |
| A stored entry is rebuilt at any time - after a deploy, an eviction or its expiry - from what its sources hold then | The cache is not storage. A value that must be kept (a record, the day's highest temperature) is saved by a write |
| `#[Refresh]` purges every target URI first, then GETs each of them inside the write's request (`RefreshAnnotatedCommand`) | The rebuild costs the writer a GET per target; with Ray.AuraSqlModule replication the connection is chosen by the HTTP method, so that GET reads the master |
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
| Cacheable at all | `onGet` reads nothing per user or per request, directly or through what it embeds and what `#[ResourceParam]` reads. One per-user read anywhere in the body → decline, or propose `#[DonutCache]` with that part as the hole. A child that is not cached but is the same for everyone (a random pick, an external API) may be frozen into the parent; say so in the proposal |
| `#[Cacheable]` vs `#[CacheableResponse]` vs `#[DonutCache]` | Same body for every client and no per-request part, kept as value/rendered state through `CacheInterceptor` → `#[Cacheable]`. A whole rendered page invalidated only by its writes, served through the donut interceptors → `#[CacheableResponse]`. Mixed stable/per-request page → `#[DonutCache]`. A resource another one embeds and reads as a value (a JSON page, or a parent reading its fields) → `#[Cacheable]`: a `#[CacheableResponse]` resource served from the cache has no `body`, so a JSON page embedding it renders that part as `null` |
| `type` | `view` only when a template exists and the rendered form is what every client receives; otherwise `value` |
| `expiry` / `expirySecond` / `expiryAt` | A write path exists that can announce the change → tags do the work; propose no TTL argument (default `never`) or a long floor, and say which. No write path but a known staleness budget → `expirySecond: N` with the budget stated. Body carries its own deadline → `expiryAt: '<field>'`, but only when that field cannot already be past on a fresh write - otherwise the entry silently never expires by TTL |
| `#[Refresh]` vs `#[Purge]` on each paired write | The target is one URI the writer can spell, the next read must be warm (the writer reads it back, a hot page), and rebuilding it is cheap → `#[Refresh]`. A broad target (a list, a virtual resource, many pages), a bust that is enough, or a re-read that would be wasted (the entity may be gone) → `#[Purge]` |
| Target URI | The read resource whose body the write changes, spelled with the writer's response body or its own URI query (body wins - `RefreshAnnotatedCommand::getUri()`). If neither carries the parameter the target needs (a delete that only knows its own id, a list keyed by something else) → report: invalidation is not spellable from here |
| Dependency shape | Parent composes exactly the children it depends on via `#[Embed]`, and each carries a tag (see the facts table) → nothing to add. `onGet` reads data itself → `#[DependsOn]` on `onGet`, or on the row class when the rows are objects; rows held as arrays have no class, so they are tagged with `UriTagInterface::fromAssoc()`. Every row adds a tag (about 24 KB for 1,000 rows, beyond Fastly's 16,384-byte header limit), so a long list depends on a virtual resource instead. Never a shared string key such as `latest_*`, or `invalidateTags()` in a write |

### 4. Pair every cacheable with its invalidation

This is the check the issue exists for. For every `#[Cacheable]`/`#[CacheableResponse]` you propose or that already exists:

- list the write methods from step 2 that change its data
- for each, state whether invalidation already reaches this resource (same-URI automatic on `onPost`/`onPut`/`onPatch`/`onDelete` of the same class; explicit `#[Refresh]`/`#[Purge]` elsewhere; embed propagation from a child that is itself invalidated)
- a write that changes the data and reaches nothing is the finding: **unpaired cacheable**. Report it as such even when the resource already carries `#[Cacheable]` today - an existing attribute is not evidence that it is right
- a handwritten invalidation is a finding even when it works: a shared string key (`latest_*`), a `Surrogate-Key` built by hand for what a declaration can say, `invalidateTags()` in a write or a service, or an interceptor that adds one key to every page. Propose the declaration that replaces it, and check that the replacement reaches every resource the key reached. `fromAssoc()` for rows held as arrays is not one of them
- a purge whose target nothing caches or reads is a finding too: it changes nothing, and it draws a dependency that does not exist

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

Applying the proposal changes behaviour that no unit test observes: a resource that stopped caching still answers correctly.

Before running anything, [qr-graph](https://github.com/bearsunday/BEAR.QueryRepositoryGraph) reads the declarations statically: `qr-graph impact <uri> --method=onPut` lists what a write reaches, `qr-graph dump` warns on a `#[DependsOn]` that no write invalidates or that names no resource, and a committed `qr-graph snapshot` shows every change to the graph in review. In 0.1 it does not see `#[DependsOn]` on row classes, what `#[ResourceParam]` reads, or what a template fetches, so a resource covered only that way looks uncovered.

A test pins one declaration: cache the reader, check that its `Surrogate-Key` carries the tag, and check that removing the declaration fails the test. When the same tag also reaches the reader by another path, removing one declaration fails nothing; check each reader on its own, not only the page.

After applying:

1. bind a real store and turn recording on ([bear-observe](https://github.com/bearsunday/BEAR.EventSourcing/blob/1.x/skills/bear-observe/SKILL.md), sections 1-2)
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
- `bear-clean-style/references/cache.md` - dependency shapes and their anti-patterns
- [docs/llms-full.txt](https://github.com/bearsunday/BEAR.QueryRepository/blob/1.x/docs/llms-full.txt), "Cache Dependencies" and "Donut Caching" - what the attributes do
