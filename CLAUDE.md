# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BEAR.Skills is a collection of Claude Skills designed for BEAR.Sunday, a PHP resource-oriented framework. The primary goal is to provide automated code generation for BEAR.Sunday's ROA (Resource Oriented Architecture) components.

### Core Skill: BEAR.Sunday Resource Generator

**Location:** `.claude/skills/bear-resource-generator/`

This skill generates a complete, consistent set of files for BEAR.Sunday resources leveraging Ray.MediaQuery:

1. **Phinx Migration** - Database schema with proper types and indexes
2. **Query/Command Interfaces** (CQRS pattern)
   - QueryInterface: Read operations with `#[DbQuery]` attributes
   - CommandInterface: Write operations (add/update/delete)
3. **SQL Files** (flat structure in `var/sql/`)
   - Naming convention: `{entity}_{operation}.sql`
   - Operations: `add`, `list`, `item`, `update`, `delete`
   - Example: `ticket_add.sql`, `ticket_item.sql`
4. **Entity Classes** (readonly properties)
   - snake_case (DB) → camelCase (JSON) conversion
   - Readonly properties for immutability
   - Constructor-based initialization
5. **Resource Classes** (extends ResourceObject)
   - Constructor injection of QueryInterface and CommandInterface
   - `#[JsonSchema]` attribute for validation
   - Full CRUD methods (onGet, onPost, onPut, onDelete) with proper HTTP status codes
   - 404 error handling for non-existent resources
6. **JsonSchema** (request and response)
   - Response: `var/schema/response/{entity}.json`
   - Request: `var/schema/request/{entity}-post.json`, `{entity}-put.json`
   - JSON Schema Draft 07 format
7. **Tests** (unit and integration)
   - Resource integration tests with all HTTP methods
   - Entity unit tests
   - 404 error handling tests

### Using the Skill

Invoke the skill and provide a specification:

```markdown
Entity: Ticket (id: string, title: string, content: string, dateCreated: datetime)

Operations:
- List tickets (GET)
- Get ticket detail (GET)
- Create ticket (POST)
- Update ticket (PUT)
- Delete ticket (DELETE)
```

Or provide an ALPS profile for more comprehensive generation with semantic definitions.

The skill will generate all necessary files and provide a summary with next steps.

## Architecture Principles

### Ray.MediaQuery Pattern

Ray.MediaQuery binds PHP interfaces directly to SQL execution:
```php
interface TicketQueryInterface
{
    #[DbQuery('ticket_item')]
    public function item(string $id): Ticket|null;

    /** @return array<Ticket> */
    #[DbQuery('ticket_list')]
    public function list(): array;
}
```

No implementation class needed - DI auto-generates SQL execution objects.

### Clean Architecture & DIP

- **Domain Layer**: Interfaces define business logic contracts
- **Infrastructure Layer**: SQL files contain implementation details
- **Dependency Inversion**: Resources depend on abstractions (interfaces), not concrete SQL

### SQL File Organization

**Flat structure (recommended):**
```
var/sql/
├── ticket_add.sql
├── ticket_list.sql
├── ticket_item.sql
├── ticket_update.sql
└── ticket_delete.sql
```

**Benefits:**
- Simple and predictable: "ticket list" → `ticket_list.sql`
- Easy discovery: `ls var/sql/ticket_*`
- Clear ownership: Each resource has self-contained SQL
- Change impact is isolated

## Development Commands

The `app/` directory contains a BEAR.Sunday project for testing and development:

### Setup and Dependencies
```bash
cd app
composer install
composer setup  # Runs bin/setup.php and Phinx migrations
```

### Testing
```bash
cd app
composer test          # Run PHPUnit tests
composer coverage      # Generate coverage report with Xdebug
composer pcov          # Generate coverage report with PCOV

# Run a single test file
./vendor/bin/phpunit tests/Resource/App/TodoTest.php

# Run a single test method
./vendor/bin/phpunit --filter testOnGet tests/Resource/App/TodoTest.php
```

### Code Quality
```bash
cd app
composer cs            # Check coding standards
composer cs-fix        # Fix coding standards
composer sa            # Run static analysis (Psalm, PHPStan, PHPMD)
composer tests         # Run cs + sa + test
```

### Development Server
```bash
cd app
composer serve         # Start server at http://127.0.0.1:8080
```

### Building
```bash
cd app
composer build         # Run clean + cs + sa + pcov + compile + metrics
```

## Skill Development

### Directory Structure

Skills are located in `.claude/skills/` (development) and `skills/` (distribution):
```text
.claude/skills/
├── bear-cache-strategy/
├── bear-documenter/
├── bear-from-alps/          # Generate project from ALPS profile
├── bear-hypermedia/
├── bear-preflight/
├── bear-refactor/
├── bear-resource-generator/
├── bear-resource-test/
├── bear-review/
├── bear-security-setup/
├── bear-sql-quality/
└── bear-to-alps/            # Extract ALPS profile from project
```

## Xdebug Integration

This project has Xdebug MCP server enabled for debugging PHP code:
- Use `mcp__xdebug__x-trace` for execution flow analysis
- Use `mcp__xdebug__x-profile` for performance profiling
- Use `mcp__xdebug__x-debug` for step debugging with breakpoints
- Use `mcp__xdebug__x-coverage` for test coverage analysis

Refer to the Forward Trace™ debugging approach outlined in the global CLAUDE.md.

## BEAR.Sunday Context

BEAR.Sunday is a resource-oriented PHP framework that:
- Models application states as resources (Resource Object)
- Uses dependency injection extensively (Ray.Di)
- Separates concerns through aspect-oriented programming (Ray.Aop)
- Implements hypermedia-driven architecture

Skills developed here should align with BEAR.Sunday's resource-oriented philosophy.
