# Advanced Patterns

## Validation (JsonSchema)

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

## AOP (Interceptors)

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

## Authentication and Authorization

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

## Caching

Use `#[Cacheable]` attribute for resource-level caching instead of manual cache logic.

```php
// ❌ Problem: Manual cache logic inside resource
public function onGet(int $id): static
{
    $cacheKey = "article_{$id}";
    $cached = $this->cache->get($cacheKey);
    if ($cached) {
        $this->body = $cached;
        return $this;
    }
    // ... fetch and cache manually
}

// ✅ Recommended: Declarative caching
#[Cacheable]
class Article extends ResourceObject
{
    public function onGet(int $id): static
    {
        // Just fetch data, caching is handled by the framework
    }
}
```
