<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Entity;

class Todo
{
    public readonly string $dateCreated;

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly bool $completed,
        string $date_created
    ) {
        $this->dateCreated = $date_created;
    }
}
