# Code Quality Checklist

## Body Assignment Pattern

Assign all at once at the end to make the structure explicit, rather than assigning sequentially.

```php
// ❌ Problem: Sequential assignment (structure is hard to see)
$this['contentTags'] = $tags;
$this['article'] = $article;
$this['blogger'] = $blogger;
$this['meta'] = $meta;

// ✅ Recommended: Assign all at once to make structure explicit
$this->body = [
    'article' => $article,
    'blogger' => $blogger,
    'contentTags' => $tags,
    'meta' => $meta,
];
```

**Benefits:**
- Response structure is visible at a glance
- Easy to add or remove properties
- Easy to code review

## Private Method Argument Passing Pattern

Passing the same arguments to multiple private methods indicates excessive resource responsibility.

```php
// ❌ Problem: Passing the same arguments repeatedly, resource is bloated
$this->setTdParams($article, $blogger->displayName ?? '', $tags);
$this->setStructuredData($article, $meta, $blogger, $tags);
$this->setSurrogateKey($article, $blogger);

// ✅ Recommended: Delegate to services, resource only decides "what to return"
$this->body = [
    'article' => $article,
    'blogger' => $blogger,
    'meta' => $meta,
    'tdParams' => $this->tdParamsFactory->create($article, $blogger, $tags),
    'structuredData' => $this->structuredDataFactory->create($article, $meta, $blogger, $tags),
];
$this->headers[Header::SURROGATE_KEY] = $this->surrogateKeyBuilder->build($article, $blogger);
```

**Principle**: A resource decides "what to return." "How to create it" is delegated to services.

## Loops Inside Resources

Do not write complex loops inside resources. Delegate to the domain layer.

```php
// ❌ Problem: Complex loop inside resource
foreach (Ranking::CATEGORIES as $key => $categorySlugArray) {
    if ($key === self::ALL) {
        $result = $this->article->rankingAllArticleList(...);
    } else {
        $result = $this->article->rankingCategoryArticleList(...);
    }
    foreach ($result as $item) {
        $articleIds[] = $item['id'];
        // ...
    }
    $list[$key] = new ValidRankingArticleList($result, ...);
}

// ✅ Recommended: Delegate to domain
$rankingCollection = $this->rankingAggregator->aggregate($limit);
$this->body = [
    'rankings' => $rankingCollection->lists,
    'articleIds' => $rankingCollection->articleIds,
];
```

## Domain vs Service Distinction

| Layer | Responsibility | Logic to delegate |
|-------|---------------|-------------------|
| **Domain** | Business logic, rules | Aggregation, calculation, transformation, validation |
| **Service** | External integration, use case coordination | API calls, email sending, file operations |
| **Query** | Data retrieval | Data access via SQL |

```php
// Domain: Business logic
class RankingAggregator
{
    public function aggregate(array $results): RankingCollection
}

// Service: External integration
class MailNotificationService
{
    public function notify(User $user, Article $article): void
}

// Query: Data retrieval
interface RankingQueryInterface
{
    public function getCategoryRankings(int $limit): array;
}
```

## Detecting Unused Embed

When fetching other resources with `$this->resource->get()` and setting them to `$this->body`,
`#[Embed]` should be used unless there is a valid reason not to.

```php
// ❌ Problem: Procedural resource fetching
$user = $this->resource->get('app://self/user', ['id' => $id]);
$this->body['user'] = $user->body;

// ✅ Recommended: Declarative Embed
#[Embed(src: 'app://self/user{?id}', rel: 'user')]
public function onGet(int $id): static
```

**Exceptions (acceptable cases):**
- Conditional fetching (get inside an if statement)
- Processing/transforming the fetched result
- References within PUT/POST/DELETE

## Return Type

Resource methods (onGet, onPost, onPut, onPatch, onDelete) should return `static`.

```php
// ✅ Correct
public function onGet(int $id): static

// ⚠️ Works but not recommended
public function onGet(int $id): ResourceObject
public function onGet(int $id): self
```

| Return Type | Grade |
|-------------|-------|
| `static` | OK |
| `ResourceObject` / `self` | ⚠️ Deprecated (recommend changing to static) |

## Setter Injection Assessment

**Principle**: Constructor injection is recommended. With PHP 8 constructor promotion, legacy injection traits are unnecessary.

