# HTTP and REST Patterns

> The canonical HTTP status / REST convention (including 201 + Location, 409
> unique-key conflict, 422 validation, action-style POST = 200) lives in
> `bear-clean-style/references/resource-patterns.md`. This file focuses on the
> **review** angle: how to detect status-code and Location-header issues.

## HTTP Status Codes

Return appropriate status codes.

| Operation | Code |
|-----------|------|
| GET success | 200 OK |
| POST success (creation) | 201 Created |
| Delete success | 204 No Content |
| Not found | 404 Not Found |
| Validation error | 400 Bad Request |

## 201 Created and Location Header

`onPost` that creates a resource should return 201 status and `Location` header together.

```php
// ❌ Problem: Creating but returning 200, no Location
public function onPost(string $title): static
{
    $id = $this->command->create($title);
    $this->body = ['id' => $id];
    return $this;
}

// ✅ Recommended: 201 + Location header
public function onPost(string $title): static
{
    $id = $this->command->create($title);

    $this->code = 201;
    $this->headers['Location'] = "/article?id={$id}";
    $this->body = ['id' => $id];

    return $this;
}
```

**Detection pattern:**
- `onPost` calls `$this->command->create` or `$this->command->add`
- But `$this->code = 201` is missing
- Or `$this->headers['Location']` is missing

| Pattern | Grade |
|---------|-------|
| 201 + Location present | ✅ Recommended |
| 201 present, Location missing | ⚠️ Warning (recommend adding Location) |
| Remains 200 (with creation logic) | ❌ Problem |

## Page Resource Restrictions

Page resources should only use `onGet` and `onPost`.

```php
// ❌ Problem: onPut/onDelete in Page
class UserPage extends ResourceObject {
    public function onDelete(int $id): static  // NG
}

// ✅ Recommended: CRUD in App resource, Page uses GET/POST only
```
