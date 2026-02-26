<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Smoke;

use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;
use MyVendor\MyProject\Injector;
use PHPUnit\Framework\TestCase;

use function assert;

class WorkflowTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance('app');
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    /**
     * Todo: Create -> List workflow
     *
     * [Create Todo] --post--> 201 --get--> [Todo List] (contains created item)
     */
    public function testTodoCreateAndList(): void
    {
        // Create
        $post = $this->resource->post('app://self/todo', ['title' => 'Workflow Test']);
        assert($post instanceof ResourceObject);
        $this->assertSame(201, $post->code);
        $id = $post->body['id'];
        $this->assertIsString($id);

        // Read (list) and verify the created item exists
        $list = $this->resource->get('app://self/todo');
        assert($list instanceof ResourceObject);
        $this->assertSame(200, $list->code);

        $found = false;
        foreach ($list->body['todos'] as $todo) {
            if ($todo->id === $id) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Created todo should appear in the list');
    }
}
