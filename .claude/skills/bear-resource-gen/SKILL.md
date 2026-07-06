---
user-invocable: true
name: bear-resource-gen
description: Generate complete BEAR.Sunday resource sets (Phinx migrations, Query/Command interfaces, SQL files, Entity classes, Resource classes, JsonSchema, tests) from simple specifications or ALPS profiles. Supports Ray.MediaQuery pattern and clean architecture principles. Use when user says "generate resources", "リソース生成", "CRUD", "create entity", or asks to scaffold resource files.
---

# BEAR.Sunday Resource Generator

This skill generates a complete, consistent set of files for BEAR.Sunday resources following ROA (Resource Oriented Architecture) principles and Ray.MediaQuery patterns.

## When to Use This Skill

Use this skill when:
- Creating new BEAR.Sunday resources from scratch
- Need to generate CRUD operations for an entity
- Want consistent code structure across resources
- Have an ALPS profile to implement

## What This Skill Generates

1. **Phinx Migration** - Database schema with proper types and indexes
2. **Query Interface** - Read operations with `#[DbQuery]` attributes
3. **Command Interface** - Write operations (add/update/delete)
4. **SQL Files** - Flat structure in `var/sql/` (e.g., `ticket_add.sql`)
5. **Entity Class** - Readonly properties with snake_case -> camelCase conversion
6. **Resource Class** - Full CRUD with proper HTTP status codes
7. **JsonSchema** - Both request and response schemas
8. **Tests** - Resource integration tests and entity unit tests

## Input Format

### Format 1: Simple Specification

```markdown
Entity: Ticket (id: string, title: string, content: string, dateCreated: datetime)

Operations:
- List tickets (GET)
- Get ticket detail (GET)
- Create ticket (POST)
- Update ticket (PUT)
- Delete ticket (DELETE)
```

### Format 2: ALPS Profile

Provide an ALPS JSON profile with semantic definitions.

## Step-by-Step Implementation Process

### Step 1: Analyze Input

1. Parse the entity specification or ALPS profile
2. Extract:
   - Entity name (e.g., "Ticket")
   - Properties with types (e.g., `id: string`, `title: string`)
   - Required operations (list, item, add, update, delete)
3. Determine:
   - Namespace from `composer.json` (e.g., `MyVendor\MyProject`)
   - Target directory structure

### Step 2: Generate Phinx Migration

Create `var/phinx/migrations/YYYYMMDDHHMMSS_create_{entity}_table.php`.

See `references/templates.md` for the migration template, type mapping, and index rules.

### Step 3: Generate Query Interface

Create `src/Query/{Entity}QueryInterface.php`.

See `references/templates.md` for the template.

### Step 4: Generate Command Interface

Create `src/Query/{Entity}CommandInterface.php`.

See `references/templates.md` for the template.

### Critical: Parameter Rules

