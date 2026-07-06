# Outside-In (Full Stack) Flow Details

When Outside-In is selected, execute the following Step 6.

## Step 6: Invoke bear-resource-gen

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

## Step 7: Add #[Alps] and #[Link] Attributes

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

### Role of the #[Alps] attribute

- **Applied to class**: Mapping to Taxonomy (state). Class = URL = state
- **Applied to method**: Mapping to Choreography (transition). Applied to the method that returns the result of the transition
- **IS_REPEATABLE**: Multiple #[Alps] can be applied to the same method

### Multiple #[Alps] attributes (IS_REPEATABLE)

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

### Using ALPS IDs in #[Link] rel

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
