# Inside-Out (API First) Flow Details

When Inside-Out is selected, execute the following Steps 6A through 6E.

## Step 6A: Generate FakeJson

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

## Step 6B: Generate Resource Classes (Stub Version)

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

## Step 6C: Configure FakeJsonModule

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

## Step 6D: Create Tests

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

## Step 6E: Generate API Docs and User Confirmation

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
