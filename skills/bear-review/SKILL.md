---
user-invocable: true
name: bear-review
description: Evaluate PHP code quality for BEAR.Sunday projects. Assess using PHPMD metrics (CC, NPath, parameter count, field count) and BEAR.Sunday-specific criteria (resource design, DI, type safety). Use for code reviews, quality checks, and refactoring considerations.
---

# BEAR.Sunday Code Review Skill

## Evaluation Procedure

### 1. Quantitative Evaluation with PHPMD

Retrieve metrics with the following command:

```bash
./vendor-bin/tools/vendor/bin/phpmd [file-path] text codesize,design 2>/dev/null | grep -v "^Deprecated"
```

### 1.1 Automatic Statistics Report Generation (Recommended)

To understand the overall quality status of the project, it is strongly recommended to automatically generate a PHPMD violation statistics report.

#### Running without baseline

If `phpmd.baseline.xml` exists, temporarily disable it to understand the true quality state:

```bash
# 1. Temporarily rename baseline
mv phpmd.baseline.xml phpmd.baseline.xml.bak

# 2. Run PHPMD against all resource directories
./vendor-bin/tools/vendor/bin/phpmd src/Resource text phpmd.xml 2>&1 > phpmd_output.txt

# 3. Restore
mv phpmd.baseline.xml.bak phpmd.baseline.xml
```

#### Statistics Aggregation Commands

Automatically generate statistics from PHPMD output:

```bash
# Total violations
cat phpmd_output.txt | wc -l

# Count by category
echo "=== Category Statistics ==="
echo "LongVariable:           $(grep -c 'LongVariable' phpmd_output.txt) violations"
echo "CouplingBetweenObjects: $(grep -c 'CouplingBetweenObjects' phpmd_output.txt) violations"
echo "StaticAccess:           $(grep -c 'StaticAccess' phpmd_output.txt) violations"
echo "ElseExpression:         $(grep -c 'ElseExpression' phpmd_output.txt) violations"
echo "UnusedFormalParameter:  $(grep -c 'UnusedFormalParameter' phpmd_output.txt) violations"

# Complexity violations (high priority)
echo ""
echo "=== Complexity Violations (High Priority) ==="
echo "CyclomaticComplexity:   $(grep -c 'CyclomaticComplexity' phpmd_output.txt) violations"
echo "NPathComplexity:        $(grep -c 'NPathComplexity' phpmd_output.txt) violations"
echo "ExcessiveMethodLength:  $(grep -c 'ExcessiveMethodLength' phpmd_output.txt) violations"
echo "ExcessiveClassLength:   $(grep -c 'ExcessiveClassLength' phpmd_output.txt) violations"
echo "TooManyFields:          $(grep -c 'TooManyFields' phpmd_output.txt) violations"
```

#### Statistics Report Example

Example of execution results:

```text
=== PHPMD Statistics Report ===
Total violations: 259

[By Category]
- LongVariable:            92 (35.5%)
- CouplingBetweenObjects:  28 (10.8%)
- StaticAccess:            24 (9.3%)
- ElseExpression:          20 (7.7%)
- UnusedFormalParameter:   18 (7.0%)
...

[Complexity Violations (High Priority)]
- CyclomaticComplexity:     5
- NPathComplexity:          5
- ExcessiveMethodLength:    7
- ExcessiveClassLength:     1
- TooManyFields:            0
```

#### Identifying the Most Problematic Files

```bash
# Top 10 files by violation count
cat phpmd_output.txt | awk -F: '{print $1}' | sort | uniq -c | sort -rn | head -10

# Example:
#   5 src/Resource/Page/Content/SpecialContent.php
#   4 src/Resource/App/GlobalNav.php
#   3 src/Resource/App/Contents/Ranking.php
```

#### Comparison With and Without Baseline

Visualize the number of issues hidden by the baseline:

```bash
# Violation count without baseline
baseline_off=$(cat phpmd_output.txt | wc -l)

# Violation count with baseline (normal execution)
baseline_on=$(./vendor-bin/tools/vendor/bin/phpmd src/Resource text phpmd.xml 2>&1 | wc -l)

echo "=== Baseline Comparison ==="
echo "With baseline:    ${baseline_on} violations"
echo "Without baseline: ${baseline_off} violations"
echo "Suppressed:       $((baseline_off - baseline_on)) violations"
```

