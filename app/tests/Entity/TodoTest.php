<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Entity;

use PHPUnit\Framework\TestCase;

class TodoTest extends TestCase
{
    public function testConstruct(): void
    {
        $todo = new Todo('1', 'Test Todo', false, '2025-01-13 00:00:00');

        $this->assertSame('1', $todo->id);
        $this->assertSame('Test Todo', $todo->title);
        $this->assertFalse($todo->completed);
        $this->assertSame('2025-01-13 00:00:00', $todo->dateCreated);
    }

    public function testSnakeToCamelConversion(): void
    {
        $todo = new Todo('1', 'Test Todo', false, '2025-01-13 00:00:00');

        // date_created (snake_case) → dateCreated (camelCase)
        $this->assertSame('2025-01-13 00:00:00', $todo->dateCreated);
    }
}
