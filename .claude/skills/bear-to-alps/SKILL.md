---
name: bear-to-alps
description: Generate ALPS profiles from existing BEAR.Sunday projects. Reads #[Alps] attributes or infers from resource structure to create ALPS profiles. Optionally adds #[Alps] attributes to resources.
user-invocable: true
---

# BEAR.Sunday to ALPS Profile Generator

A skill that scans existing BEAR.Sunday projects to generate ALPS profiles.

## When to Use This Skill

- You want to create an API design document (ALPS profile) from an existing BEAR.Sunday project
- You want to add #[Alps] attributes to existing resources
- You want to visualize resource structure with ALPS
- You want to automate API design documentation

## Prerequisites

- PHP 8.1 or higher
- An existing BEAR.Sunday project
- asd (app-state-diagram) command - Used for ALPS validation and HTML generation

## Step-by-Step Process

### Step 1: Verify the Project

**Verify the following first:**

1. Confirm the project directory path
2. Identify the namespace from composer.json
3. List the resource classes under src/Resource/

```bash
# Verify directory structure
ls -la src/Resource/App/
ls -la src/Resource/Page/

# Check namespace from composer.json
cat composer.json | grep -A5 '"autoload"'
```

### Information Source Priority

When extracting ALPS information, use sources in this priority order:

1. **BEAR.ApiDoc HTML** (most complete) - Generated HTML contains semantic markup with full state transition data. Parse if available at `docs/alps.html` or similar location.
2. **var/schema/ JsonSchema files** (Ontology + Taxonomy) - Response schemas define properties and types. Request schemas define input parameters.
3. **PHP source extraction** (Choreography from on* methods, #[Link], templates) - Scan resource classes for methods, parameters, Link attributes, and JsonSchema attributes.

**Note:** This skill performs lightweight extraction from existing files. No `composer install` or dependency installation is required.

### Step 2: Mode Selection

```
AskUserQuestion:
  Please select a generation mode:

  - Extract & Generate (Recommended)
    -> Uses #[Alps] attributes if present, otherwise infers from resource structure
    -> Generates an ALPS profile

  - Add Attributes
    -> Infers ALPS from resource structure
    -> Adds #[Alps] attributes to resource classes
    -> Also generates an ALPS profile
```

### Step 3: Scanning Resource Classes

Extract the following information from each resource class:

#### 3.1 Reading #[Alps] Attributes

```php
// Read existing #[Alps] attributes
use BEAR\ApiDoc\Annotation\Alps;

// Class level: Taxonomy (state)
#[Alps('UserList')]
class Users extends ResourceObject { }

// Method level: Choreography (transition)
#[Alps('goUserList')]
public function onGet(): static { }

#[Alps('doCreateUser')]
public function onPost(string $userName): static { }

// Multiple #[Alps] attributes (IS_REPEATABLE)
#[Alps('goUserList')]
#[Alps('goUserListByAge')]
public function onGet(?bool $orderByAge = false): static { }
```

**Handling multiple attributes:**
- If a method has multiple #[Alps] attributes, extract all of them as Choreography
- Each transition has the same rt (return type)
- Output as separate descriptors in the ALPS profile

#### 3.2 Reading #[Link] Attributes

```php
// Get transition destination information
#[Link(rel: 'goUser', href: '/user{?id}')]
#[Link(rel: 'doCreateUser', href: '/users')]
public function onGet(): static { }
```

#### 3.3 Reading JsonSchema

```php
// Get property information
#[JsonSchema(schema: 'users.json')]
public function onGet(): static { }
```

Extract property definitions from the corresponding JsonSchema file (`var/schema/response/users.json`).

#### 3.4 Reading Method Parameters

```php
// Extract input parameters
public function onPost(string $userName, string $email): static { }
// -> Extract userName, email as Ontology
```

### Step 4: Building the ALPS Structure

#### 4.1 Inference from Naming Conventions (when #[Alps] is absent)

| Resource Class | ALPS Taxonomy ID |
|--------------|-----------------|
| Users.php | UserList |
| User.php | UserDetail or User |
| Products.php | ProductList |
| Product.php | Product |

| Method | ALPS Choreography ID | Type |
|---------|---------------------|------|
| onGet() | go{TaxonomyId} | safe |
| onPost() | doCreate{Entity} | unsafe |
| onPut() | doUpdate{Entity} | idempotent |
| onPatch() | doModify{Entity} | idempotent |
| onDelete() | doDelete{Entity} or doRemove{Entity} | idempotent |

#### 4.2 Transition Information from #[Link] and rt Resolution

```php
#[Link(rel: 'goUser', href: '/user{?id}')]
// -> ALPS: {"id": "goUser", "type": "safe", "rt": "#UserDetail"}
```

**rt (return type) resolution logic:**

1. **Identify the resource class from href:**
   ```
   /user{?id} -> User.php -> User (Taxonomy ID)
   /users -> Users.php -> UserList (Taxonomy ID)
   ```

2. **URI pattern to class name mapping:**
   | href | Resource Class | Taxonomy ID |
   |------|---------------|-------------|
   | /user, /user{?id} | User.php | User |
   | /users | Users.php | UserList |
   | /product/{id} | Product.php | Product |
   | /products | Products.php | ProductList |

3. **rt resolution by transition type:**
   - `go*` (safe): Taxonomy of the destination
   - `doCreate*` (unsafe): Taxonomy of the created resource
   - `doUpdate*` (idempotent): Taxonomy of the updated resource (usually the same)
   - `doDelete*` (idempotent): Destination after deletion (usually the list)

4. **When the class has an #[Alps] attribute:**
   Use the #[Alps] value of that class as the Taxonomy ID

### Step 5: Generating the ALPS Profile

```json
{
  "$schema": "https://alps-io.github.io/schemas/alps.json",
  "alps": {
    "title": "{Package} API",
    "doc": {"value": "Generated from BEAR.Sunday resources"},
    "descriptor": [
      // Ontology: From method parameters and JsonSchema properties
      {"id": "userId", "title": "User ID", "def": "https://schema.org/identifier"},
      {"id": "userName", "title": "User Name", "def": "https://schema.org/name"},

      // Taxonomy: From resource classes
      {"id": "UserList", "title": "User List", "descriptor": [
        {"href": "#userId"},
        {"href": "#userName"},
        {"href": "#goUser"},
        {"href": "#doCreateUser"}
      ]},
      {"id": "User", "title": "User", "descriptor": [
        {"href": "#userId"},
        {"href": "#userName"},
        {"href": "#goUserList"},
        {"href": "#doUpdateUser"},
        {"href": "#doDeleteUser"}
      ]},

      // Choreography: From methods and #[Link]
      {"id": "goUserList", "type": "safe", "rt": "#UserList", "title": "View User List"},
      {"id": "goUser", "type": "safe", "rt": "#User", "title": "View User",
        "descriptor": [{"href": "#userId"}]},
      {"id": "doCreateUser", "type": "unsafe", "rt": "#User", "title": "Create User",
        "descriptor": [{"href": "#userName"}]},
      {"id": "doUpdateUser", "type": "idempotent", "rt": "#User", "title": "Update User",
        "descriptor": [{"href": "#userId"}, {"href": "#userName"}]},
      {"id": "doDeleteUser", "type": "idempotent", "rt": "#UserList", "title": "Delete User",
        "descriptor": [{"href": "#userId"}]}
    ]
  }
}
```

### Step 6: Output and Validation

```bash
# Save the ALPS profile
# docs/alps.json

# Validate
asd --validate docs/alps.json

# Generate HTML
asd docs/alps.json -o docs/alps.html

# View the state transition diagram
open docs/alps.html
```

### Step 7: Adding #[Alps] Attributes (Add Attributes Mode)

When the user selects "Add Attributes" mode,
add the inferred ALPS IDs as attributes to resource classes.

**Before:**
```php
<?php
declare(strict_types=1);

namespace MyVendor\MyProject\Resource\App;

use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

class Users extends ResourceObject
{
    #[Link(rel: 'user', href: '/user{?id}')]
    public function onGet(): static
    {
        // ...
    }

    public function onPost(string $userName, string $email): static
    {
        // ...
    }
}
```

**After:**
```php
<?php
declare(strict_types=1);

namespace MyVendor\MyProject\Resource\App;

use BEAR\ApiDoc\Annotation\Alps;
use BEAR\Resource\Annotation\Link;
use BEAR\Resource\ResourceObject;

#[Alps('UserList')]
class Users extends ResourceObject
{
    #[Alps('goUserList')]
    #[Link(rel: 'goUser', href: '/user{?id}')]
    #[Link(rel: 'doCreateUser', href: '/users')]
    public function onGet(): static
    {
        // ...
    }

    #[Alps('doCreateUser')]
    public function onPost(string $userName, string $email): static
    {
        // ...
    }
}
```

**Notes when adding attributes:**

1. Add use statement: `use BEAR\ApiDoc\Annotation\Alps;`
2. Add #[Alps] attribute to the class (Taxonomy)
3. Add #[Alps] attribute to each on* method (Choreography)
4. Update #[Link] rel to ALPS ID (for consistency)

### Step 8: Updating #[Link] rel

Update the rel of existing #[Link] attributes to ALPS IDs for consistency:

**Before:**
```php
#[Link(rel: 'user', href: '/user{?id}')]
#[Link(rel: 'create', href: '/users')]
```

**After:**
```php
#[Link(rel: 'goUser', href: '/user{?id}')]
#[Link(rel: 'doCreateUser', href: '/users')]
```

## Mapping Rules in Detail

### HTTP Method -> ALPS Type

| HTTP Method | ALPS Type | ID Prefix | Description |
|-------------|-----------|-----------|-------------|
| GET | safe | go | Safe read operation |
| POST | unsafe | do | Create new resource (non-idempotent) |
| PUT | idempotent | do | Full update (idempotent) |
| PATCH | idempotent | do | Partial update (idempotent) |
| DELETE | idempotent | do | Delete (idempotent) |

### Resource Class -> Taxonomy

| Class Pattern | Taxonomy ID | Description |
|--------------|-------------|-------------|
| {Entity}s.php | {Entity}List | Plural = list |
| {Entity}.php | {Entity} | Singular = detail (simple form) |
| Index.php | Home | Top page |

**Taxonomy ID rules for singular resources:**
- Basic: `User.php` -> `User` (simple)
- Use `UserDetail` only when explicit distinction is needed
- Recommended pairing for list and detail: `UserList` / `User`
- Avoid `{Entity}Detail` as it is redundant

### JsonSchema -> Ontology

| JsonSchema Type | ALPS | schema.org |
|----------------|------|------------|
| "type": "string", "format": "email" | email | schema.org/email |
| "type": "string", "format": "date-time" | dateCreated | schema.org/dateCreated |
| "type": "integer" | count, quantity | schema.org/Integer |
| "type": "boolean" | isActive | schema.org/Boolean |

## Error Handling

### When No Resource Classes Are Found

```
Warning: No resource classes found in src/Resource/App/

Actions:
1. Verify the project directory is correct
2. Verify composer autoload is configured
3. Verify resource classes extend ResourceObject
```

### Circular Reference Detection

```
Warning: Circular reference detected
  UserList -> goUser -> UserDetail -> goUserList -> UserList

Actions:
This is acceptable in ALPS. Verify that the state transition diagram has a cycle.
```

### Orphaned Taxonomy

```
Warning: No transitions defined to the following Taxonomy
  - OrphanPage

Actions:
1. Add a reference from another resource using #[Link]
2. Or remove this Taxonomy
```

## Output Summary

### Extract & Generate Mode

```markdown
## ALPS Profile Generation Complete

### Extracted Information

#### Ontology (Data Elements): {n} items
- userId, userName, email, dateCreated, ...

#### Taxonomy (States): {n} items
- UserList (from Users.php)
- User (from User.php)
- ...

#### Choreography (Transitions): {n} items
- goUserList (safe) -> UserList
- goUser (safe) -> User
- doCreateUser (unsafe) -> User
- ...

### Generated Files
- docs/alps.json
- docs/alps.html

### Next Steps
1. Review the state transition diagram:
   open docs/alps.html

2. Edit the profile as needed

3. To add #[Alps] attributes, run this skill again
   and select "Add Attributes" mode
```

### Add Attributes Mode

```markdown
## #[Alps] Attribute Addition Complete

### Updated Files ({n} files)

- src/Resource/App/Users.php
  - Class: #[Alps('UserList')]
  - onGet: #[Alps('goUserList')]
  - onPost: #[Alps('doCreateUser')]
  - #[Link] rel updated: user -> goUser, create -> doCreateUser

- src/Resource/App/User.php
  - Class: #[Alps('User')]
  - onGet: #[Alps('goUser')]
  - onPut: #[Alps('doUpdateUser')]
  - onDelete: #[Alps('doDeleteUser')]
  - #[Link] rel updated: users -> goUserList, edit -> doUpdateUser

### Additional Packages
composer require bear/api-doc (already added or needs to be added)

### Generated Files
- docs/alps.json
- docs/alps.html

### Next Steps
1. Review the updated resources
2. Run tests: composer test
3. Review the state transition diagram: open docs/alps.html
```

## Advanced: Consistency Check with Existing #[Alps]

When #[Alps] attributes already exist, check consistency with the generated ALPS:

```
Consistency check results:

- Users.php #[Alps('UserList')] - OK
- User.php #[Alps('User')] - OK
- Product.php #[Alps('ProductDetail')] - Recommended: 'Product'
  Reason: Singular resources should use the simple '{Entity}' form

Confirm: Do you want to proceed as is?
  - Yes, proceed as is
  - No, update to recommended values
```

## References

- ALPS Specification: https://alps-io.github.io/spec/
- BEAR.Sunday Resource: https://bearsunday.github.io/manuals/1.0/en/resource.html
- BEAR.ApiDoc: https://github.com/bearsunday/BEAR.ApiDoc
- app-state-diagram: https://github.com/alps-asd/app-state-diagram