#### Classification by Severity

Determine severity based on complexity values:

```bash
# Critical: CC>20 or NPath>10000
critical_cc=$(grep 'CyclomaticComplexity' phpmd_output.txt | grep -oP 'Complexity of \K[0-9]+' | awk '{if($1>20)print}' | wc -l)
critical_npath=$(grep 'NPathComplexity' phpmd_output.txt | grep -oP 'complexity of \K[0-9]+' | awk '{if($1>10000)print}' | wc -l)

echo "=== By Severity ==="
echo "Critical (CC>20 or NPath>10000): $((critical_cc + critical_npath)) violations"
```

#### How to Use the Statistics

1. **Visualize technical debt**: Understand the total number of issues hidden by the baseline
2. **Prioritize**: Address complexity violations first
3. **Improvement plan**: Create a phased improvement plan based on per-category counts
4. **Trend analysis**: Run periodically to monitor improvement progress

### 2. Evaluation Criteria

#### Cyclomatic Complexity (CC)

| Grade | CC Value | Status | Action |
|-------|----------|--------|--------|
| **A** | 1-10 | Very good. Simple and easy to test | State to maintain |
| **B** | 11-20 | Acceptable. Slightly complex but maintainable | Acceptable for complex logic |
| **C** | 21-30 | Warning. Hard to test and prone to bugs | Refactoring recommended |
| **D** | 31+ | Failing. Unmaintainable | Immediate action required |

#### NPath Complexity

| Grade | NPath | Status |
|-------|-------|--------|
| **A** | 1-200 | Good |
| **B** | 201-500 | Acceptable |
| **C** | 501-1000 | Warning |
| **D** | 1001+ | Failing |

#### ExcessiveParameterList

| Grade | Parameter Count | Status |
|-------|----------------|--------|
| **A** | 1-10 | Good |
| **B** | 11-15 | Acceptable (consider DTO) |
| **C** | 16-20 | Warning (recommend wrapping in object) |
| **D** | 21+ | Failing |

#### TooManyFields

| Grade | Field Count | Status |
|-------|------------|--------|
| **A** | 1-15 | Good |
| **B** | 16-22 | Acceptable (consider splitting) |
| **C** | 23-30 | Warning |
| **D** | 31+ | Failing |

### 3. BEAR.Sunday-Specific Evaluation

#### Resource Design (Resource classes only)

| Grade | Criteria |
|-------|----------|
| **A** | Proper use of `#[Embed]`, single responsibility, appropriate HTTP methods |
| **B** | Follows basic resource patterns |
| **C** | Bloated logic, unclear responsibilities |
| **D** | Non-resource code (controller-like implementation) |

#### Body Assignment Pattern

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

#### Private Method Argument Passing Pattern

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

#### Loops Inside Resources

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

#### Domain vs Service Distinction

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

#### Detecting Unused Embed

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

#### Return Type

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

#### Dependency Injection

| Grade | Criteria |
|-------|----------|
| **A** | Constructor injection only, interface dependencies |
| **B** | Some concrete class dependencies |
| **C** | Setter injection via traits, mixed service locator |
| **D** | Global state, static method dependencies |

#### Setter Injection Assessment

**Principle**: Constructor injection is recommended. With PHP 8 constructor promotion, legacy injection traits are unnecessary.

**Ray.Di usage:**
- **Required dependencies** → Constructor injection
- **Optional dependencies** → Setter injection (`optional: true`) is also acceptable

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

#### `new` Usage Assessment

**Important**: Whether `new` usage is problematic depends on the type of object being created.

| Type | `new` Usage | Assessment Criteria |
|------|-----------|---------------------|
| Domain object | ✅ OK | Holds data, represents state (Entity, ValueObject) |
| Value object | ✅ OK | Immutable, data representation (DateTime, Money, etc.) |
| DTO | ✅ OK | Data transfer object |
| Service | ❌ NG → DI | Has behavior, has external dependencies |
| Repository | ❌ NG → DI | Data access layer |
| HTTP client | ❌ NG → DI | External communication |

