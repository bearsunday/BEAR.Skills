---
name: bear-from-alps
description: Generate a BEAR.Sunday project from an ALPS profile. Interactively collects vendor name, package name, router selection, etc. to build the project. Uses bear-resource-gen internally. Use when user says "create project from ALPS", "ALPSからプロジェクト", "alps-to-bear", "scaffold BEAR.Sunday", or asks to generate a project from an ALPS profile.
user-invocable: true
---

# ALPS to BEAR.Sunday Project Generator

A skill that generates an entire BEAR.Sunday project from an ALPS profile.

## When to Use This Skill

- You want to start a new BEAR.Sunday project
- You want to create a project structure based on an ALPS profile
- You want to proceed with project initial setup interactively

## Prerequisites

- PHP 8.1 or higher (BEAR.Sunday framework baseline; 8.3+ recommended, 8.1 reached EOL 2025-12)
- Composer
- asd (app-state-diagram) command - used for ALPS validation

## Step-by-Step Process

### Step 1: Verify the ALPS Profile

**Always verify first:**

1. Ask for the ALPS profile path
2. Read the file and verify it is valid ALPS JSON
3. Validate with `asd --validate {profile_path}`

```bash
# Validation command
asd --validate docs/alps.json
```

**On validation failure:**
```
Error: ALPS profile validation failed
Details: {validation_error}

Action: Use the alps skill to fix the profile
Details: https://www.app-state-diagram.com/manuals/1.0/en/ai-assistant.html#skill-claude-code
```

### Step 2: Collect Project Information

Use AskUserQuestion tool to ask (multiple questions):

1. **Development Approach**
   - **Inside-Out (API First) (Recommended)** - Define APIs first with FakeJson -> Create tests & API Docs -> User agreement -> DB implementation
   - **Outside-In (Full Stack)** - Generate DB, resources, and tests all at once

2. **Project Creation Directory**
   - **Current Directory (Recommended)**
   - **Specified Path**

3. **Vendor Name** - e.g.: MyVendor, Acme, MyCompany

4. **Package Name** - e.g.: MyProject, Blog, Shop

5. **Router Selection**
   - **Web Router (Recommended)** - Convention-based, no configuration needed
   - **Aura Router** - When path parameters (/user/{id}) are needed

## Development Approach

### When Inside-Out (API First) is Selected

```
Phase 1: API Design & Validation
┌─────────────────────────────────────────────────────┐
│ ALPS → FakeJson → Resource(stub) → Tests → API Doc  │
└─────────────────────────────────────────────────────┘
                        ↓
                  User Agreement
                        ↓
Phase 2: Implementation (executed separately)
┌─────────────────────────────────────────────────────┐
│ DB Design → Entity → Query/Command → SQL → Go Live   │
└─────────────────────────────────────────────────────┘
```

### When Outside-In (Full Stack) is Selected

```
┌──────────────────────────────────────────────────────────────────┐
│ ALPS → DB Design → Entity → Query/Command → SQL → Resource → Tests │
└──────────────────────────────────────────────────────────────────┘
                        ↓
                     Done!
```

### Step 3: Generate Project Skeleton

```bash
# Create project
composer create-project bear/skeleton {Vendor}.{Package}
cd {Vendor}.{Package}

# Common packages
composer require koriym/env-json
composer require bear/api-doc  # #[Alps] attribute
composer require bear/aura-router-module ^2.0  # Only when Aura Router is selected

# When Inside-Out is selected
composer require --dev bear/fake-json

# When Outside-In is selected
composer require ray/media-query
```

### Step 4: Generate Configuration Files

Generate all configuration files for the project: composer.json, env.json, env.schema.json, env.dist.json, phinx.php, .gitignore, bin/app.php, public/index.php, tests/bootstrap.php, and place the ALPS profile.

See references/config-templates.md for details.

### Step 5: Parse the ALPS Profile

Parse the ALPS profile to extract the following information:

```php
// Parsing targets
$alps = json_decode(file_get_contents('docs/alps.json'), true);
$descriptors = $alps['alps']['descriptor'];

// UpperCamelCase check (state/resource names)
$isUpperCamelCase = fn(string $id): bool => preg_match('/^[A-Z][a-zA-Z0-9]*$/', $id) === 1;

// lowerCamelCase check (data element names)
$isLowerCamelCase = fn(string $id): bool => preg_match('/^[a-z][a-zA-Z0-9]*$/', $id) === 1;

// 1. Extract Ontology (data elements) - lowerCamelCase with type unspecified or semantic
$semantics = array_filter($descriptors, fn($d) =>
    isset($d['id']) &&
    $isLowerCamelCase($d['id']) &&
    (!isset($d['type']) || $d['type'] === 'semantic')
);

// 2. Extract Taxonomy (states/resources) - UpperCamelCase with child descriptors
$states = array_filter($descriptors, fn($d) =>
    isset($d['id']) &&
    $isUpperCamelCase($d['id']) &&
    isset($d['descriptor']) &&
    !isset($d['type'])
);

// 3. Extract Choreography (transitions) - go*/do* prefix or has type specified
$transitions = array_filter($descriptors, fn($d) =>
    isset($d['type']) && in_array($d['type'], ['safe', 'unsafe', 'idempotent'])
);
```

**Identifying list resources and individual resources:**

