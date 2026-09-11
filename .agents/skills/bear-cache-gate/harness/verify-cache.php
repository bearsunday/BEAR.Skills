<?php

declare(strict_types=1);

/**
 * The oracle for the cache loop: exercise one flow and judge it from the log alone
 *
 * Usage: php var/loop/verify-cache.php <flow>
 *
 * A flow names a cached read and, when it has one, the write that must invalidate it. The script
 * boots the application with a real pool and recording on - neither is bound by default, so
 * without this the attributes are inert and every assertion below would pass vacuously - then
 * checks the invariants that a working cache has to satisfy:
 *
 *   1. the cold read stores something (`save_*` with `saved: true`)
 *   2. the parent's own read closes as a hit the second time
 *   3. an embedded child left the evidence the parent's declaration records - which evidence that
 *      is depends on the declaration, so the oracle reads the kind off the parent's saves first:
 *        - `#[Cacheable]` (`save_value`/`save_view`): a `depends_on` edge, and every child tag on
 *          the parent's save tags
 *        - `#[CacheableResponse]` (`save_donut_view`): no edge is emitted; the child's URI tag is
 *          on the parent's `save_etag`/`save_donut_view` tags
 *        - `#[DonutCache]` (`save_donut` only): nothing is recorded, by design - the page is
 *          recomposed on every read and the child's own entry decides its freshness
 *   4. the write announces the change - an `invalidate` that is not the writer's own marker-preceded
 *      cleanup - and its tags meet the parent's save tags (set intersection). The CDN outcome of
 *      that announcement is printed; `failed` is a violation, `skipped` means no purger is bound
 *   5. the parent's read after the invalidation serves new content: a miss for `#[Cacheable]`, and
 *      for a donut parent a hit carrying `refresh_donut` - its template survives on purpose
 *   6. no entry is saved with an empty tag list, which no invalidation can ever reach
 *
 * Exit code 0 means the log proved it. Non-zero prints which invariant failed and the tree.
 */

use BEAR\QueryRepository\Cdn\FastlyCacheControlHeaderSetter;
use BEAR\QueryRepository\CdnCacheControlHeaderSetterInterface;
use BEAR\QueryRepository\Exception\CacheStoreFailure;
use BEAR\QueryRepository\PurgerInterface;
use BEAR\QueryRepository\Log\SafeSemanticLogger;
use BEAR\QueryRepository\ProdQueryRepositoryModule;
use BEAR\QueryRepository\StorageRedisDsnModule;
use BEAR\QueryRepository\QueryRepositoryModule;
use BEAR\RepositoryModule\Annotation\CacheLog;
use BEAR\QueryRepository\QueryRepositoryInterface;
use BEAR\QueryRepository\UriScopedHttpCacheInterface;
use BEAR\QueryRepository\ResourceStorageInterface;
use BEAR\QueryRepository\UriTag;
use BEAR\Resource\ResourceInterface;
use BEAR\Sunday\Extension\Transfer\HttpCacheInterface;
use BEAR\Resource\Uri;
use Koriym\SemanticLogger\LogJson;
use Koriym\SemanticLogger\SemanticLoggerInterface;
use MyVendor\BeMart\Injector;
use Ray\Di\AbstractModule;
use Symfony\Component\Cache\Adapter\RedisAdapter;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

const FLOWS = [
    'help' => [
        'read' => 'page://self/help/about',
        'write' => null,
        'embeds' => false,
    ],
    // The agent reads the same corpus a shopper does, through its own resource. Purging the shared
    // tag is what says so: a TTL passes store-and-hit and fails nothing.
    'agent-catalog' => [
        'read' => 'app://self/agent/catalog',
        'write' => null,
        'purgeTags' => ['product-corpus'],
        'embeds' => false,
    ],
    'agent-product' => [
        'read' => 'app://self/agent/product?productCode=sample-001',
        'write' => null,
        'purgeTags' => ['product-corpus'],
        'embeds' => false,
    ],
    // The storefront list: the page is per-request (CSRF, sort, paging), the corpus it embeds is
    // the cached part. Reading the page proves the embedded resource is what fills and hits.
    'products-app' => [
        'read' => 'app://self/products',
        'write' => null,
        'embeds' => false,
    ],
    // An order header belongs to one customer: neither the page nor the resource it embeds may be
    // cached, and the flow asserts that nothing about the page is stored. Its child is uncached too,
    // so the "child answers from cache" half does not apply - this flow only pins the negative.
    'shopping-complete' => [
        'read' => 'page://self/shopping/complete?orderNo=past0000000000000000000000000001',
        'write' => null,
        'embeds' => false,
        'mode' => 'per-request',
        'childCached' => false,
    ],
    // Personal data: the assertion is that nothing about it is ever stored. Reading it twice must
    // leave the store as empty as it found it - an entry keyed by customer id would be handed to
    // whoever supplies the id.
    'customer-profile' => [
        'read' => 'app://self/customer/profile?customerId=0123456789abcdef0123456789abcdef',
        'write' => null,
        'embeds' => false,
        'mode' => 'per-request',
        'childCached' => false,
    ],
    // The write announcing itself: an admin edit invalidates the shared surrogate key, and every
    // cached variant of the corpus falls with it - a URI tag would only reach the exact query
    // string that produced an entry.
    'products-corpus-tag' => [
        'read' => 'app://self/products?nameKeyword=sample',
        'write' => null,
        'purgeTags' => ['product-corpus'],
        'embeds' => false,
    ],
    // The dependency case: master data embeds the number that moves. Purging the child is what an
    // admin write hook would do, and the parent has to fall with it.
    'product-stock' => [
        'read' => 'app://self/product?productCode=sample-001',
        'write' => null,
        'purge' => 'app://self/product/stock?productCode=sample-001',
        'embeds' => true,
    ],
    // A page that carries a CSRF token must NOT be cached; what it must do is hit the child it
    // embeds. Caching it would hand one shopper's token to the next.
    'products-page' => [
        'read' => 'page://self/products',
        'write' => null,
        'embeds' => false,
        'mode' => 'per-request',
    ],
    // The store is down - a Redis restart, a network partition - and the shop has to keep selling.
    // The framework's contract is that a cache failure costs latency, not the response: the
    // resource runs, the page is served, and the log says the cache was the thing that failed.
    'help-cache-down' => [
        'read' => 'page://self/help/about',
        'write' => null,
        'embeds' => false,
        'mode' => 'cache-down',
        // lazy=1 on purpose: the scenario is a store that cannot be reached when a request needs it,
        // not one that refuses to be configured. Without it symfony/cache connects eagerly and the
        // injector throws before any read happens.
        'dsn' => 'redis://127.0.0.1:1?lazy=1',
    ],
    // The edge's copy. A CDN is told what to keep and, later, what to drop: the two have to name
    // the same thing, or the edge serves a page the origin has already replaced - and nothing in
    // the origin's own cache can show it.
    'help-cdn' => [
        'read' => 'page://self/help/about',
        'write' => null,
        'embeds' => false,
        'mode' => 'cdn',
    ],
    // The client's copy, revalidated. A cached page hands out an ETag, and the next request offers
    // it back: the answer must be 304 while the entry stands and 200 once it does not. Answering
    // 304 about content that changed freezes that client on it - a browser will not ask again.
    'help-revalidate' => [
        'read' => 'page://self/help/about',
        'write' => null,
        'embeds' => false,
        'mode' => 'revalidate',
    ],
];