```php
// ✅ OK: Domain/value objects
$article = new ArticleDomain($data);
$dateTime = new DateTimeImmutable();  // Immutable recommended
$thumbnail = new Thumbnail($data);

// ⚠️ Warning: Mutable DateTime
$date = new DateTime();  // → Should use DateTimeImmutable

// ❌ NG: Services should be injected via DI
$client = new HttpClient();        // → Inject HttpClientInterface
$logger = new FileLogger();        // → Inject LoggerInterface
$mailer = new SmtpMailer();        // → Inject MailerInterface
```

**Judge from context**: Determine the type from class name, namespace, and constructor arguments.

#### Exception Design

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

#### try-catch Inside Resources (Pokemon Catch Problem)

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

#### Type Safety

| Grade | Criteria |
|-------|----------|
| **A** | Full type declarations, generics usage, no `mixed` |
| **B** | Basic type declarations, some `mixed` |
| **C** | Heavy use of `array<string, mixed>`, many `@psalm-suppress` |
| **D** | No type declarations, `array<object>` usage |

#### Doctrine Annotations and PHP 8 Attributes

In PHP 8, use native attributes `#[Embed]` instead of Doctrine annotations `/** @Embed */`.

| Pattern | Grade |
|---------|-------|
| `#[Embed]`, `#[Inject]`, `#[Named]` | ✅ Recommended |
| `/** @Embed */`, `/** @Inject */` | ❌ Legacy |

#### Constants and Configuration Values

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
| Statuses, type identifiers | △ Enum recommended | - |

#### Direct DB Access Inside Resources

Transactions and SQL execution in resources are prohibited. Delegate to the Query layer.

```php
// ❌ Problem: DB operations inside resource
$this->pdo->beginTransaction();
$this->pdo->exec($sql);
$this->pdo->commit();

// ✅ Recommended: Delegate to Query layer
$this->articleQuery->createWithTransaction($data);
```

#### Debug Code

`error_log()`, `var_dump()`, `print_r()` are prohibited. Use LoggerInterface.

```php
// ❌ Problem
error_log('Error: ' . $e->getMessage());

// ✅ Recommended
$this->logger->error('Error', ['exception' => $e]);
```

#### File Size

| Lines | Grade |
|-------|-------|
| 1-200 | ✅ Good |
| 201-400 | ⚠️ Consider splitting |
| 401+ | ❌ Excessive responsibility |

#### Resource Method Arguments

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

#### Web Context Retrieval

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

#### ResourceParam (Inter-Resource Dependencies)

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

#### File Upload

Use `#[UploadFiles]` instead of direct `$_FILES` access.

```php
// ❌ Problem
$file = $_FILES['image'];

// ✅ Recommended
public function onPost(#[UploadFiles] array $files): static
```

#### HTTP Status Codes

Return appropriate status codes.

| Operation | Code |
|-----------|------|
| GET success | 200 OK |
| POST success (creation) | 201 Created |
| Delete success | 204 No Content |
| Not found | 404 Not Found |
| Validation error | 400 Bad Request |

#### 201 Created and Location Header

`onPost` that creates a resource should return 201 status and `Location` header together.

```php
// ❌ Problem: Creating but returning 200, no Location
public function onPost(string $title): static
{
    $id = $this->command->create($title);
    $this->body = ['id' => $id];
    return $this;
}

// ✅ Recommended: 201 + Location header
public function onPost(string $title): static
{
    $id = $this->command->create($title);

    $this->code = 201;
    $this->headers['Location'] = "/article?id={$id}";
    $this->body = ['id' => $id];

    return $this;
}
```

**Detection pattern:**
- `onPost` calls `$this->command->create` or `$this->command->add`
- But `$this->code = 201` is missing
- Or `$this->headers['Location']` is missing

| Pattern | Grade |
|---------|-------|
| 201 + Location present | ✅ Recommended |
| 201 present, Location missing | ⚠️ Warning (recommend adding Location) |
| Remains 200 (with creation logic) | ❌ Problem |

#### Page Resource Restrictions

Page resources should only use `onGet` and `onPost`.

```php
// ❌ Problem: onPut/onDelete in Page
class UserPage extends ResourceObject {
    public function onDelete(int $id): static  // NG
}

// ✅ Recommended: CRUD in App resource, Page uses GET/POST only
```

