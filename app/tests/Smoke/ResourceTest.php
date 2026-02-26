<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Smoke;

use BEAR\Resource\ResourceInterface;
use MyVendor\MyProject\Injector;
use PHPUnit\Framework\TestCase;

class ResourceTest extends TestCase
{
    private ResourceInterface $resource;

    protected function setUp(): void
    {
        $injector = Injector::getInstance('app');
        $this->resource = $injector->getInstance(ResourceInterface::class);
    }

    /**
     * @param array<mixed> $query
     *
     * @dataProvider resourceProvider
     */
    public function testResource(string $method, string $uri, array $query, int $expectedCode): void
    {
        $ro = $this->resource->{$method}($uri, $query);

        $this->assertSame($expectedCode, $ro->code);
    }

    /** @return iterable<string, array{string, string, array<mixed>, int}> */
    public static function resourceProvider(): iterable
    {
        yield 'GET app://self/todo' => ['get', 'app://self/todo', [], 200];
        yield 'POST app://self/todo' => ['post', 'app://self/todo', ['title' => 'test'], 201];
        yield 'GET page://self/index' => ['get', 'page://self/index', [], 200];
    }
}