/**
 * The CDN, as far as the origin can see it
 *
 * A real purge leaves the process; what matters to the judgement is what the origin decided to
 * send. Static because Ray.Di hands the same instance to the storage while the script keeps a
 * reference for the assertions.
 */
final class RecordingPurger implements PurgerInterface
{
    /** @var list<string> */
    public array $purged = [];

    private static self|null $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function __invoke(string $tag): void
    {
        $this->purged[] = $tag;
    }
}

$flowName = $argv[1] ?? '';
if (! isset(FLOWS[$flowName])) {
    fwrite(STDERR, sprintf("unknown flow '%s'; known: %s\n", $flowName, implode(', ', array_keys(FLOWS))));
    exit(2);
}

$flow = FLOWS[$flowName];

// The oracle owns its cold state, or "cold read" is a lie. The default context is the one
// verify-all.sh exports, so running this script directly clears the same store the gate does.
$context = getenv('APP_CONTEXT') ?: 'cli-fake-hal-app';
$dsnForFlush = getenv('CACHE_DSN') ?: '';
if ($dsnForFlush !== '') {
    // Through symfony, not a client class: the app declares no redis client, and the pools this
    // oracle judges are built the same way - ext-redis when it is there, predis when it is not.
    RedisAdapter::createConnection($dsnForFlush)->flushall();
} else {
    exec('rm -rf ' . escapeshellarg(dirname(__DIR__) . '/tmp/' . $context . '/cache'));
}

$dsn = $flow['dsn'] ?? (getenv('CACHE_DSN') ?: '');
$override = new class ($dsn, ($flow['mode'] ?? '') === 'cdn') extends AbstractModule {
    public function __construct(private string $dsn, private bool $cdn)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Pools only. Installing a log module here would bring a second QueryRepositoryModule with
        // it, and Ray.Aop accumulates pointcuts: the application already installed one through
        // PackageModule, so every cache interceptor would run twice - two lookups, two writes per
        // request, and a log that shows the same URI nested inside itself.
        $this->install($this->dsn === ''
            ? new ProdQueryRepositoryModule()
            : new StorageRedisDsnModule($this->dsn));
        // Recording on, by replacing the one binding that decides it. No sink: this script
        // flushes every session itself, and a sink would drain them somewhere else.
        $this->bind(SemanticLoggerInterface::class)->annotatedWith(CacheLog::class)
            ->toInstance(new SafeSemanticLogger());

        if ($this->cdn) {
            // The CDN flavour BeMart would deploy behind, with the purge recorded instead of sent.
            $this->bind(CdnCacheControlHeaderSetterInterface::class)->to(FastlyCacheControlHeaderSetter::class);
            $this->bind(PurgerInterface::class)->toInstance(RecordingPurger::instance());
        }
    }
};

// Context is a parameter: the sql contexts talk to a database that may hold no products, the
// fake contexts carry a deterministic corpus. A cache is indifferent to which, the assertions are
// not - so the store cleared above and the injector built here have to be the same one.
$injector = Injector::getOverrideInstance($context, $override);
$resource = $injector->getInstance(ResourceInterface::class);
$logger = $injector->getInstance(SemanticLoggerInterface::class, CacheLog::class);