#### Composition Over Inheritance

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

#### Overuse of Providers

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

#### Global References Prohibited

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

#### Validation (JsonSchema)

Input validation should be done declaratively with JsonSchema.

```php
// ❌ Problem: Manual validation
public function onPost(array $data): static
{
    if (empty($data['title'])) {
        throw new InvalidArgumentException();
    }
    // ...
}

// ✅ Recommended: Declare with JsonSchema
#[JsonSchema(schema: 'article.post.json')]
public function onPost(string $title, string $body): static
```

```json
// var/json_schema/article.post.json
{
  "type": "object",
  "required": ["title", "body"],
  "properties": {
    "title": {"type": "string", "minLength": 1, "maxLength": 255},
    "body": {"type": "string", "minLength": 1}
  }
}
```

#### AOP (Interceptors)

Separate cross-cutting concerns with interceptors. Do not write them directly in resources.

```php
// ❌ Problem: Cross-cutting concerns in resource
public function onPost(array $data): static
{
    $this->logger->info('Creating article');
    $start = microtime(true);
    // Business logic
    $this->logger->info('Created', ['time' => microtime(true) - $start]);
}

// ✅ Recommended: Separate with interceptor
// Module:
$this->bindInterceptor(
    $this->matcher->subclassesOf(ResourceObject::class),
    $this->matcher->startsWith('on'),
    [LogInterceptor::class]
);
```

| Use Case | Implementation Location |
|----------|----------------------|
| Logging | Interceptor |
| Transaction | Interceptor |
| Authentication check | Interceptor |
| Cache | `#[Cacheable]` |
| Validation | `#[JsonSchema]` |

#### Authentication and Authorization

Authentication should be in interceptors or middleware. Do not write authentication logic in resources.

```php
// ❌ Problem: Authentication check inside resource
public function onGet(int $id): static
{
    if (!$this->auth->isLoggedIn()) {
        $this->code = 401;
        return $this;
    }
    // ...
}

// ✅ Recommended: Attribute + interceptor
#[RequireLogin]
public function onGet(int $id): static

// ✅ Recommended: Role-based
#[RequireRole('admin')]
public function onDelete(int $id): static
```

| Pattern | Grade |
|---------|-------|
| Custom attribute + interceptor | ✅ Recommended |
| Middleware | ✅ Recommended |
| Direct check inside resource | ❌ Problem |

## Output Format

```text
## File Evaluation: [file-path]

### PHPMD Metrics

| Metric | Value | Grade |
|--------|-------|-------|
| Cyclomatic Complexity | X | A/B/C/D |
| NPath Complexity | X | A/B/C/D |
| Parameters | X | A/B/C/D |
| Fields | X | A/B/C/D |

### BEAR.Sunday-Specific Evaluation

| Item | Grade | Comment |
|------|-------|---------|
| Resource design | A/B/C/D | ... |
| Body assignment | OK/Problem | Assign all at once to make structure explicit, not sequential |
| Embed usage | OK/Problem | Check if resource->get() sets to body |
| Return type | OK/Recommended | Whether static is used |
| Dependency injection | A/B/C/D | ... |
| Exception design | OK/Problem | @throws Exception is problematic, use domain exceptions |
| try-catch | OK/Problem | Large try-catch, catching Throwable/Exception is problematic |
| Type safety | A/B/C/D | ... |

### Overall Grade: [A/B/C/D]

### Improvement Suggestions

1. ...
2. ...
```

## References

- [BEAR.Sunday Single Page](https://bearsunday.github.io/llms-full.txt)
- [Resource](https://bearsunday.github.io/manuals/1.0/en/resource.html)
- [Resource Parameters](https://bearsunday.github.io/manuals/1.0/en/resource_param.html)
- [DI](https://bearsunday.github.io/manuals/1.0/en/di.html)
- [AOP](https://bearsunday.github.io/manuals/1.0/en/aop.html)
- [Validation](https://bearsunday.github.io/manuals/1.0/en/validation.html)
- [Database](https://bearsunday.github.io/manuals/1.0/en/database.html)
- [Coding Guide](https://bearsunday.github.io/manuals/1.0/en/coding-guide.html)
- [PHPMD Code Size Rules](https://phpmd.org/rules/codesize.html)
