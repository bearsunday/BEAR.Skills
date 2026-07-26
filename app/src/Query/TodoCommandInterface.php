<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Query;

use DateTimeInterface;
use Ray\MediaQuery\Annotation\DbQuery;

interface TodoCommandInterface
{
    #[DbQuery('todo_add')]
    public function add(string $id, string $title, DateTimeInterface|null $dateCreated = null): void;
}
