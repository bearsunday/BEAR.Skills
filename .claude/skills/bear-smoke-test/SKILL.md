---
user-invocable: true
name: bear-smoke-test
description: Generate a 4-layer smoke test suite for BEAR.Sunday projects. Covers SQL, Query/Command, Resource, and Workflow layers.
---

# BEAR.Sunday Smoke Test Generation Skill

## Purpose

Generate a comprehensive 4-layer smoke test suite that validates each layer of a BEAR.Sunday application:

1. **SQL** - Syntax and quality checks for all SQL files
2. **Query/Command** - Interface method invocation checks
3. **Resource** - HTTP method and status code checks
4. **Workflow** - CRUD lifecycle and hypermedia navigation checks

## Procedure

### Layer 1: SQL Smoke Test (`tests/Smoke/SqlTest.php`)

Scan `var/sql/` and generate a test class that validates all SQL files.

```php
class SqlTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $injector = Injector::getInstance('app');
        self::$pdo = $injector->getInstance(ExtendedPdoInterface::class)->getPdo();
    }

    /**
     * @dataProvider sqlFileProvider
     */
    public function testSqlSyntax(string $sqlFile): void
    {
        $sql = trim(file_get_contents($sqlFile));
        // Strip leading comment
        $sql = preg_replace('/\A\/\*.*?\*\/\s*/s', '', $sql);
        // Replace named parameters with NULL for syntax check
        $sql = preg_replace('/:[a-zA-Z_]+/', 'NULL', $sql);

        $stmt = self::$pdo->prepare($sql);
        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    /**
     * @dataProvider selectSqlProvider
     */
    public function testSelectExplain(string $sqlFile): void
    {
        $sql = trim(file_get_contents($sqlFile));
        $sql = preg_replace('/\A\/\*.*?\*\/\s*/s', '', $sql);
        $sql = preg_replace('/:[a-zA-Z_]+/', 'NULL', $sql);
        $sql = preg_replace('/\bLIMIT\s+NULL\b/i', 'LIMIT 1', $sql);
        $sql = preg_replace('/\bOFFSET\s+NULL\b/i', 'OFFSET 0', $sql);

        $driver = self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            // SQLite: EXPLAIN QUERY PLAN returns detail column
            $stmt = self::$pdo->query('EXPLAIN QUERY PLAN ' . $sql);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $detail = $row['detail'] ?? '';
                // 'SCAN ... USING COVERING INDEX' is an index scan, not a full table scan
                if (str_contains($detail, 'SCAN') && !str_contains($detail, 'USING')) {
                    $this->fail('Full table scan: ' . $detail);
                }
            }
            return;
        }

        // MySQL: EXPLAIN returns type column
        $stmt = self::$pdo->query('EXPLAIN ' . $sql);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $type = $row['type'] ?? '';
            $this->assertNotSame('ALL', $type, sprintf(
                'Full table scan detected in %s (table: %s)',
                basename($sqlFile),
                $row['table'] ?? 'unknown'
            ));
        }
    }

    public static function sqlFileProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../../var/sql/*.sql') as $file) {
            yield basename($file) => [$file];
        }
    }

    public static function selectSqlProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../../var/sql/*.sql') as $file) {
            $sql = file_get_contents($file);
            if (stripos($sql, 'SELECT') !== false && stripos($sql, 'INSERT') === false) {
                yield basename($file) => [$file];
            }
        }
    }
}
```

#### SQL Quality Checks

The EXPLAIN check detects:

| Issue | Detection |
|-------|-----------|
| Full table scan | `type = ALL` in EXPLAIN output |
| Index invalidation | Functions wrapping indexed columns (e.g., `YEAR(created_at)`) |
| Inefficient JOIN | JOIN without a proper index |

**Common improvement patterns:**

```sql
-- Index invalidation: use range instead of function
-- Bad:  WHERE YEAR(created_at) = 2024
-- Good: WHERE created_at >= '2024-01-01' AND created_at < '2025-01-01'

-- Leading wildcard: use trailing wildcard
-- Bad:  WHERE name LIKE '%test'
-- Good: WHERE name LIKE 'test%'

-- SELECT *: select only required columns
-- Bad:  SELECT * FROM table
-- Good: SELECT id, title FROM table
```

### Layer 2: Query Smoke Test (`tests/Smoke/QueryTest.php`)

Scan `src/Query/` for all `*Interface.php` files and test each method with default arguments.