Reference: [Database Media documentation](https://bearsunday.github.io/manuals/1.0/en/database_media.html)

#### 1. DateTimeInterface Auto-Injection

Use `DateTimeInterface $fieldName = null` for timestamp fields. The null default enables auto-injection by DI. Resource callers omit these parameters -- current time is injected automatically. This makes timestamps testable.

#### 2. Exclude Auto-Generated and Default Value Fields

Exclude from Command parameters:
- Fields with `DEFAULT` in migration (e.g., `completed DEFAULT false`)
- Auto-generated fields (e.g., `id` generated in Resource)

#### 3. Query Interface @return Type

Use PHPDoc `@return array<{Entity}>` for automatic Entity conversion. DB snake_case is converted to Entity camelCase automatically. No manual conversion needed in Resource code.

### Step 5: Generate SQL Files

Create files in `var/sql/` with flat structure: `{entity}_{operation}.sql`.

Operations: `_add`, `_item`, `_list`, `_update`, `_delete`.

See `references/templates.md` for the SQL templates.

### Step 6: Generate Entity Class

Create `src/Entity/{Entity}.php`.

Conversion: DB `date_created` (snake_case) -> constructor param `string $date_created` -> property `public readonly string $dateCreated` (camelCase).

See `references/templates.md` for the template.

### Step 7: Generate Resource Class

Create `src/Resource/App/{Entity}.php`.

HTTP Status Codes:
- GET: 200 OK / 404 Not Found
- POST: 201 Created + Location header
- PUT: 200 OK / 404 Not Found
- DELETE: 204 No Content / 404 Not Found
- 400 Bad Request: Automatically handled by JsonSchema validation

For the full HTTP status convention (201 + Location, 409 unique-key conflict,
422 validation failure, action-style POST = 200), see
`bear-clean-style/references/resource-patterns.md`.

See `references/templates.md` for the template.

For advanced patterns (#[Embed], #[ResourceParam]), see `references/advanced-patterns.md`.

### Step 8: Generate JsonSchema Files

Create response schema in `var/json_schema/{entity}.json` and request schemas in `var/json_validate/{entity}-post.json`, `{entity}-put.json`.

See `references/jsonschema-templates.md` for the templates.

### Step 9: Generate Tests

Create `tests/Resource/App/{Entity}Test.php` and `tests/Entity/{Entity}Test.php`.

See `references/templates.md` for the test templates.

### Step 10: Create Directories

Ensure all necessary directories exist:

```bash
mkdir -p src/Query
mkdir -p src/Entity
mkdir -p src/Resource/App
mkdir -p var/sql
mkdir -p var/phinx/migrations
mkdir -p var/json_validate
mkdir -p var/json_schema
mkdir -p tests/Resource/App
mkdir -p tests/Entity
```

### Step 11: Summary Output

After generating all files, provide a summary:

```text
Generated Files:
- Phinx Migration: var/phinx/migrations/YYYYMMDDHHMMSS_create_{entity}_table.php
- Query Interface: src/Query/{Entity}QueryInterface.php
- Command Interface: src/Query/{Entity}CommandInterface.php
- SQL Files: var/sql/{entity}_*.sql (5 files)
- Entity: src/Entity/{Entity}.php
- Resource: src/Resource/App/{Entity}.php
- JsonSchema: var/json_schema/{entity}.json, var/json_validate/{entity}-*.json (3 files)
- Tests: tests/Resource/App/{Entity}Test.php, tests/Entity/{Entity}Test.php
```

Next steps: run migration, run tests, fix coding standards.

## Important Notes

- **Flat SQL Structure**: All SQL files go directly in `var/sql/` with `{entity}_{operation}.sql` naming
- **snake_case -> camelCase**: Database uses snake_case, JSON/PHP uses camelCase
- **404 Handling**: Always check if item exists before update/delete operations
- **Type Safety**: Use readonly properties and proper type hints
- **CQRS**: Separate Query (read) and Command (write) interfaces
- **DIP**: Interfaces define contracts, SQL files are implementation details

## Entrypoint / Bootstrap / Context

For BEAR.Sunday applications, keep request execution and DI mode separate:

- Thin entrypoints (`bin/app.php`, `bin/page.php`, `public/index.php`) call `Bootstrap` with a fixed default context.
- `APP_CONTEXT` is an escape hatch for temporary override, not the primary user-facing API.
- `Bootstrap` owns request execution: method, path/query parsing, router match, resource invocation, and response transfer.
- Context names should describe DI composition only (for example HAL API vs Page/HTML, fake/test/prod bindings), not method/path/query.
- Human-facing CLI input should stay request-shaped, e.g. `php bin/app.php get '/article?id=1'` or `composer app -- get '/article?id=1'`.
- Do not mix Fake, Dev diagnostics, and Page/HTML concerns in one module: keep FakeQuery bindings, diagnostics/logging, and renderer/session presentation as separate modules that contexts compose.

## Troubleshooting

- If namespace detection fails, ask user for vendor and project name
- If uncertain about nullable fields, ask user
- If operations are unclear, ask which CRUD operations are needed
- Always validate generated SQL syntax
- Ensure proper PHP 8.3+ syntax (readonly properties, constructor property promotion)
