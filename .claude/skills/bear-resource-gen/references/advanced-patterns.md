# Advanced Resource Patterns

These patterns are added manually based on your resource relationships, after basic resource generation.

## #[Embed] - Embed Related Resources

Reference: [Resource Link documentation](https://bearsunday.github.io/manuals/1.0/en/resource_link.html)

Use when you need to include related resource data in the response:

```php
#[Embed(rel: 'author', src: 'app://self/user{?authorId}')]
public function onGet(string $id): static
{
    $post = $this->query->item($id);
    $this->body = [
        'id' => $post->id,
        'title' => $post->title,
        'authorId' => $post->authorId  // Used in URI template
    ];
    return $this;
}
// Response will include embedded 'author' resource
```

### Common use cases

- Blog post with author details
- Order with customer information
- Todo with creator information

## #[ResourceParam] - Inject from Other Resources

Reference: [Resource Param documentation](https://bearsunday.github.io/manuals/1.0/en/resource_param.html)

Use when you need to inject values from other resources (e.g., authentication):

```php
#[JsonSchema(schema: 'todo-post.json')]
public function onPost(
    #[ResourceParam('app://self/login#userId')] string $userId,
    string $title
): static {
    $id = $this->generateId();
    $this->command->add($id, $title, $userId); // Authenticated user ID
    // ...
}
```

### Common use cases

- Inject authenticated user ID
- Inject session information
- Inject global configuration values