/** @return list<array{type: string, context: array<string, mixed>}> */
function flatten(LogJson $log): array
{
    $flat = [];
    $walk = static function (array $node) use (&$walk, &$flat): void {
        $flat[] = ['type' => (string) $node['type'], 'context' => (array) ($node['context'] ?? [])];
        foreach ((array) ($node['open'] ?? []) as $child) {
            $walk((array) $child);
        }

        foreach ((array) ($node['events'] ?? []) as $event) {
            $walk((array) $event);
        }

        if (isset($node['close'])) {
            $walk((array) $node['close']);
        }
    };
    $tree = $log->toArray();
    foreach ((array) ($tree['open'] ?? []) as $node) {
        $walk((array) $node);
    }

    foreach ((array) ($tree['events'] ?? []) as $node) {
        $walk((array) $node);
    }

    return $flat;
}

/** @param list<array{type: string, context: array<string, mixed>}> $entries */
function typesOf(array $entries): array
{
    return array_map(static fn (array $e): string => $e['type'], $entries);
}

/**
 * @param list<array{type: string, context: array<string, mixed>}> $entries
 * @param string|null                                              $uri     Only what this URI emitted; a nested child's saves are not the parent's
 */
function tagsOf(array $entries, string $prefix, string|null $uri = null): array
{
    $tags = [];
    foreach ($entries as $entry) {
        if (! str_starts_with($entry['type'], $prefix)) {
            continue;
        }

        if ($uri !== null && ($entry['context']['uri'] ?? null) !== $uri) {
            continue;
        }

        foreach ((array) ($entry['context']['tags'] ?? []) as $tag) {
            $tags[] = (string) $tag;
        }
    }

    return array_values(array_unique($tags));
}

/**
 * The save events this URI emitted itself
 *
 * @param list<array{type: string, context: array<string, mixed>}> $entries
 *
 * @return list<string>
 */
function savesOf(array $entries, string $uri): array
{
    $saves = [];
    foreach ($entries as $entry) {
        if (str_starts_with($entry['type'], 'save_') && ($entry['context']['uri'] ?? null) === $uri) {
            $saves[] = $entry['type'];
        }
    }

    return array_values(array_unique($saves));
}

/**
 * Which cache declaration the parent read under, from what it stored
 *
 * The declaration decides what a dependency looks like in the log, and the attribute is not in
 * the log: only the saves name it. `#[CacheableResponse]` and `#[DonutCache]` both open with
 * `put_donut`, and the second stores no page entry at all - `save_donut_view` is what separates
 * them. Null means the parent stored nothing, so there is nothing to judge a dependency against.
 *
 * @param list<string> $saves the parent's own save types, from savesOf()
 *
 * @return 'cacheable'|'cacheable-response'|'donut-cache'|null
 */
function parentKindOf(array $saves): string|null
{
    if (in_array('save_donut_view', $saves, true)) {
        return 'cacheable-response';
    }

    if (in_array('save_donut', $saves, true)) {
        return 'donut-cache';
    }

    if (array_intersect(['save_value', 'save_view'], $saves) !== []) {
        return 'cacheable';
    }

    return null;
}

/**
 * The `get` scope this URI opened, depth first
 *
 * @param array<string, mixed> $tree LogJson::toArray(), or a node of it
 *
 * @return array<string, mixed>|null
 */
