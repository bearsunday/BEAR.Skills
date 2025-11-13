<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Resource\App;

use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use MyVendor\MyProject\Injector;
use PHPUnit\Framework\TestCase;

use function assert;

class TodoTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance('app');
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    public function testOnGet(): void
    {
        $todo = $this->resource->get('app://self/todo');
        assert($todo instanceof ResourceObject);

        $this->assertSame(200, $todo->code);
        $this->assertArrayHasKey('todos', $todo->body);
        $this->assertIsArray($todo->body['todos']);
    }

    public function testOnPost(): void
    {
        $todo = $this->resource->post('app://self/todo', [
            'title' => 'Test Todo',
        ]);
        assert($todo instanceof ResourceObject);

        $this->assertSame(201, $todo->code);
        $this->assertArrayHasKey('Location', $todo->headers);
        $this->assertArrayHasKey('id', $todo->body);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $todo->body['id']);
    }

    public function testOnPostAndGet(): void
    {
        // Create a todo
        $created = $this->resource->post('app://self/todo', [
            'title' => 'Integration Test Todo',
        ]);
        assert($created instanceof ResourceObject);

        $this->assertSame(201, $created->code);
        $this->assertArrayHasKey('id', $created->body);

        // Retrieve todos list
        $list = $this->resource->get('app://self/todo');
        assert($list instanceof ResourceObject);

        $this->assertSame(200, $list->code);
        $this->assertArrayHasKey('todos', $list->body);
        $this->assertNotEmpty($list->body['todos']);
    }
}
