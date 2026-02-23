<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Smoke;

use MyVendor\MyProject\Injector;
use MyVendor\MyProject\Query\TodoCommandInterface;
use MyVendor\MyProject\Query\TodoQueryInterface;
use PHPUnit\Framework\TestCase;

use function is_array;
use function is_object;

class QueryTest extends TestCase
{
    /**
     * @param array<mixed> $args
     *
     * @dataProvider queryProvider
     */
    public function testQueryMethod(string $interface, string $method, array $args, string $expectedType): void
    {
        $injector = Injector::getInstance('app');
        $instance = $injector->getInstance($interface);
        $result = $instance->{$method}(...$args);

        match ($expectedType) {
            'void' => $this->addToAssertionCount(1),
            'array' => $this->assertIsArray($result),
            default => $this->assertTrue($result === null || is_object($result) || is_array($result)),
        };
    }

    /** @return iterable<string, array{string, string, array<mixed>, string}> */
    public static function queryProvider(): iterable
    {
        yield 'TodoQueryInterface::list' => [
            TodoQueryInterface::class,
            'list',
            [],
            'array',
        ];

        yield 'TodoCommandInterface::add' => [
            TodoCommandInterface::class,
            'add',
            ['test-smoke-id', 'test', null],
            'void',
        ];
    }
}