function scopeOf(array $tree, string $uri): array|null
{
    foreach ((array) ($tree['open'] ?? []) as $child) {
        $node = (array) $child;
        $context = (array) ($node['context'] ?? []);
        if ((string) ($node['type'] ?? '') === 'get' && ($context['uri'] ?? null) === $uri) {
            return $node;
        }

        $found = scopeOf($node, $uri);
        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * The close of the `get` scope this URI opened
 *
 * A read nests the children it embeds, so a session-wide `cache_hit` may be a child's answer and
 * not the parent's: a close belongs to a scope, and only the tree keeps that.
 *
 * @param array<string, mixed> $tree LogJson::toArray()
 *
 * @return array{type: string, context: array<string, mixed>}|null
 */
function closeOf(array $tree, string $uri): array|null
{
    $scope = scopeOf($tree, $uri);
    $close = (array) ($scope['close'] ?? []);

    return $close === [] ? null : ['type' => (string) ($close['type'] ?? ''), 'context' => (array) ($close['context'] ?? [])];
}

/**
 * The URIs of the `get` scopes nested directly under this URI's own: the children it embeds
 *
 * @param array<string, mixed> $tree LogJson::toArray()
 *
 * @return list<string>
 */
function childUrisOf(array $tree, string $uri): array
{
    $scope = scopeOf($tree, $uri);
    $uris = [];
    foreach ((array) ($scope['open'] ?? []) as $child) {
        $node = (array) $child;
        $childUri = (string) (((array) ($node['context'] ?? []))['uri'] ?? '');
        if ((string) ($node['type'] ?? '') === 'get' && $childUri !== '') {
            $uris[] = $childUri;
        }
    }

    return array_values(array_unique($uris));
}

/**
 * The invalidations that announce a change, without the writer clearing its own entry
 *
 * Same rule as the library's retention policy: an `invalidate` is cleanup iff the event
 * immediately before it *in the same scope* is `pre_write_cleanup`. Matched on the tree, not
 * on flatten()'s output - flatten emits a scope's nested opens before its own events, so flat
 * adjacency would misjudge an `invalidate` that sits inside a nested `manual_invalidate`. A
 * write whose only invalidation is cleanup has told nobody about the change.
 *
 * @param array<string, mixed> $tree LogJson::toArray()
 *
 * @return list<array{type: string, context: array<string, mixed>}>
 */
function realInvalidations(array $tree): array
{
    $real = [];
    $walk = static function (array $node) use (&$walk, &$real): void {
        $previous = null;
        foreach ((array) ($node['events'] ?? []) as $event) {
            $event = (array) $event;
            $type = (string) ($event['type'] ?? '');
            if ($type === 'invalidate' && $previous !== 'pre_write_cleanup') {
                $real[] = ['type' => $type, 'context' => (array) ($event['context'] ?? [])];
            }

            $previous = $type;
        }

        foreach ((array) ($node['open'] ?? []) as $child) {
            $walk((array) $child);
        }
    };
    $walk($tree);

    return $real;
}

/**
 * Violations already tracked upstream, so the loop fails on new ones only
 *
 * Each entry names the substring and the issue that owns it. Remove an entry when its issue
 * closes - if the defect is still there, the next run says so.
 */
const KNOWN = [
    // #185 (a donut template stored untagged) is fixed and its entry is gone: an entry stays here
    // only while its issue is open, so a defect that comes back fails the next run.
];

/**
 * A violation the loop already knows about carries its issue instead of failing the run
 *
 * @param list<string> $violations
 * @param list<string> $known
 */
function record(string $violation, array &$violations, array &$known): void
{
    foreach (KNOWN as $needle => $tracked) {
        if (str_contains($violation, $needle)) {
            $known[] = $violation . ' [' . $tracked . ']';

            return;
        }
    }

    $violations[] = $violation;
}

$violations = [];
$known = [];
$sessions = [];
// The same sessions unflattened - what closeOf() and childUrisOf() match on
$trees = [];

$codes = [];
$read = static function (string $label) use ($resource, $logger, $flow, &$sessions, &$trees, &$codes): array {
    $ro = $resource->get($flow['read']);
    $codes[$label] = $ro->code;
    $log = $logger->flush();
    $entries = flatten($log);
    $sessions[$label] = $entries;
    $trees[$label] = $log->toArray();

    return $entries;
};

$cold = $read('cold read');
$types = typesOf($cold);
$mode = $flow['mode'] ?? 'cached';

// An empty log is the expected proof for an uncacheable flow, and it is also what a typo in the
// URI produces. The response code separates the two: nothing here judges a request that failed.
if ($codes['cold read'] !== 200) {
    printf("FAIL %s\n  - 0: the read returned %d, so nothing below is evidence of anything\n", $flowName, $codes['cold read']);

    exit(1);
}

if ($mode === 'cache-down') {
    // The response is the point: a shop whose cache is down is slow, not closed.
    $types = typesOf($cold);
    // Two shapes carry a failing store: `cache_error` when the exception reached this package, and
    // `pool_error` when the adapter swallowed it - which is what symfony/cache does.
    $errors = array_values(array_filter($cold, static fn (array $e): bool => in_array($e['type'], ['cache_error', 'pool_error'], true)));

    if ($errors === []) {
        record('21: the store is unreachable and the log says nothing about it - a silent degrade is an outage nobody can attribute', $violations, $known);
    }

    $sides = array_values(array_unique(array_map(static fn (array $e): string => (string) ($e['context']['operation'] ?? '?'), $errors)));
    if (! in_array('read', $sides, true)) {
        record(sprintf('22: no failing read was recorded (sides: %s) - the lookup is what a request hits first', json_encode($sides)), $violations, $known);
    }

    if (! in_array('cache_miss', $types, true)) {
        record('23: the failed lookup did not close as a miss - a reader cannot tell a broken pool from a cold one', $violations, $known);
    }

    // Twice, because the second request must not depend on the first having stored anything.
    $second = $read('second read');
    if ($codes['second read'] !== 200) {
        record(sprintf('24: the second read returned %d - the degrade is not repeatable', $codes['second read']), $violations, $known);
    }

    if (in_array('cache_hit', typesOf($second), true)) {
        record('25: a hit was reported while the store was unreachable', $violations, $known);
    }

    foreach ($sessions as $label => $entries) {
        printf("%-22s %s\n", $label, implode(' ', typesOf($entries)));
    }

    printf("%-22s %s\n", 'failing sides', json_encode($sides));

    // The other half of the contract, and the one that was visible before pool_error existed: a
    // write to a store that cannot be reached has to say it did not happen.
    foreach ($cold as $entry) {
        if (! str_starts_with($entry['type'], 'save_')) {
            continue;
        }

        $saved = $entry['context']['saved'] ?? null;
        printf("%-22s %s saved=%s\n", 'write outcome', $entry['type'], var_export($saved, true));
        if ($saved !== false) {
            record(sprintf('26: %s reports saved=%s while the store is unreachable - the log is claiming a write that cannot have happened', $entry['type'], var_export($saved, true)), $violations, $known);
        }
    }

    foreach (array_unique($known) as $entry) {
        printf("KNOWN %s\n", $entry);
    }

    if ($violations === []) {
        printf("\nOK %s: the store is down, the page is served, and the log names the cache as what failed\n", $flowName);

        exit(0);
    }

    printf("\nFAIL %s\n", $flowName);
    foreach ($violations as $violation) {
        printf("  - %s\n", $violation);
    }

    exit(1);
}

if ($mode === 'cdn') {
    // What the edge was told to keep.
    $told = null;
    foreach ($cold as $entry) {
        if ($entry['type'] === 'cdn_headers' && ($entry['context']['uri'] ?? '') === $flow['read']) {
            $told = $entry['context'];
        }
    }

    if ($told === null) {
        printf("FAIL %s\n  - 15: the response went out with no CDN headers recorded, so nothing says what the edge holds\n", $flowName);

        exit(1);
    }

    /** @var list<string> $keptUnder */
    $keptUnder = array_map('strval', (array) ($told['surrogateKeys'] ?? []));
    $lifetime = array_filter(
        (array) ($told['headers'] ?? []),
        static fn (string $name): bool => in_array(strtolower($name), ['surrogate-control', 'cache-control', 'edge-control'], true),
        ARRAY_FILTER_USE_KEY,
    );

    if ($keptUnder === []) {
        record('16: the edge was given no surrogate key - nothing the origin invalidates can reach it', $violations, $known);
    }

    if ($lifetime === []) {
        record('17: the edge was given no lifetime directive - it decides for itself how long to keep the page', $violations, $known);
    }

    // A write drops the edge's copy too. With a year-long Surrogate-Control the purge is the only
    // way a change reaches the edge, so the write path issuing one is load-bearing, not noise.
    $sentOnWrite = RecordingPurger::instance()->purged;
    if (array_intersect($keptUnder, $sentOnWrite) === []) {
        record(sprintf(
            '20: the write told the edge to keep %s without dropping the copy it replaced (sent %s) - with a %s lifetime the edge serves the old page until it lapses',
            json_encode($keptUnder),
            json_encode($sentOnWrite),
            json_encode(array_values($lifetime)),
        ), $violations, $known);
    }

    // What the edge is later told to drop.
    RecordingPurger::instance()->purged = [];
    $repository = $injector->getInstance(QueryRepositoryInterface::class);
    $repository->purge(new Uri($flow['read']));
    $sessions['purge the page'] = flatten($logger->flush());

    $sent = RecordingPurger::instance()->purged;
    $cdnOutcome = null;
    foreach ($sessions['purge the page'] as $entry) {
        if ($entry['type'] === 'invalidate') {
            $cdnOutcome = (string) ($entry['context']['cdn'] ?? '');
        }
    }

    if ($cdnOutcome !== 'purged') {
        record(sprintf('18: the log reports the CDN as %s for a purge that has to reach it', var_export($cdnOutcome, true)), $violations, $known);
    }

    // The two lists have to meet: a key the edge holds and never hears about again is stale forever.
    if (array_intersect($keptUnder, $sent) === []) {
        record(sprintf(
            '19: the edge keeps %s and was told to drop %s - the two never meet, so the edge serves the old page until its own lifetime runs out',
            json_encode($keptUnder),
            json_encode($sent),
        ), $violations, $known);
    }

    foreach ($sessions as $label => $entries) {
        printf("%-22s %s\n", $label, implode(' ', typesOf($entries)));
    }

    printf(
        "%-22s keep=%s drop-on-write=%s drop-on-purge=%s lifetime=%s\n",
        'cdn',
        json_encode($keptUnder),
        json_encode($sentOnWrite),
        json_encode($sent),
        json_encode($lifetime),
    );

    foreach (array_unique($known) as $entry) {
        printf("KNOWN %s\n", $entry);
    }

    if ($violations === []) {
        printf("\nOK %s: the edge was told what to keep, and the purge names the same thing\n", $flowName);

        exit(0);
    }

    printf("\nFAIL %s\n", $flowName);
    foreach ($violations as $violation) {
        printf("  - %s\n", $violation);
    }

    exit(1);
}

if ($mode === 'revalidate') {
    // The client's copy of this page, and what the app says when it offers it back.
    $etag = null;
    foreach ($cold as $entry) {
        if ($entry['type'] === 'save_etag' && ($entry['context']['uri'] ?? '') === $flow['read']) {
            $etag = (string) $entry['context']['etag'];
        }
    }

    if ($etag === null) {
        printf("FAIL %s\n  - 10: the page stored no validator, so a client has nothing to revalidate with\n", $flowName);

        exit(1);
    }

    $httpCache = $injector->getInstance(HttpCacheInterface::class);
    $read = $flow['read'];
    // The scoped question, the way an application asks it after routing: this validator, for this
    // resource. The unscoped one cannot tell whose validator it is.
    $ask = static function (string $label, string $offered) use ($httpCache, $logger, &$sessions, $read): bool {
        $answer = $httpCache instanceof UriScopedHttpCacheInterface
            ? $httpCache->isNotModifiedFor(new Uri($read), ['HTTP_IF_NONE_MATCH' => $offered])
            : $httpCache->isNotModified(['HTTP_IF_NONE_MATCH' => $offered, 'REQUEST_URI' => '/help/about']);
        $sessions[$label] = flatten($logger->flush());

        return $answer;
    };

    if (! $ask('offer the etag', $etag)) {
        $violations[] = '11: the validator the page just handed out does not revalidate - every client refetches the whole page';
    }

    if ($ask('offer a stale etag', '"0000000000000000000000000000000000000000"')) {
        $violations[] = '12: an unknown validator was answered 304 - the client keeps content the server never saw';
    }

    $repository = $injector->getInstance(QueryRepositoryInterface::class);
    $repository->purge(new Uri($flow['read']));
    $sessions['purge the page'] = flatten($logger->flush());

    if ($ask('offer it after the purge', $etag)) {
        $violations[] = '13: the purged page still answers 304 to the old validator - a revalidating client is frozen on content that is gone';
    }

    // Another live page's validator, offered for this one. HTTP scopes an entity-tag to the
    // resource it came from; the ETag pool is a set of live validators, so the answer is 304 for
    // any URI. A client that returns the validator it was given cannot reach this - the value is
    // derived from the URI - but a proxy or client that mixes them is served the wrong page.
    $other = $resource->get('page://self/help/privacy');
    $otherEtag = (string) ($other->headers['ETag'] ?? '');
    $logger->flush();
    if ($otherEtag !== '' && $ask('offer another page validator', $otherEtag)) {
        record("14: another page's validator was answered 304 for this URI - the 304 decision is not scoped to the resource", $violations, $known);
    }

    foreach ($sessions as $label => $entries) {
        printf("%-22s %s\n", $label, implode(' ', typesOf($entries)));
    }

    foreach (array_unique($known) as $entry) {
        printf("KNOWN %s\n", $entry);
    }

    if ($violations === []) {
        printf("\nOK %s: the validator revalidates, an unknown one does not, and the purge ends it\n", $flowName);

        exit(0);
    }

    printf("\nFAIL %s\n", $flowName);
    foreach ($violations as $violation) {
        printf("  - %s\n", $violation);
    }

    exit(1);
}

if ($mode === 'per-request') {
    // 8. the response is assembled per request: nothing about it may be stored, and the child it
    // embeds has to be the thing that fills and answers
    $ownSaves = array_filter(
        $cold,
        static fn (array $e): bool => str_starts_with($e['type'], 'save_') && ($e['context']['uri'] ?? '') === $flow['read'],
    );
    if ($ownSaves !== []) {
        $violations[] = sprintf('8: %s is assembled per request, but the log stores it', $flow['read']);
    }

    $warmPerRequest = $read('second read');
    if (($flow['childCached'] ?? true) && ! in_array('cache_hit', typesOf($warmPerRequest), true)) {
        $violations[] = '8: the embedded resource is not answering from cache through this page';
    }

    if (($flow['childCached'] ?? true) === false && $warmPerRequest !== [] && array_filter(
        $warmPerRequest,
        static fn (array $e): bool => str_starts_with($e['type'], 'save_'),
    ) !== []) {
        $violations[] = '8: this flow is customer-specific, yet something in it was stored';
    }

    foreach ($sessions as $label => $entries) {
        printf("%-18s %s\n", $label, implode(' ', typesOf($entries)));
    }

    foreach (array_unique($known) as $entry) {
        printf("KNOWN %s\n", $entry);
    }

    if ($violations === []) {
        printf(
            "\nOK %s: 200 and nothing about the page stored%s\n",
            $flowName,
            ($flow['childCached'] ?? true) ? ', its child answering from cache' : ' - the flow is customer-specific, so nothing in it is',
        );
        exit(0);
    }

    printf("\nFAIL %s\n", $flowName);
    foreach ($violations as $violation) {
        printf("  - %s\n", $violation);
    }

    exit(1);
}

// 1. the cold read stored something
$saves = array_values(array_filter($cold, static fn (array $e): bool => str_starts_with($e['type'], 'save_')));
if ($saves === []) {
    $violations[] = '1: the cold read wrote nothing (no save_* event)';
}

foreach ($saves as $save) {
    if (($save['context']['saved'] ?? null) === false) {
        $violations[] = sprintf('1: %s reports saved: false - the pool refused the entry', $save['type']);
    }

    // 6. an entry with no tags is unreachable by any invalidation
    if (($save['context']['tags'] ?? null) === []) {
        record(sprintf('6: %s saved with an empty tag list - no invalidation can reach it', $save['type']), $violations, $known);
    }
}

$parentSaves = savesOf($cold, $flow['read']);
$parentKind = parentKindOf($parentSaves);
$donutParent = in_array($parentKind, ['cacheable-response', 'donut-cache'], true);
printf("%-18s %s (%s)\n", 'parent kind', $parentKind ?? 'unknown', $parentSaves === [] ? 'the parent stored nothing' : implode(' ', $parentSaves));

// 3. an embedded child has to leave the evidence its parent's declaration records
if ($flow['embeds'] && $parentKind === 'cacheable') {
    $childTags = [];
    foreach ($cold as $entry) {
        if ($entry['type'] !== 'depends_on' || ($entry['context']['parent'] ?? null) !== $flow['read']) {
            continue;
        }

        foreach ((array) ($entry['context']['childTags'] ?? []) as $tag) {
            $childTags[] = (string) $tag;
        }
    }

    if ($childTags === []) {
        $violations[] = '3: no depends_on edge - the parent is not stored under its children tags';
    }

    $parentTags = tagsOf($cold, 'save_', $flow['read']);
    $absent = array_values(array_diff($childTags, $parentTags));
    if ($absent !== []) {
        $violations[] = sprintf(
            '3: the child tags %s are absent from the parent save tags %s',
            json_encode($absent),
            json_encode($parentTags),
        );
    }
}

if ($flow['embeds'] && $parentKind === 'cacheable-response') {
    // No depends_on here: the donut writer never calls CacheDependency. The child's URI tag
    // riding on the page's own save tags is the whole record of the dependency.
    $viewTags = array_values(array_unique([
        ...tagsOf($cold, 'save_etag', $flow['read']),
        ...tagsOf($cold, 'save_donut_view', $flow['read']),
    ]));
    // Direct children only: what a grandchild reaches is the child's own entry to answer for. A
    // child that is no cache target opens no scope, so an empty list is not a missing dependency.
    $childTags = array_map(static fn (string $uri): string => (new UriTag())(new Uri($uri)), childUrisOf($trees['cold read'], $flow['read']));
    if ($childTags === []) {
        printf("%-18s %s\n", 'dependency', 'no child opened a get scope, so the log names no child tag to look for');
    }

    $absent = array_values(array_diff($childTags, $viewTags));
    if ($absent !== []) {
        $violations[] = sprintf(
            '3: the child tags %s are absent from the parent save tags %s',
            json_encode($absent),
            json_encode($viewTags),
        );
    }
}

if ($flow['embeds'] && $parentKind === 'donut-cache') {
    printf("%-18s %s\n", 'dependency', 'a putDonut page records none: it is recomposed on every read, and the child entry decides the child');
}

if ($flow['embeds'] && $parentKind === null) {
    printf("%-18s %s\n", 'dependency', 'not judged: the parent stored nothing, so no declaration can be read off it');
}

/**
 * What the parent's own read says after an invalidation, and the evidence behind it
 *
 * A donut parent closes as a hit even when it rebuilt - its template survives the child's
 * invalidation on purpose - so `refresh_donut` is what separates a recomposed page from a
 * replayed one. `unknown` is a parent that opened no scope at all: nothing hit, so nothing here
 * is stale.
 *
 * @return 'rebuilt'|'stale'|'template-gone'|'unknown'
 */
$verdictOn = static function (string $label) use (&$trees, &$sessions, $flow, $donutParent): string {
    $close = closeOf($trees[$label] ?? [], $flow['read']);
    if ($close === null) {
        printf("%-18s %s: no get scope for the parent\n", 'parent close', $label);

        return 'unknown';
    }

    $evidence = array_values(array_unique(array_map(
        static fn (array $e): string => $e['type'],
        array_filter(
            $sessions[$label] ?? [],
            static fn (array $e): bool => in_array($e['type'], ['refresh_donut', 'save_donut_view'], true) && ($e['context']['uri'] ?? null) === $flow['read'],
        ),
    )));
    printf(
        "%-18s %s: %s{%s}%s\n",
        'parent close',
        $label,
        $close['type'],
        (string) ($close['context']['layer'] ?? ''),
        $evidence === [] ? '' : ' ' . implode(' ', $evidence),
    );

    if (! $donutParent) {
        return $close['type'] === 'cache_miss' ? 'rebuilt' : 'stale';
    }

    if ($close['type'] === 'cache_miss') {
        printf("%-18s %s\n", 'dependency', 'the invalidation reached the template too, so the page was rebuilt from scratch');

        return 'template-gone';
    }

    return in_array('refresh_donut', $evidence, true) ? 'rebuilt' : 'stale';
};

// 2. the second read is a hit - the parent's own, not a child's
$read('warm read');
$warmClose = closeOf($trees['warm read'], $flow['read']);
if ($warmClose === null || $warmClose['type'] !== 'cache_hit') {
    $violations[] = '2: the second read is not a hit';
}

// A putDonut page stores no page entry, so it has no tag set for an invalidation to meet: the
// intersections below would judge the absence of a design rather than a defect.
$storesPage = $parentKind !== 'donut-cache';

$purgeTags = $flow['purgeTags'] ?? null;
if ($purgeTags !== null) {
    // The application announcing a change, the way a Be Final does: a direct invalidateTags()
    $injector->getInstance(ResourceStorageInterface::class)->invalidateTags($purgeTags);
    $tagEntries = flatten($logger->flush());
    $sessions['invalidate tag'] = $tagEntries;

    $savedTags = tagsOf($cold, 'save_', $flow['read']);
    if ($storesPage && array_intersect($purgeTags, $savedTags) === []) {
        $violations[] = sprintf(
            '4: the announced tags %s are absent from what the read stored %s',
            json_encode($purgeTags),
            json_encode($savedTags),
        );
    }

    $read('read after invalidate');
    $verdict = $verdictOn('read after invalidate');
    if ($verdict === 'stale') {
        $violations[] = $donutParent
            ? '5: the parent served its stored view after its tag was invalidated - stale content'
            : '5: the entry still hits after its tag was invalidated - stale content';
    }
}

$purgeUri = $flow['purge'] ?? null;
if ($purgeUri !== null) {
    // A direct purge, the way an admin write hook would invalidate: it opens a manual_purge scope
    $injector->getInstance(QueryRepositoryInterface::class)->purge(new Uri($purgeUri));
    $purgeEntries = flatten($logger->flush());
    $sessions['purge child'] = $purgeEntries;

    $savedTags = tagsOf($cold, 'save_', $flow['read']);
    $purgedTags = tagsOf($purgeEntries, 'invalidate');
    // 4. the purge has to reach a tag the parent was stored under
    if ($storesPage && array_intersect($purgedTags, $savedTags) === []) {
        $violations[] = sprintf(
            '4: purging the child invalidated %s, which does not meet the parent read tags %s',
            json_encode($purgedTags),
            json_encode($savedTags),
        );
    }

    // 5. the parent must serve new content
    $read('read after purge');
    $verdict = $verdictOn('read after purge');
    if ($verdict === 'stale') {
        $violations[] = $donutParent
            ? '5: the parent served its stored view after its child was purged - stale content'
            : '5: the parent still hits after its child was purged - stale content';
    }

    // A donut page is recomposed from whatever the child answers, so the child answering from its
    // own stale entry is the page's staleness - and the parent's close cannot show it.
    $childClose = $donutParent && ($flow['childCached'] ?? true) ? closeOf($trees['read after purge'], $purgeUri) : null;
    if ($childClose !== null && $childClose['type'] !== 'cache_miss') {
        $violations[] = '5: the child still hits after being purged - the page recomposes from stale child content';
    }
}

if ($flow['write'] !== null) {
    // A CDN purge failure is fail-closed: the local pools are cleared, `invalidate{cdn: failed}`
    // is recorded, then the store failure leaves invalidateTags() and nothing on a command path
    // catches it - so it surfaces here, not in the log alone.
    $purgeFailed = null;
    try {
        $resource->put($flow['write']);
    } catch (CacheStoreFailure $purgeFailed) {
    }

    $writeLog = $logger->flush();
    $writeEntries = flatten($writeLog);
    $sessions['write'] = $writeEntries;
    $trees['write'] = $writeLog->toArray();

    $announced = realInvalidations($trees['write']);
    $invalidateTags = tagsOf($announced, 'invalidate');
    $savedTags = tagsOf($cold, 'save_', $flow['read']);
    // 4. the write has to announce the change, and announce a tag the parent was stored under
    if ($announced === [] && in_array('invalidate', typesOf($writeEntries), true)) {
        $violations[] = '28: the write only cleaned up its own entry (marker-preceded invalidate) - nothing announced the change, the parent stays warm until its TTL';
    } elseif ($storesPage && array_intersect($invalidateTags, $savedTags) === []) {
        $violations[] = sprintf(
            '4: the write invalidated %s, which does not meet the read tags %s',
            json_encode($invalidateTags),
            json_encode($savedTags),
        );
    }

    // The local pools and the edge are two targets; a local pass with a failed purge is still stale at the edge
    foreach ($announced as $entry) {
        printf("%-18s %s\n", 'cdn on write', (string) ($entry['context']['cdn'] ?? ''));
    }

    if ($purgeFailed !== null) {
        $violations[] = sprintf(
            '29: the CDN purge failed (%s) - the local pools are clean and the edge is still serving the old page',
            $purgeFailed->getMessage(),
        );
    }

    // 5. the read after the write must serve new content
    $read('read after write');
    $verdict = $verdictOn('read after write');
    if ($verdict === 'stale') {
        $violations[] = $donutParent
            ? '5: the parent served its stored view after the write - stale content is being served'
            : '5: the read after the write is a hit - stale content is being served';
    }
}

foreach ($sessions as $label => $entries) {
    printf("%-18s %s\n", $label, implode(' ', typesOf($entries)));
}

// 6b. what the cache is worth: the close of each read carries what that answer cost. The sign is
// the invariant - a hit that is not cheaper than a miss is a cache paid for and not used. The
// difference is not "saved": a miss includes the write it triggered.
$costOf = static function (array $entries, string $type): float|null {
    foreach ($entries as $entry) {
        if ($entry['type'] === $type && ($entry['context']['layer'] ?? null) !== 'donut') {
            $duration = $entry['context']['durationMs'] ?? null;

            return is_float($duration) || is_int($duration) ? (float) $duration : null;
        }
    }

    return null;
};

$missCost = $costOf($cold, 'cache_miss');
$hitCost = $costOf($sessions['warm read'] ?? [], 'cache_hit');
if ($missCost !== null && $hitCost !== null) {
    printf("%-18s miss=%.3fms hit=%.3fms\n", 'cost', $missCost, $hitCost);
    if ($hitCost >= $missCost) {
        record(sprintf(
            '27: the hit cost %.3fms against a miss of %.3fms - this cache is paid for and not used',
            $hitCost,
            $missCost,
        ), $violations, $known);
    }
}

// 7. what the store actually did with the lifetime the log claims
if ($dsn !== '') {
    $client = RedisAdapter::createConnection($dsn);
    /** @var list<string> $keys */
    $keys = $client->keys('*');
    $claimsNoExpiry = [];
    foreach ($sessions['cold read'] as $entry) {
        if (str_starts_with($entry['type'], 'save_') && in_array($entry['context']['requestedTtl'] ?? null, [null, 0], true)) {
            $claimsNoExpiry[] = $entry['type'];
        }
    }

    foreach ($keys as $key) {
        $ttl = (int) $client->ttl($key);
        if ($claimsNoExpiry !== [] && $ttl > 0) {
            // Not a defect: requestedTtl is what this package asked for, and the backend decides.
            // Worth printing because an operator reading "no expiry" still has entries expiring.
            $known[] = sprintf(
                '7: %s asked for no expiry; the store gave %s a TTL of %d seconds [backend floor]',
                implode('/', array_unique($claimsNoExpiry)),
                $key,
                $ttl,
            );
            break;
        }
    }

    // The declaration, against what the store will really do with it. `requestedTtl` cannot ask
    // this question: `expiry: never` resolves to a finite number, so an entry meant to live until
    // invalidation looks like a deliberate 1-year TTL both in the log and in the store.
    foreach ($sessions['cold read'] as $entry) {
        if ($entry['type'] !== 'cache_policy' || ($entry['context']['expiry'] ?? null) !== 'never') {
            continue;
        }

        $ttls = array_filter(array_map(static fn (string $key): int => (int) $client->ttl($key), $keys), static fn (int $ttl): bool => $ttl > 0);
        if ($ttls === []) {
            continue;
        }

        $known[] = sprintf(
            '9: %s declares expiry=never - until invalidation - and the store drops its entries in %d..%d seconds [backend floor]',
            $entry['context']['uri'],
            min($ttls),
            max($ttls),
        );
        break;
    }
}

foreach (array_unique($known) as $entry) {
    printf("KNOWN %s\n", $entry);
}

if ($violations === []) {
    printf(
        "\nOK %s: the log proves store, hit%s\n",
        $flowName,
        $flow['write'] !== null || ($flow['purge'] ?? null) !== null ? ', invalidation and rebuild' : '',
    );
    exit(0);
}

printf("\nFAIL %s\n", $flowName);
foreach ($violations as $violation) {
    printf("  - %s\n", $violation);
}

exit(1);
