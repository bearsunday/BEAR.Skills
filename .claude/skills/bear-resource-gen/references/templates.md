# Code Templates

All code templates used by the BEAR.Sunday Resource Generator.

## Phinx Migration

File: `var/phinx/migrations/YYYYMMDDHHMMSS_create_{entity}_table.php`

```php
<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class Create{Entity}Table extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('{entity_snake}', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'string', ['limit' => 64])
              // Add other columns based on entity properties
              ->addColumn('date_created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['{searchable_columns}'])
              ->create();
    }
}
```

### Type Mapping

- `string` -> `'string', ['limit' => 255]`
- `int` -> `'integer'`
- `bool` -> `'boolean'`
- `float` -> `'decimal', ['precision' => 10, 'scale' => 2]`
- `datetime` -> `'datetime'`
- `?Type` (nullable) -> add `['null' => true]`

### Index Rules

Add indexes for:
- Search columns (title, name, etc.)
- Date columns
- Foreign keys

## Query Interface

File: `src/Query/{Entity}QueryInterface.php`

```php
<?php
declare(strict_types=1);

namespace {Namespace}\Query;

use {Namespace}\Entity\{Entity};
use Ray\MediaQuery\Annotation\DbQuery;

interface {Entity}QueryInterface
{
    #[DbQuery('{entity_snake}_item')]
    public function item(string $id): {Entity}|null;

    /** @return array<{Entity}> */
    #[DbQuery('{entity_snake}_list')]
    public function list(): array;
}
```

## Command Interface

File: `src/Query/{Entity}CommandInterface.php`

```php
<?php
declare(strict_types=1);

namespace {Namespace}\Query;

use DateTimeInterface;
use Ray\MediaQuery\Annotation\DbQuery;

interface {Entity}CommandInterface
{
    #[DbQuery('{entity_snake}_add')]
    public function add({parameters}): void;
    // Timestamp example (PHP 8.4-safe explicit nullable; auto-injected when omitted):
    //   public function add(string $id, string $title, DateTimeInterface|null $dateCreated = null): void;

    #[DbQuery('{entity_snake}_update')]
    public function update({parameters}): void;

    #[DbQuery('{entity_snake}_delete')]
    public function delete(string $id): void;
}
```

## SQL Files

All SQL files go in `var/sql/` with flat structure using `{entity}_{operation}.sql` naming.

### `var/sql/{entity}_add.sql`

```sql
/* {entity} add */
INSERT INTO {entity_snake} ({columns})
VALUES ({named_placeholders})
```

### `var/sql/{entity}_item.sql`

```sql
/* {entity} item */
SELECT {columns}
  FROM {entity_snake}
 WHERE id = :id
```

### `var/sql/{entity}_list.sql`

```sql
/* {entity} list */
SELECT {columns}
  FROM {entity_snake}
 ORDER BY date_created DESC
```

### `var/sql/{entity}_update.sql`

```sql
/* {entity} update */
UPDATE {entity_snake}
   SET {column_assignments}
 WHERE id = :id
```

### `var/sql/{entity}_delete.sql`

```sql
/* {entity} delete */
DELETE FROM {entity_snake}
 WHERE id = :id
```

## Entity Class

File: `src/Entity/{Entity}.php`

```php
<?php
declare(strict_types=1);

namespace {Namespace}\Entity;

class {Entity}
{
    public readonly string $propertyCamelCase;

    public function __construct(
        public readonly string $id,
        string $property_snake_case
    ) {
        $this->propertyCamelCase = $property_snake_case;
    }
}
```

### Conversion Rules

- Database: `date_created` (snake_case)
- Entity constructor param: `string $date_created`
- Entity property: `public readonly string $dateCreated` (camelCase)

**Datetime normalisation:** databases store `datetime` as a naive `Y-m-d H:i:s` string, but the response JSON Schema uses `"format": "date-time"` (ISO-8601). Normalise in the constructor so the JSON output validates:

```php
public readonly string $dateCreated;

public function __construct(
    public readonly string $id,
    public readonly string $title,
    bool|int $completed,   // SQLite returns booleans as int — cast in the body
    string $date_created
) {
    $this->completed = (bool) $completed;
    $this->dateCreated = (new \DateTimeImmutable($date_created))->format(\DateTimeInterface::ATOM);
}
```

