<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Query;

use MyVendor\MyProject\Entity\Todo;
use Ray\MediaQuery\Annotation\DbQuery;

interface TodoQueryInterface
{
    /** @return array<Todo> */
    #[DbQuery('todo_list')]
    public function list(): array;
}