**Ray.Di usage:**
- **Required dependencies** -> Constructor injection
- **Optional dependencies** -> Setter injection (`optional: true`) is also acceptable

```php
// ⚠️ Deprecated: Setter injection via trait (required dependency)
trait MetaTag
{
    protected Article $articleMeta;

    #[Inject]
    public function setArticleMeta(Article $articleMeta): void
    {
        $this->articleMeta = $articleMeta;
    }
}

// ✅ Recommended: Constructor injection
public function __construct(
    private readonly Article $articleMeta,
)

// ✅ OK: Optional dependency (ignored if not available)
#[Inject(optional: true)]
public function setDebugger(?DebuggerInterface $debugger): void
{
    $this->debugger = $debugger;
}
```

| Pattern | Grade |
|---------|-------|
| Constructor injection | ✅ Recommended |
| `#[Inject]` setter via trait (required dependency) | ⚠️ Deprecated |
| `#[Inject(optional: true)]` setter | ✅ OK (optional dependency) |
| `use ResourceInject` | ⚠️ Deprecated (recommend constructor injection) |
| `use AInject` traits | ⚠️ Deprecated |
| ResourceObject-specific setters (`setRenderer`, etc.) | ✅ OK (framework use) |

```php
// ⚠️ Deprecated: Resource injection via trait
use ResourceInject;

// ✅ Recommended: Inject via constructor
public function __construct(
    private readonly ResourceInterface $resource,
)
```

**Problems with setter injection via traits:**
- Dependencies are hidden (not visible from the constructor)
- Difficult to test (requires calling setters or using reflection)
- Dependencies are mutable (can be changed later)

**Note**: If existing code uses ResourceInject, it is not an immediate error, but new code should use constructor injection.

## `new` Usage Assessment

**Important**: Whether `new` usage is problematic depends on the type of object being created.

| Type | `new` Usage | Assessment Criteria |
|------|-----------|---------------------|
| Domain object | ✅ OK | Holds data, represents state (Entity, ValueObject) |
| Value object | ✅ OK | Immutable, data representation (DateTime, Money, etc.) |
| DTO | ✅ OK | Data transfer object |
| Service | ❌ NG -> DI | Has behavior, has external dependencies |
| Repository | ❌ NG -> DI | Data access layer |
| HTTP client | ❌ NG -> DI | External communication |

```php
// ✅ OK: Domain/value objects
$article = new ArticleDomain($data);
$dateTime = new DateTimeImmutable();  // Immutable recommended
$thumbnail = new Thumbnail($data);

// ⚠️ Warning: Mutable DateTime
$date = new DateTime();  // -> Should use DateTimeImmutable

// ❌ NG: Services should be injected via DI
$client = new HttpClient();        // -> Inject HttpClientInterface
$logger = new FileLogger();        // -> Inject LoggerInterface
$mailer = new SmtpMailer();        // -> Inject MailerInterface
```

**Judge from context**: Determine the type from class name, namespace, and constructor arguments.

## Exception Design

`@throws Exception` is problematic. Use specific domain exceptions.

| Notation | Grade |
|----------|-------|
| `@throws Exception` | ❌ Problem (unclear what exception) |
| `@throws \Exception` | ❌ Problem |
| `@throws RuntimeException` | ⚠️ Too broad |
| `@throws ArticleNotFoundException` | ✅ Specific and good |

**Recommended**: All exceptions should be domain exceptions extending `RuntimeException` or `LogicException`.

```php
// ✅ Recommended: Domain exceptions
class ArticleNotFoundException extends RuntimeException {}
class InvalidArticleStateException extends LogicException {}

// Usage example
/**
 * @throws ArticleNotFoundException When the article is not found
 */
public function onGet(int $id): static
```

| Base Class | Use Case |
|-----------|----------|
| `RuntimeException` | Recoverable errors at runtime (resource not found, external API failure, etc.) |
| `LogicException` | Program logic errors (invalid arguments, invalid state transitions, etc.) |

## try-catch Inside Resources (Pokemon Catch Problem)

Do not write large try-catch blocks inside resources.