Declare any non-promoted `readonly` properties (e.g. `$completed`, `$dateCreated`) at class level to avoid the PHP 8.4 dynamic-property deprecation.

## Resource Class

File: `src/Resource/App/{Entity}.php`

```php
<?php
declare(strict_types=1);

namespace {Namespace}\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;
use {Namespace}\Query\{Entity}CommandInterface;
use {Namespace}\Query\{Entity}QueryInterface;

class {Entity} extends ResourceObject
{
    public function __construct(
        private readonly {Entity}QueryInterface $query,
        private readonly {Entity}CommandInterface $command
    ) {}

    #[JsonSchema(schema: '{entity}.json')]
    public function onGet(string $id): static
    {
        $item = $this->query->item($id);
        if ($item === null) {
            $this->code = 404;
            return $this;
        }

        $this->body = (array) $item;
        return $this;
    }

    #[JsonSchema(schema: '{entity}-post.json')]
    public function onPost({parameters}): static
    {
        $id = $this->generateId();
        $this->command->add($id, {params}); // DateTimeInterface auto-injected

        $this->code = 201;
        $this->headers['Location'] = "/{entity}?id={$id}";
        $this->body = ['id' => $id];

        return $this;
    }

    #[JsonSchema(schema: '{entity}-put.json')]
    public function onPut(string $id, {parameters}): static
    {
        $item = $this->query->item($id);
        if ($item === null) {
            $this->code = 404;
            return $this;
        }

        $this->command->update($id, {params});
        $this->code = 200;
        $this->body = ['id' => $id];

        return $this;
    }

    public function onDelete(string $id): static
    {
        $item = $this->query->item($id);
        if ($item === null) {
            $this->code = 404;
            return $this;
        }

        $this->command->delete($id);
        $this->code = 204;

        return $this;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

### List Operation (keyed onGet)

When the spec includes a list (GET) operation, serve the collection with a keyed body validated by `{entity}-list.json` (mirrors `app/src/Resource/App/Todo.php`; schema template in `references/jsonschema-templates.md`):

```php
#[JsonSchema(schema: '{entity}-list.json')]
public function onGet(): static
{
    $this->body = [
        '{entity_plural}' => $this->query->list(),
    ];

    return $this;
}
```

A ResourceObject has one `onGet` per URI. When both list and item GET are required, keep the item `onGet(string $id)` in `{Entity}.php` and put the list `onGet()` in a collection resource (e.g. `src/Resource/App/{Entity}s.php`) so each method keeps its own `#[JsonSchema]`.

## Resource Test

File: `tests/Resource/App/{Entity}Test.php`

```php
<?php
declare(strict_types=1);

namespace {Namespace}\Resource\App;

use BEAR\Resource\ResourceInterface;
use {Namespace}\Injector;
use PHPUnit\Framework\TestCase;

class {Entity}Test extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance('app');
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    public function testOnGet(): void
    {
        $item = $this->resource->get('app://self/{entity_snake}', ['id' => '1']);
        $this->assertSame(200, $item->code);
        $this->assertArrayHasKey('id', $item->body);
    }

    public function testOnGetNotFound(): void
    {
        $item = $this->resource->get('app://self/{entity_snake}', ['id' => 'non-existent']);
        $this->assertSame(404, $item->code);
    }

    public function testOnPost(): void
    {
        $item = $this->resource->post('app://self/{entity_snake}', [{post_params}]);
        $this->assertSame(201, $item->code);
        $this->assertArrayHasKey('Location', $item->headers);
    }

    public function testOnPut(): void
    {
        $item = $this->resource->put('app://self/{entity_snake}', [{put_params}]);
        $this->assertSame(200, $item->code);
    }

    public function testOnDelete(): void
    {
        $item = $this->resource->delete('app://self/{entity_snake}', ['id' => '1']);
        $this->assertSame(204, $item->code);
    }
}
```

## Entity Test

File: `tests/Entity/{Entity}Test.php`

```php
<?php
declare(strict_types=1);

namespace {Namespace}\Entity;

use PHPUnit\Framework\TestCase;

class {Entity}Test extends TestCase
{
    public function testConstruct(): void
    {
        $item = new {Entity}({constructor_params});
        $this->assertSame({expected_values});
    }
}
```
