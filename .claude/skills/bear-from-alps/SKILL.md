---
name: bear-from-alps
description: Generate a BEAR.Sunday project from an ALPS profile. Interactively collects vendor name, package name, router selection, etc. to build the project. Uses bear-resource-gen internally.
user-invocable: true
---

# ALPS to BEAR.Sunday Project Generator

A skill that generates an entire BEAR.Sunday project from an ALPS profile.

## When to Use This Skill

- You want to start a new BEAR.Sunday project
- You want to create a project structure based on an ALPS profile
- You want to proceed with project initial setup interactively

## Prerequisites

- PHP 8.1 or higher
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

#### 4.1 Update composer.json

```json
{
  "name": "{vendor}/{package}",
  "autoload": {
    "psr-4": {
      "{Vendor}\\{Package}\\": "src/"
    }
  }
}
```

#### 4.2 Generate Environment Variable Files (Koriym.EnvJson)

**env.schema.json** - Schema definition:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["TZ", "DB_DSN"],
  "properties": {
    "TZ": {
      "description": "Timezone",
      "type": "string",
      "default": "Asia/Tokyo"
    },
    "DB_DSN": {
      "description": "Database connection DSN",
      "type": "string",
      "examples": ["sqlite:var/db/app.sqlite3", "mysql:host=localhost;dbname=mydb"]
    },
    "DB_USER": {
      "description": "Database user",
      "type": "string"
    },
    "DB_PASS": {
      "description": "Database password",
      "type": "string"
    },
    "DB_SLAVE": {
      "description": "Slave database DSN for read replica",
      "type": "string"
    }
  }
}
```

**env.json** - Environment variables:

```json
{
  "$schema": "./env.schema.json",
  "TZ": "Asia/Tokyo",
  "DB_DSN": "sqlite:var/db/app.sqlite3",
  "DB_USER": "",
  "DB_PASS": "",
  "DB_SLAVE": ""
}
```

**env.dist.json** - Distribution template (exclude env.json in .gitignore and commit this file instead):

```json
{
  "$schema": "./env.schema.json",
  "TZ": "Asia/Tokyo",
  "DB_DSN": "sqlite:var/db/app.sqlite3",
  "DB_USER": "",
  "DB_PASS": "",
  "DB_SLAVE": ""
}
```

#### 4.3 Generate phinx.php

```php
<?php
use Koriym\EnvJson\EnvJson;

$dir = __DIR__;
(new EnvJson())->load($dir);

return [
    'paths' => [
        'migrations' => $dir . '/var/phinx/migrations',
        'seeds' => $dir . '/var/phinx/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name' => $dir . '/var/db/app',
            'suffix' => '.sqlite3',
        ],
        'testing' => [
            'adapter' => 'sqlite',
            'name' => $dir . '/var/db/test',
            'suffix' => '.sqlite3',
        ],
    ],
];
```

#### 4.4 Update .gitignore

```bash
# Add the following to .gitignore
echo "env.json" >> .gitignore
echo "var/db/*.sqlite3" >> .gitignore
```

#### 4.5 Update bin/app.php (EnvJson Loading)

Load EnvJson at the application entry point:

**bin/app.php:**
```php
<?php
declare(strict_types=1);

use Koriym\EnvJson\EnvJson;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables
(new EnvJson())->load(dirname(__DIR__));

exit((require dirname(__DIR__) . '/bootstrap.php')($argv));
```

**public/index.php:**
```php
<?php
declare(strict_types=1);

use Koriym\EnvJson\EnvJson;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables
(new EnvJson())->load(dirname(__DIR__));

exit((require dirname(__DIR__) . '/bootstrap.php')());
```

#### 4.6 Configure tests/bootstrap.php

```php
<?php
declare(strict_types=1);

use Koriym\EnvJson\EnvJson;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load test environment variables (prefer env.test.json if it exists)
$envFile = file_exists(dirname(__DIR__) . '/env.test.json') ? 'env.test.json' : 'env.json';
(new EnvJson())->load(dirname(__DIR__), $envFile);
```

**env.test.json** - Test environment variables:
```json
{
  "$schema": "./env.schema.json",
  "TZ": "Asia/Tokyo",
  "DB_DSN": "sqlite:var/db/test.sqlite3",
  "DB_USER": "",
  "DB_PASS": "",
  "DB_SLAVE": ""
}
```

#### 4.7 Place the ALPS Profile

```bash
mkdir -p docs
cp {alps_profile_path} docs/alps.json