```php
// ❌ Problem: Large try-catch, catching Throwable
public function onGet(int $id): static
{
    try {
        // 100+ lines of logic...
        $article = $this->article->item($id);
        $blogger = $this->blogger->item($article['bloggerId']);
        $meta = $this->meta->generate($article);
        // continues further...
    } catch (Throwable $e) {
        $this->logger->error('Error', ['exception' => $e]);
        throw $e;
    }
}

// ✅ Recommended: Let the framework handle it, delegate logic
public function onGet(int $id): static
{
    $articleView = $this->articleViewFactory->create($id);

    $this->body = [
        'article' => $articleView->article,
        'blogger' => $articleView->blogger,
        'meta' => $articleView->meta,
    ];

    return $this;
}
```

**Problems:**
- Broad catch of `Throwable` or `Exception` (catching everything - "Pokemon catch")
- Try block is too large (unclear where errors occur)
- Logging and re-throwing is redundant (framework handles it)
- Indicates excessive resource responsibility

**Recommended:**
- Let the framework handle exception processing
- Use small try-catch blocks only when specific exceptions are needed
- Delegate logic to Domain/Service to keep resources simple

| Pattern | Grade |
|---------|-------|
| No try-catch (let framework handle) | ✅ Recommended |
| Small catch for specific exceptions | ✅ OK |
| Large try + `catch (Throwable)` | ❌ Problem |
| Large try + `catch (Exception)` | ❌ Problem |

## Type Safety

| Grade | Criteria |
|-------|----------|
| **A** | Full type declarations, generics usage, no `mixed` |
| **B** | Basic type declarations, some `mixed` |
| **C** | Heavy use of `array<string, mixed>`, many `@psalm-suppress` |
| **D** | No type declarations, `array<object>` usage |

## Doctrine Annotations and PHP 8 Attributes

In PHP 8, use native attributes `#[Embed]` instead of Doctrine annotations `/** @Embed */`.

| Pattern | Grade |
|---------|-------|
| `#[Embed]`, `#[Inject]`, `#[Named]` | ✅ Recommended |
| `/** @Embed */`, `/** @Inject */` | ❌ Legacy |

## Constants and Configuration Values

**Environment-dependent configuration values** should be injected, not defined as class constants. **Application structure definitions** are OK as class constants. Use Enum for domain invariant values.

```php
// ❌ Problem: Environment-dependent config values as class constants
private const API_URL = 'https://api.example.com';
private const TIMEOUT = 30;
private const API_KEY = 'xxx';

// ✅ Recommended: Bind with NamedModule, inject with #[Named]
// Module:
$this->bind()->annotatedWith('API_URL')->toInstance($apiUrl);

// Resource:
public function __construct(
    #[Named('API_URL')] private readonly string $apiUrl,
    #[Named('TIMEOUT')] private readonly int $timeout,
)

// ✅ OK: Application structure definitions (environment-independent)
private const RESOURCE_URI_LIST = [
    ['list' => 'app://self/article/publishable', 'update' => 'app://self/article/publish'],
    // ...
];
private const SUPPORTED_CONTENT_TYPES = ['article', 'blog', 'news'];

// ✅ Domain invariant values use Enum
enum ContentStatus: string {
    case Draft = 'draft';
    case Published = 'published';
}
```

| Type | Class Constant | Injection |
|------|---------------|-----------|
| URLs, paths, API keys | ❌ | ✅ |
| Timeouts, credentials | ❌ | ✅ |
| Environment-dependent IDs | ❌ | ✅ |
| **App structure definitions (URI lists, etc.)** | **✅** | - |
| Statuses, type identifiers | Enum recommended | - |

## Direct DB Access Inside Resources

Transactions and SQL execution in resources are prohibited. Delegate to the Query layer.

```php
// ❌ Problem: DB operations inside resource
$this->pdo->beginTransaction();
$this->pdo->exec($sql);
$this->pdo->commit();

// ✅ Recommended: Delegate to Query layer
$this->articleQuery->createWithTransaction($data);
```

## Debug Code

`error_log()`, `var_dump()`, `print_r()` are prohibited. Use LoggerInterface.

```php
// ❌ Problem
error_log('Error: ' . $e->getMessage());

// ✅ Recommended
$this->logger->error('Error', ['exception' => $e]);
```

## File Size

| Lines | Grade |
|-------|-------|
| 1-200 | ✅ Good |
| 201-400 | ⚠️ Consider splitting |
| 401+ | ❌ Excessive responsibility |

