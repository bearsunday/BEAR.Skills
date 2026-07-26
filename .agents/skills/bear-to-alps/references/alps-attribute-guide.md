# #[Alps] Attribute Addition Guide

## Adding Attributes to Resource Classes

When the user selects "Add Attributes" mode, add the inferred ALPS IDs as attributes to resource classes.

### Before

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

### After

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

## Notes When Adding Attributes

1. Verify `bear/api-doc` is in composer.json (it provides `BEAR\ApiDoc\Annotation\Alps`); if absent, run `composer require bear/api-doc` first
2. Add use statement: `use BEAR\ApiDoc\Annotation\Alps;`
3. Add #[Alps] attribute to the class (Taxonomy)
4. Add #[Alps] attribute to each on* method (Choreography)
5. Update #[Link] rel to ALPS ID (for consistency)

## Updating #[Link] rel

Update the rel of existing #[Link] attributes to ALPS IDs for consistency:

### Before

```php
#[Link(rel: 'user', href: '/user{?id}')]
#[Link(rel: 'create', href: '/users')]
```

### After

```php
#[Link(rel: 'goUser', href: '/user{?id}')]
#[Link(rel: 'doCreateUser', href: '/users')]
```

## Consistency Check with Existing #[Alps]

When #[Alps] attributes already exist, check consistency with the generated ALPS:

```text
Consistency check results:

- Users.php #[Alps('UserList')] - OK
- User.php #[Alps('User')] - OK
- Product.php #[Alps('ProductDetail')] - Recommended: 'Product'
  Reason: Singular resources should use the simple '{Entity}' form

Confirm: Do you want to proceed as is?
  - Yes, proceed as is
  - No, update to recommended values
```
