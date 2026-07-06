# Extraction Tools

## Project Verification

```bash
# Verify directory structure
ls -la src/Resource/App/
ls -la src/Resource/Page/

# Check namespace from composer.json
cat composer.json | grep -A5 '"autoload"'
```

## Information Source Priority

When extracting ALPS information, use sources in this priority order:

1. **BEAR.ApiDoc HTML** (most complete) - Generated HTML contains semantic markup with full state transition data. Parse if available at `docs/alps.html` or similar location.
2. **var/schema/ JsonSchema files** (Ontology + Taxonomy) - Response schemas define properties and types. Request schemas define input parameters.
3. **PHP source extraction** (Choreography from on* methods, #[Link], templates) - Scan resource classes for methods, parameters, Link attributes, and JsonSchema attributes.

**Note:** This skill performs lightweight extraction from existing files. No `composer install` or dependency installation is required.

## Reading #[Alps] Attributes

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

## Reading #[Link] Attributes

```php
// Get transition destination information
#[Link(rel: 'goUser', href: '/user{?id}')]
#[Link(rel: 'doCreateUser', href: '/users')]
public function onGet(): static { }
```

## Reading JsonSchema

```php
// Get property information
#[JsonSchema(schema: 'users.json')]
public function onGet(): static { }
```

Extract property definitions from the corresponding JsonSchema file (`var/json_schema/users.json`).

## Reading Method Parameters

```php
// Extract input parameters
public function onPost(string $userName, string $email): static { }
// -> Extract userName, email as Ontology
```

## ALPS Profile Validation and Output

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
