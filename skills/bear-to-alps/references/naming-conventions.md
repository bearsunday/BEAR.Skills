# ALPS Naming Conventions

## Resource Class -> Taxonomy Inference

| Resource Class | ALPS Taxonomy ID |
|--------------|-----------------|
| Users.php | UserList |
| User.php | UserDetail or User |
| Products.php | ProductList |
| Product.php | Product |

### Taxonomy ID Rules for Singular Resources

| Class Pattern | Taxonomy ID | Description |
|--------------|-------------|-------------|
| {Entity}s.php | {Entity}List | Plural = list |
| {Entity}.php | {Entity} | Singular = detail (simple form) |
| Index.php | Home | Top page |

- Basic: `User.php` -> `User` (simple)
- Use `UserDetail` only when explicit distinction is needed
- Recommended pairing for list and detail: `UserList` / `User`
- Avoid `{Entity}Detail` as it is redundant

## Method -> Choreography Inference

| Method | ALPS Choreography ID | Type |
|---------|---------------------|------|
| onGet() | go{TaxonomyId} | safe |
| onPost() | doCreate{Entity} | unsafe |
| onPut() | doUpdate{Entity} | idempotent |
| onPatch() | doModify{Entity} | idempotent |
| onDelete() | doDelete{Entity} or doRemove{Entity} | idempotent |

## HTTP Method -> ALPS Type

| HTTP Method | ALPS Type | ID Prefix | Description |
|-------------|-----------|-----------|-------------|
| GET | safe | go | Safe read operation |
| POST | unsafe | do | Create new resource (non-idempotent) |
| PUT | idempotent | do | Full update (idempotent) |
| PATCH | idempotent | do | Partial update (idempotent) |
| DELETE | idempotent | do | Delete (idempotent) |

## Transition Information from #[Link] and rt Resolution

```php
#[Link(rel: 'goUser', href: '/user{?id}')]
// -> ALPS: {"id": "goUser", "type": "safe", "rt": "#UserDetail"}
```

### rt (Return Type) Resolution Logic

1. **Identify the resource class from href:**
   ```text
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

## JsonSchema -> Ontology

| JsonSchema Type | ALPS | schema.org |
|----------------|------|------------|
| "type": "string", "format": "email" | email | schema.org/email |
| "type": "string", "format": "date-time" | dateCreated | schema.org/dateCreated |
| "type": "integer" | count, quantity | schema.org/Integer |
| "type": "boolean" | isActive | schema.org/Boolean |