```php
class QueryTest extends TestCase
{
    /**
     * @dataProvider queryProvider
     */
    public function testQueryMethod(string $interface, string $method, array $args): void
    {
        $injector = Injector::getInstance('app');
        $instance = $injector->getInstance($interface);
        $result = $instance->{$method}(...$args);

        $ref = new ReflectionMethod($interface, $method);
        $returnType = (string) $ref->getReturnType();

        if ($returnType === 'void') {
            $this->addToAssertionCount(1);
            return;
        }
        if ($returnType === 'array') {
            $this->assertIsArray($result);
            return;
        }
        // nullable return (Entity|null) - accept null for default args
        $this->assertTrue($result === null || is_object($result) || is_array($result));
    }

    public static function queryProvider(): iterable
    {
        // Generate entries for each interface method
    }
}
```

#### Generating the dataProvider

1. Scan `src/Query/*Interface.php`
2. For each interface, reflect all public methods
3. Build default arguments from parameter types (see Default Parameter Values table)
4. Skip methods that require complex arguments not covered by defaults

### Layer 3: Resource Smoke Test (`tests/Smoke/ResourceTest.php`)

Scan `src/Resource/App/` and `src/Resource/Page/` for resource classes and test each `on*` method.

```php
class ResourceTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance('app');
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    /**
     * @dataProvider resourceProvider
     */
    public function testResource(string $method, string $uri, array $query, int $expectedCode): void
    {
        $ro = $this->resource->{$method}($uri, $query);
        $this->assertSame($expectedCode, $ro->code);
    }

    public static function resourceProvider(): iterable
    {
        return [
            // 'METHOD uri (params)' => ['method', 'scheme://self/path', [...params], code],
        ];
    }
}
```

#### Generating the dataProvider

1. Scan resource directories for classes extending `ResourceObject`
2. For each `on*` method, extract parameters and default values
3. Map class path to URI: `src/Resource/App/Todo.php` -> `app://self/todo`
4. Map method to expected status code (see HTTP Status Code table)

### Layer 4: Workflow Smoke Test (`tests/Smoke/WorkflowTest.php`)

Detect resources with `#[Link]` attributes and generate CRUD lifecycle tests.

```php
class WorkflowTest extends TestCase
{
    protected ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance('app');
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    /**
     * Example: Todo Create -> List workflow
     */
    public function testTodoCrudWorkflow(): void
    {
        // Create
        $post = $this->resource->post('app://self/todo', ['title' => 'Workflow Test']);
        $this->assertSame(201, $post->code);

        // Read (list)
        $list = $this->resource->get('app://self/todo');
        $this->assertSame(200, $list->code);
    }
}
```

#### Generating Workflow Tests

1. Find resources with `#[Link]` attributes
2. For each linked resource chain, generate a workflow test
3. If no `#[Link]` attributes exist, generate basic CRUD cycle tests for resources that have both query and command methods (GET + POST/PUT/DELETE)
4. Test navigation: Create -> Read -> (Update -> Read ->) Delete

## Reference Tables

### Default Parameter Values

| Type | Default Value |
|------|---------------|
| `int` | `1` |
| `string` | `'test'` |
| `bool` | `true` |
| `float` | `1.0` |
| `array` | `[]` |
| `?type` (nullable) | `null` |
| `DateTimeInterface` | `null` |

### HTTP Status Code Mapping

| Method | Default Code |
|--------|--------------|
| `onGet` | `200` |
| `onPost` | `201` |
| `onPut` | `200` |
| `onPatch` | `200` |
| `onDelete` | `204` |

## Output

After generation, provide a summary:

```text
Generated 4-layer smoke test suite:

  tests/Smoke/
  ├── SqlTest.php        — N SQL files checked (syntax + EXPLAIN)
  ├── QueryTest.php      — N interface methods checked
  ├── ResourceTest.php   — N resource endpoints checked
  └── WorkflowTest.php   — N workflow scenarios checked

Next steps:
1. Run: ./vendor/bin/phpunit --testsuite smoke
2. Review generated default parameter values
3. Add authentication setup if needed for protected resources
4. Adjust workflow test data for your domain
```

## Notes

- Resources requiring authentication need separate setup in `setUp()` or a custom test trait
- Adjust default parameter values after generation to match your domain constraints
- Workflow tests may need transaction rollback or database cleanup between runs
- For `#[Link]`-based workflow tests, use `bear-hypermedia` to add link attributes first

## References

- [BEAR.Sunday Testing](https://bearsunday.github.io/manuals/1.0/en/test.html)
- [BEAR.Sunday Resource](https://bearsunday.github.io/manuals/1.0/en/resource.html)
- [PHPUnit Data Providers](https://docs.phpunit.de/en/10.5/writing-tests-for-phpunit.html#data-providers)