# Generate HTML documentation
asd docs/alps.json -o docs/alps.html
```

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

When Inside-Out is selected, execute the following Steps 6A through 6E.

### Step 6A: Generate FakeJson

Generate FakeJson files for each Taxonomy in the ALPS profile.

**Directory structure:**
```
var/fake/
├── App/
│   ├── Users.json      # List resource
│   ├── User.json       # Individual resource
│   └── ...
└── Page/
    └── Index.json      # Home page
```

**Example: var/fake/App/Users.json**
```json
{
  "users": [
    {
      "userId": "user-001",
      "userName": "Alice",
      "email": "alice@example.com",
      "dateCreated": "2024-01-15T10:30:00+09:00"
    },
    {
      "userId": "user-002",
      "userName": "Bob",
      "email": "bob@example.com",
      "dateCreated": "2024-01-16T14:20:00+09:00"
    }
  ]
}
```

**Example: var/fake/App/User.json**
```json
{
  "userId": "user-001",
  "userName": "Alice",
  "email": "alice@example.com",
  "dateCreated": "2024-01-15T10:30:00+09:00"
}
```

**Conversion rules from ALPS to FakeJson:**
- Extract properties from Taxonomy child descriptors
- Generate appropriate sample values from schema.org def
- List resources use array format, individual resources use object format

### Step 6B: Generate Resource Classes (Stub Version)

Generate stub resource classes that use FakeJsonModule.

**src/Resource/App/Users.php:**
```php
<?php
declare(strict_types=1);

namespace {Vendor}\{Package}\Resource\App;

use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

#[Alps('UserList')]  // Taxonomy - maps to ALPS state
class Users extends ResourceObject
{
    #[Alps('goUserList')]  // Choreography - transition that returns this state
    #[Link(rel: 'goUser', href: '/user{?id}')]
    #[Link(rel: 'doCreateUser', href: '/users')]
    #[JsonSchema(schema: 'users.json')]
    public function onGet(): static
    {
        // Content is automatically set from var/fake/App/Users.json by FakeJsonModule
        return $this;
    }

    #[Alps('doCreateUser')]  // Choreography - unsafe transition
    #[JsonSchema(schema: 'user-post.json')]
    public function onPost(string $userName, string $email): static
    {
        $this->code = 201;
        $this->headers['Location'] = '/users/user-new-id';
        return $this;
    }
}
```

### Step 6C: Configure FakeJsonModule

**src/Module/FakeJsonModule.php:**
```php
<?php
declare(strict_types=1);

namespace {Vendor}\{Package}\Module;

use BEAR\FakeJson\FakeJsonModule as BaseFakeJsonModule;
use Ray\Di\AbstractModule;

class FakeJsonModule extends AbstractModule
{
    protected function configure(): void
    {
        $fakeDir = dirname(__DIR__, 2) . '/var/fake';
        $this->install(new BaseFakeJsonModule($fakeDir));
    }
}
```

**Module switching by context:**

FakeJsonModule is used in a dedicated context Module, not in AppModule:

**src/Module/FakeModule.php** (for Phase 1):
```php
<?php
declare(strict_types=1);

namespace {Vendor}\{Package}\Module;

use Ray\Di\AbstractModule;

class FakeModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->install(new AppModule());
        $this->install(new FakeJsonModule());
    }
}
```

**Usage:**
```bash
# Phase 1: Using FakeJson (API design phase)
export APP_CONTEXT=fake
php -S localhost:8080 -t public

# Phase 2 onward: Using production DB
export APP_CONTEXT=app
php -S localhost:8080 -t public
```

**Context loading in bootstrap.php:**
```php
$context = getenv('APP_CONTEXT') ?: 'app';
$injector = Injector::getInstance($context);
```

### Step 6D: Create Tests

Resource tests using FakeJson:

**tests/Resource/App/UsersTest.php:**
```php
<?php
declare(strict_types=1);

namespace {Vendor}\{Package}\Resource\App;

use BEAR\Resource\ResourceInterface;
use {Vendor}\{Package}\Injector;
use PHPUnit\Framework\TestCase;

class UsersTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance('fake');
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    public function testOnGet(): void
    {
        $ro = $this->resource->get('app://self/users');

        $this->assertSame(200, $ro->code);
        $this->assertArrayHasKey('users', $ro->body);
        $this->assertIsArray($ro->body['users']);
        $this->assertArrayHasKey('userId', $ro->body['users'][0]);
        $this->assertArrayHasKey('userName', $ro->body['users'][0]);
    }

    public function testOnPost(): void
    {
        $ro = $this->resource->post('app://self/users', [
            'userName' => 'Charlie',
            'email' => 'charlie@example.com',
        ]);

        $this->assertSame(201, $ro->code);
        $this->assertArrayHasKey('Location', $ro->headers);
    }
}
```

### Step 6E: Generate API Docs and User Confirmation

```bash
# Generate API Doc from JsonSchema
asd docs/alps.json -o docs/alps.html

