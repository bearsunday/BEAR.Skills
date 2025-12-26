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
        $ro = $this->resource->get('app://self/todo');

        assert($ro instanceof ResourceObject);
        $this->assertSame(200, $ro->code);
        $this->assertArrayHasKey('todos', $ro->body);
        $this->assertIsArray($ro->body['todos']);
    }

    public function testOnPost(): void
    {
        $ro = $this->resource->post('app://self/todo', ['title' => 'Test Todo']);

        assert($ro instanceof ResourceObject);
        $this->assertSame(201, $ro->code);
        $this->assertArrayHasKey('Location', $ro->headers);
        $this->assertArrayHasKey('id', $ro->body);
        $this->assertIsString($ro->body['id']);
    }

    public function testOnPostAndGet(): void
    {
        // Create a new todo
        $post = $this->resource->post('app://self/todo', ['title' => 'Integration Test Todo']);
        assert($post instanceof ResourceObject);
        $this->assertSame(201, $post->code);
        $id = $post->body['id'];

        // Verify it appears in the list
        $list = $this->resource->get('app://self/todo');
        assert($list instanceof ResourceObject);
        $this->assertSame(200, $list->code);

        $todos = $list->body['todos'];
        $found = false;
        foreach ($todos as $todo) {
            if ($todo->id === $id) {
                $found = true;
                $this->assertSame('Integration Test Todo', $todo->title);
                $this->assertFalse($todo->completed);
                break;
            }
        }
        $this->assertTrue($found, 'Created todo should appear in the list');
    }
}