| Pattern | Type | Example |
|---------|------|---------|
| `{Entity}List`, `{Entity}Collection` | List | UserList, ProductCollection |
| `{Entity}`, `{Entity}Detail`, `{Entity}Item` | Individual | User, ProductDetail, OrderItem |

**Recommended: Use `{Entity}` for singular resources (simpler)**

**Determining Resource URIs:**

| Taxonomy ID | Resource URI | Resource Class |
|-------------|--------------|----------------|
| UserList | /users | Users.php |
| User | /user | User.php |
| ProductList | /products | Products.php |
| Product | /product | Product.php |

---

## Inside-Out (API First) Flow

When Inside-Out is selected, execute Steps 6A through 6E, then Phase 2 after user agreement.

See references/inside-out-flow.md for details on FakeJson generation, stub resources, FakeJsonModule configuration, tests, API Doc generation, and Phase 2 implementation steps.

---

## Outside-In (Full Stack) Flow

When Outside-In is selected, invoke bear-resource-gen for each entity, then add #[Alps] and #[Link] attributes.

See references/outside-in-flow.md for details on ALPS-to-resource conversion, #[Alps] attribute mapping, and #[Link] rel usage.

---

### Step 8: Routing Configuration (When Aura Router is Selected)

**var/conf/aura.route.php:**

```php
<?php
declare(strict_types=1);

/** @var \Aura\Router\Map $map */

// List resource: /users
$map->get('users', '/users', '/users');       // GET /users → Users::onGet()
$map->post('users.post', '/users', '/users'); // POST /users → Users::onPost()

// Individual resource: /users/{id}
$map->get('user', '/users/{id}', '/user');       // GET /users/123 → User::onGet(id: '123')
$map->put('user.put', '/users/{id}', '/user');   // PUT /users/123 → User::onPut(id: '123')
$map->patch('user.patch', '/users/{id}', '/user'); // PATCH /users/123 → User::onPatch(id: '123')
$map->delete('user.delete', '/users/{id}', '/user'); // DELETE /users/123 → User::onDelete(id: '123')
```

### Step 9: Module Configuration

**src/Module/AppModule.php:**

```php
<?php
declare(strict_types=1);

namespace {Vendor}\{Package}\Module;

use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\Resource\Module\JsonSchemaModule;
use BEAR\AuraRouterModule\AuraRouterModule;
use Ray\AuraSqlModule\AuraSqlModule;
use Ray\MediaQuery\MediaQueryModule;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        // Database connection
        $this->install(new AuraSqlModule(
            (string) getenv('DB_DSN'),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASS'),
            (string) getenv('DB_SLAVE'),
        ));

        // MediaQuery - auto-binds interfaces with #[DbQuery] annotations
        // Query/Command interfaces are automatically mapped to SQL files via DbQuery annotations
        $sqlDir = $this->appMeta->appDir . '/var/sql';
        $this->install(new MediaQueryModule($sqlDir));

        // JsonSchema validation
        $this->install(new JsonSchemaModule(
            $this->appMeta->appDir . '/var/json_schema',
            $this->appMeta->appDir . '/var/json_validate'
        ));

        // Aura Router (only when selected)
        // $this->install(new AuraRouterModule(
        //     $this->appMeta->appDir . '/var/conf/aura.route.php'
        // ));

        $this->install(new PackageModule());
    }
}
```

**Note:** Interface methods annotated with `#[DbQuery]` are automatically bound to SQL files by MediaQueryModule. For example, `#[DbQuery('user_item')]` executes `var/sql/user_item.sql`.

### Step 10: Create Directories

```bash
mkdir -p src/Entity
mkdir -p src/Query
mkdir -p src/Resource/App
mkdir -p var/sql
mkdir -p var/db
mkdir -p var/phinx/migrations
mkdir -p var/phinx/seeds
mkdir -p var/json_validate
mkdir -p var/json_schema
mkdir -p var/conf
mkdir -p docs
mkdir -p tests/Resource/App
mkdir -p tests/Entity
```

### Step 11: Verification and Completion

```bash
# Fix coding standards
composer cs-fix

# Static analysis
composer sa

# Run migrations
./vendor/bin/phinx migrate

# Run tests
composer test

# Verify application startup
php -S localhost:8080 -t public
```

## Output Summary

See references/output-summary.md for all output templates (Inside-Out Phase 1/2 and Outside-In completion).

## Error Handling

### When a Directory Already Exists

Use AskUserQuestion tool to ask: "The directory {Vendor}.{Package} already exists. What would you like to do?"

- **Create with a different name (add suffix)**
- **Cancel**

**Note:** An overwrite option is not provided (to prevent data loss)

### When composer create-project Fails

```
Error: Failed to create project skeleton
Details: {error_message}

Actions:
1. Check PHP/Composer version
2. Check network connection
3. Check available disk space
```

## Detailed Conversion Rules from ALPS

See references/alps-conversion-rules.md for type mapping tables and naming conventions.

## References

- ALPS Specification: https://alps-io.github.io/spec/
- BEAR.Sunday Manual: https://bearsunday.github.io/
- Ray.MediaQuery: https://github.com/ray-di/Ray.MediaQuery
- Aura.Router: https://github.com/auraphp/Aura.Router
- app-state-diagram: https://github.com/alps-asd/app-state-diagram