# Start development server
php -S localhost:8080 -t public

# Verify API behavior (Web Router)
curl http://localhost:8080/users
curl http://localhost:8080/user?id=user-001

# When Aura Router is selected
# curl http://localhost:8080/users/user-001
```

**Confirm with user:**
```
Please review the API design:

1. Check the API state transition diagram at docs/alps.html
2. Verify FakeJson responses via curl
3. Check request/response formats in JsonSchema

If everything looks good, we will proceed to Phase 2 (DB implementation).
If changes are needed, we will update FakeJson and JsonSchema.
```

---

## Phase 2: Implementation (Continuation of Inside-Out)

After user agreement, implement the DB. This phase uses the bear-resource-gen skill.

### Phase 2 Execution Conditions

Use AskUserQuestion tool to ask:

- **Yes, proceed to Phase 2**
- **No, modify FakeJson**
- **No, modify JsonSchema**

### Phase 2 Steps

1. **Invoke bear-resource-gen**
   - Generate Entity/Query/Command/SQL using existing FakeJson as reference
   - Generate migration files

2. **Update Resource classes to production implementation**
   - Switch from FakeJsonModule to MediaQueryModule
   - Inject Query/Command interfaces

3. **Remove FakeJsonModule**
   - Delete src/Module/FakeJsonModule.php
   - Remove FakeJsonModule install from AppModule

4. **Update tests**
   - Change to use test DB
   - Add migration execution to setUp

```php
// Production resource class (after Phase 2 completion)
use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

#[Alps('UserList')]  // Taxonomy - maps to ALPS state
class Users extends ResourceObject
{
    public function __construct(
        private readonly UserQueryInterface $query,
        private readonly UserCommandInterface $command,
    ) {}

    #[Alps('goUserList')]  // Choreography - safe transition
    #[Link(rel: 'goUser', href: '/user{?id}')]
    #[Link(rel: 'doCreateUser', href: '/users')]
    #[JsonSchema(schema: 'users.json')]
    public function onGet(): static
    {
        $this->body = ['users' => $this->query->list()];
        return $this;
    }

