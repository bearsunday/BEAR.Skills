---
user-invocable: true
name: bear-resource-test
description: Read resource classes and generate dataProvider for smoke tests. Test all resources in a single test class.
---

# BEAR.Sunday Resource Test Generation Skill

## Purpose

Analyze resource classes and generate dataProvider for smoke tests.

## Test Structure

```php
class ResourceTest extends TestCase
{
    use ResourceTestTrait;

    /**
     * @dataProvider resourceProvider
     */
    public function testResource(string $method, string $uri, array $query, int $expectedCode): void
    {
        $response = $this->resource->{$method}($uri, $query);
        $this->assertSame($expectedCode, $response->code);
    }

    public static function resourceProvider(): array
    {
        return [
            // Generated test cases
            'GET /article' => ['get', 'app://self/article', ['id' => 1], 200],
            'GET /articles' => ['get', 'app://self/articles', [], 200],
            'POST /article' => ['post', 'app://self/article', ['title' => 'Test', 'body' => 'Content'], 201],
            // ...
        ];
    }
}
```

## Generation Steps

### 1. Analyze Resource Classes

```php
// Input: src/Resource/App/Article.php
public function onGet(int $id): static
public function onPost(string $title, string $body): static
public function onDelete(int $id): static
```

### 2. Extract Test Cases

| Method | URI | Required Parameters | Expected Code |
|--------|-----|---------------------|---------------|
| GET | app://self/article | id | 200 |
| POST | app://self/article | title, body | 201 |
| DELETE | app://self/article | id | 204 |

### 3. Add to dataProvider

```php
'GET /article' => ['get', 'app://self/article', ['id' => 1], 200],
'POST /article' => ['post', 'app://self/article', ['title' => 'Test', 'body' => 'Body'], 201],
'DELETE /article' => ['delete', 'app://self/article', ['id' => 1], 204],
```

## Default Parameter Values

| Type | Default Value |
|------|---------------|
| int | 1 |
| string | 'test' |
| bool | true |
| array | [] |
| ?type | null |

## Expected Code Determination

| Method | Default Code |
|--------|--------------|
| onGet | 200 |
| onPost | 201 |
| onPut | 200 |
| onPatch | 200 |
| onDelete | 204 |

## Usage

1. Specify the resource directory
2. The skill scans resource classes
3. Generate dataProvider PHP code
4. Add to existing test or create new one

## Output Example

```php
public static function resourceProvider(): array
{
    return [
        // App resources
        'GET app://self/article (id)' => ['get', 'app://self/article', ['id' => 1], 200],
        'GET app://self/articles' => ['get', 'app://self/articles', [], 200],
        'GET app://self/articles (limit)' => ['get', 'app://self/articles', ['limit' => 10], 200],
        'POST app://self/article' => ['post', 'app://self/article', ['title' => 'test', 'body' => 'test'], 201],
        'DELETE app://self/article' => ['delete', 'app://self/article', ['id' => 1], 204],

        // Page resources
        'GET page://self/index' => ['get', 'page://self/index', [], 200],
        'GET page://self/article' => ['get', 'page://self/article', ['id' => 1], 200],
    ];
}
```

## Notes

- Resources requiring authentication need separate configuration
- Resources with external dependencies may require mocks
- Adjust parameter values manually after generation