## Resource Method Arguments

Use explicit arguments or Input classes instead of `array<string, mixed>`.

```php
// ❌ Problem: Magic bag
public function onGet(array $conditions): static

// ✅ Recommended: Explicit scalar arguments
public function onGet(
    ?int $categoryId = null,
    ?string $keyword = null,
): static

// ✅ Recommended: Input class (for complex data)
public function onPost(UserInput $user): static
```

| Parameter Count | Recommendation |
|----------------|----------------|
| 1-10 | Scalar arguments (explicit and good) |
| 11+ | Consider `#[Input]` + DTO class |

**When to use Input:**
- Related parameters form a single concept (address, user info, etc.)
- Contains nested structures or arrays
- Same parameter set is used across multiple resources

## Web Context Retrieval

Direct access to superglobals is prohibited. Use attributes to retrieve them.

```php
// ❌ Problem: Direct superglobal access
$id = $_GET['id'];
$token = $_COOKIE['token'];

// ✅ Recommended: Retrieve via attributes (easy to test)
public function onGet(
    #[QueryParam('id')] string $userId,
    #[CookieParam('token')] string $token = '',
): static
```

## ResourceParam (Inter-Resource Dependencies)

Inject results from other resources as arguments. More declarative and recommended over procedural fetching.

```php
// ❌ Problem: Procedural fetching
public function onPut(array $data): static
{
    $userId = $this->resource->get('app://self/user/me')['id'];
    // ...
}

// ✅ Recommended: Declarative injection
#[ResourceParam(uri: 'app://self/user/me#id', param: 'userId')]
public function onPut(int $userId, array $data): static
```

## File Upload

Use `#[UploadFiles]` instead of direct `$_FILES` access.

```php
// ❌ Problem
$file = $_FILES['image'];

// ✅ Recommended
public function onPost(#[UploadFiles] array $files): static
```

## Page Resource Restrictions

Page resources should only use `onGet` and `onPost`.

```php
// ❌ Problem: onPut/onDelete in Page
class UserPage extends ResourceObject {
    public function onDelete(int $id): static  // NG
}

// ✅ Recommended: CRUD in App resource, Page uses GET/POST only
```

## Composition Over Inheritance

Prefer dependency injection over traits or parent class methods.

```php
// ❌ Problem: Adding functionality via traits
use MetaTagTrait;
use SurrogateKeyTrait;

// ✅ Recommended: Dependency injection
public function __construct(
    private readonly MetaTagService $metaTag,
    private readonly SurrogateKeyService $surrogateKey,
)
```

## Overuse of Providers

Use `Provider` only when complex creation logic is needed. For simple `new`, use `toConstructor`.

```php
// ❌ Problem: Provider that simply calls new
class FooProvider implements ProviderInterface
{
    public function __construct(
        private readonly BarInterface $bar,
        #[Named('config')] private readonly array $config,
    ) {}

    public function get(): Foo
    {
        return new Foo($this->bar, $this->config['timeout']);
    }
}

// Module
$this->bind(Foo::class)->toProvider(FooProvider::class);

// ✅ Recommended: toConstructor binding (no Provider class needed)
$this->bind(Foo::class)->toConstructor(
    Foo::class,
    ['timeout' => 'foo_timeout']
);
$this->bind()->annotatedWith('foo_timeout')->toInstance($config['timeout']);
```

**Cases where Provider is needed (acceptable):**
- Conditional creation (different instances per environment)
- Factory pattern (dynamic creation based on arguments)
- When lazy initialization is required
- Establishing external resource connections

**Cases where Provider is unnecessary (problematic):**
- `get()` simply calls `new` and returns
- Just relaying received dependencies

| Pattern | Grade |
|---------|-------|
| `toConstructor` suffices | ✅ Recommended |
| Provider that only does simple `new` | ❌ Excessive (use toConstructor) |
| Provider with conditional logic | ✅ Acceptable |
| Factory-like Provider | ✅ Acceptable |

## Global References Prohibited

`define` constants and direct static method calls are prohibited.

```php
// ❌ Problem
$value = SOME_CONSTANT;
$result = SomeClass::staticMethod();

// ✅ Recommended: Injection
public function __construct(
    #[Named('SOME_VALUE')] private readonly string $value,
    private readonly SomeService $service,
)
```