    #[Alps('doCreateUser')]  // Choreography - unsafe transition
    #[JsonSchema(schema: 'user-post.json')]
    public function onPost(string $userName, string $email): static
    {
        $id = $this->generateId();
        $this->command->add($id, $userName, $email);

        $this->code = 201;
        $this->headers['Location'] = "/users/{$id}";
        $this->body = ['id' => $id];
        return $this;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

---

## Outside-In (Full Stack) Flow

When Outside-In is selected, execute the following Step 6.

### Step 6: Invoke bear-resource-gen

**Invoke bear-resource-gen for each entity.**

Convert information extracted from ALPS to bear-resource-gen input format:

```markdown
Entity: {EntityName} ({properties_from_semantics})

Operations:
- List (GET) - when reachable via go transition from {EntityList}
- Get detail (GET) - when {EntityDetail} exists
- Create (POST) - when doCreate{Entity} exists
- Update (PUT) - when doUpdate{Entity} exists
- Delete (DELETE) - when doDelete{Entity} exists
```

**Example: Conversion from UserList and User**

```
ALPS:
{"id": "userId", "title": "User ID"}
{"id": "userName", "title": "User Name"}
{"id": "UserList", "descriptor": [{"href": "#userId"}, {"href": "#userName"}, {"href": "#goUser"}]}
{"id": "User", "descriptor": [{"href": "#userId"}, {"href": "#userName"}, {"href": "#doUpdateUser"}, {"href": "#doDeleteUser"}]}
{"id": "goUser", "type": "safe", "rt": "#User"}
{"id": "doCreateUser", "type": "unsafe", "rt": "#User"}
{"id": "doUpdateUser", "type": "idempotent", "rt": "#User"}
{"id": "doDeleteUser", "type": "idempotent", "rt": "#UserList"}

↓ Convert to bear-resource-gen input format

Entity: User (userId: string, userName: string)

Operations:
- List users (GET)
- Get user detail (GET)
- Create user (POST)
- Update user (PUT)
- Delete user (DELETE)
```

**Generated files (bear-resource-gen output):**

```
src/
├── Entity/User.php
├── Query/
│   ├── UserQueryInterface.php
│   └── UserCommandInterface.php
└── Resource/App/
    ├── Users.php      # List resource
    └── User.php       # Individual resource
var/
├── sql/
│   ├── user_list.sql
│   ├── user_item.sql
│   ├── user_add.sql
│   ├── user_update.sql
│   └── user_delete.sql
├── schema/
│   ├── request/
│   │   ├── user-post.json
│   │   └── user-put.json
│   └── response/
│       ├── users.json
│       └── user.json
└── phinx/migrations/
    └── YYYYMMDDHHMMSS_create_user_table.php
```

### Step 7: Add #[Alps] and #[Link] Attributes

Add attributes to resource classes and methods based on ALPS state (Taxonomy) and transition (Choreography) information:

```php
// src/Resource/App/Users.php
use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\Link;

#[Alps('UserList')]  // Taxonomy - ALPS state
class Users extends ResourceObject
{
    public function __construct(
        private readonly UserQueryInterface $query,
        private readonly UserCommandInterface $command,
    ) {}

    /**
     * @return array<User>
     */
    #[Alps('goUserList')]  // Choreography - transition that returns this state
    #[Link(rel: 'goUser', href: '/user{?id}')]
    #[Link(rel: 'doCreateUser', href: '/users')]
    public function onGet(): static
    {
        $this->body = ['users' => $this->query->list()];
        return $this;
    }

    #[Alps('doCreateUser')]  // Choreography - unsafe transition
    public function onPost(string $userName, string $email): static
    {
        // ...
    }
}

// src/Resource/App/User.php
use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\Link;

#[Alps('User')]  // Taxonomy - ALPS state
class User extends ResourceObject
{
    public function __construct(
        private readonly UserQueryInterface $query,
        private readonly UserCommandInterface $command,
    ) {}

    #[Alps('goUser')]  // Choreography - transition that returns this state
    #[Link(rel: 'goUserList', href: '/users')]
    #[Link(rel: 'doUpdateUser', href: '/user{?id}')]
    #[Link(rel: 'doDeleteUser', href: '/user{?id}')]
    public function onGet(string $id): static
    {
        // ...
    }

    #[Alps('doUpdateUser')]  // Choreography - idempotent transition
    public function onPut(string $id, string $userName, string $email): static
    {
        // ...
    }

    #[Alps('doDeleteUser')]  // Choreography - idempotent transition
    public function onDelete(string $id): static
    {
        // ...
    }
}
```

**Role of the #[Alps] attribute:**
- **Applied to class**: Mapping to Taxonomy (state). Class = URL = state
- **Applied to method**: Mapping to Choreography (transition). Applied to the method that returns the result of the transition
- **IS_REPEATABLE**: Multiple #[Alps] can be applied to the same method

**Multiple #[Alps] attributes (IS_REPEATABLE):**
When the same method has multiple semantics:
```php
// Different semantics based on parameter differences
#[Alps('goUserList')]
#[Alps('goUserListByAge')]
public function onGet(?bool $orderByAge = false): static

// Different operations with the same PUT
#[Alps('doModifyName')]
#[Alps('doDeactivateUser')]
public function onPut(string $id, ?string $name = null, ?bool $active = null): static
```

**Using ALPS IDs in #[Link] rel:**
By using ALPS IDs as rel values, consistency is maintained between REST (how) and domain vocabulary (what)

| ALPS Taxonomy | Class | #[Alps] on class |
|--------------|-------|------------------|
| UserList | Users | #[Alps('UserList')] |
| User | User | #[Alps('User')] |
| Product | Product | #[Alps('Product')] |

| ALPS Choreography | Method | #[Alps] on method | #[Link] rel |
|------------------|--------|-------------------|-------------|
| goUserList | Users::onGet() | #[Alps('goUserList')] | - |
| goUser | User::onGet() | #[Alps('goUser')] | goUser |
| doCreateUser | Users::onPost() | #[Alps('doCreateUser')] | doCreateUser |
| doUpdateUser | User::onPut() | #[Alps('doUpdateUser')] | doUpdateUser |
| doDeleteUser | User::onDelete() | #[Alps('doDeleteUser')] | doDeleteUser |

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
            $this->appMeta->appDir . '/var/schema/response',
            $this->appMeta->appDir . '/var/schema/request'
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
mkdir -p var/schema/request
mkdir -p var/schema/response
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

### Inside-Out (API First) - Phase 1 Completed

```markdown
## Phase 1: API Design Completed

### Basic Information
- Project: {Vendor}.{Package}/
- Namespace: {Vendor}\{Package}
- Router: {Web Router | Aura Router}
- Approach: Inside-Out (API First)
- Status: Phase 1 completed (awaiting user confirmation)

### Generated Files

#### Configuration Files
✓ composer.json
✓ env.json + env.schema.json + env.dist.json
✓ .gitignore
✓ bin/app.php, public/index.php
✓ docs/alps.json (ALPS)
✓ docs/alps.html (ASD)

#### FakeJson ({n} resources)
✓ var/fake/App/*.json
✓ var/fake/Page/*.json

#### Resources (Stub Version)
✓ src/Resource/App/*.php (using FakeJsonModule)
✓ src/Module/FakeJsonModule.php
✓ var/schema/**/*.json

#### Tests
✓ tests/bootstrap.php
✓ tests/Resource/App/*Test.php

### Next Steps

1. Review API Doc:
   open docs/alps.html

2. Verify FakeJson responses on development server:
   php -S localhost:8080 -t public
   curl http://localhost:8080/users

3. If everything looks good, proceed to Phase 2 (DB implementation)
   If changes are needed, modify FakeJson/JsonSchema
```

### Inside-Out (API First) - Phase 2 Completed

```markdown
## Phase 2: Implementation Completed

### Added/Updated Files

#### DB Related
✓ phinx.php
✓ var/phinx/migrations/*.php
✓ var/sql/*.sql
✓ env.test.json

#### Resources (Production Version)
✓ src/Entity/*.php
✓ src/Query/*Interface.php
✓ src/Resource/App/*.php (using MediaQueryModule)

#### Deleted Files
✗ src/Module/FakeJsonModule.php (deleted)
✗ var/fake/ (deleted or retained)

### Next Steps

1. Run migrations:
   ./vendor/bin/phinx migrate

2. Run tests:
   composer test

3. Verify production:
   php -S localhost:8080 -t public
```

### Outside-In (Full Stack) Completed

```markdown
## Project Generation Completed

### Basic Information
- Project: {Vendor}.{Package}/
- Namespace: {Vendor}\{Package}
- Router: {Web Router | Aura Router}
- Approach: Outside-In (Full Stack)

### Generated Files

#### Configuration Files
✓ composer.json
✓ env.json + env.schema.json + env.dist.json + env.test.json
✓ phinx.php
✓ .gitignore (excluding env.json, var/db/*.sqlite3)
✓ bin/app.php, public/index.php (EnvJson loading added)
✓ tests/bootstrap.php
✓ docs/alps.json (ALPS)
✓ docs/alps.html (ASD)

#### Resource Related ({n} entities)
✓ src/Entity/*.php
✓ src/Query/*Interface.php
✓ src/Resource/App/*.php
✓ var/sql/*.sql
✓ var/schema/**/*.json
✓ var/phinx/migrations/*.php

#### Module
✓ src/Module/AppModule.php

#### Routing (when Aura Router is selected)
✓ var/conf/aura.route.php

### Next Steps

1. Navigate to the project directory:
   cd {Vendor}.{Package}

2. Configure environment variables (edit env.json as needed)

3. Run database migrations:
   ./vendor/bin/phinx migrate

4. Run tests:
   composer test

5. Start the development server:
   php -S localhost:8080 -t public

6. Review API documentation:
   open docs/alps.html
```

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

### Type Mapping

| schema.org def | PHP Type | DB Type |
|----------------|----------|---------|
| schema.org/identifier | string | VARCHAR(64) |
| schema.org/name | string | VARCHAR(255) |
| schema.org/description | string | TEXT |
| schema.org/email | string | VARCHAR(255) |
| schema.org/DateTime | DateTimeInterface | DATETIME |
| schema.org/Date | DateTimeInterface | DATE |
| schema.org/Integer | int | INTEGER |
| schema.org/Number | float | DECIMAL(10,2) |
| schema.org/Boolean | bool | BOOLEAN |
| (default) | string | VARCHAR(255) |

### Name Conversion

| ALPS | PHP/JSON | Database |
|------|----------|----------|
| userId | userId | user_id |
| dateCreated | dateCreated | date_created |
| UserList | Users (class) | - |
| User | User (class) | user (table) |

## References

- ALPS Specification: https://alps-io.github.io/spec/
- BEAR.Sunday Manual: https://bearsunday.github.io/
- Ray.MediaQuery: https://github.com/ray-di/Ray.MediaQuery
- Aura.Router: https://github.com/auraphp/Aura.Router
- app-state-diagram: https://github.com/alps-asd/app-state-diagram
