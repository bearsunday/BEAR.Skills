<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\App;

use BEAR\Resource\Annotation\JsonSchema;
use BEAR\Resource\ResourceObject;
use MyVendor\MyProject\Query\TodoCommandInterface;
use MyVendor\MyProject\Query\TodoQueryInterface;

class Todo extends ResourceObject
{
    public function __construct(
        private readonly TodoQueryInterface $query,
        private readonly TodoCommandInterface $command
    ) {
    }

    #[JsonSchema(key: 'todos', schema: 'todo-list.json')]
    public function onGet(): static
    {
        $this->body = [
            'todos' => $this->query->list(),
        ];

        return $this;
    }

    #[JsonSchema(schema: 'todo-post.json')]
    public function onPost(string $title): static
    {
        $id = $this->generateId();
        $this->command->add($id, $title);

        $this->code = 201;
        $this->headers['Location'] = "/todo?id={$id}";
        $this->body = ['id' => $id];

        return $this;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
