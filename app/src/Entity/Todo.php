<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Entity;

use DateTimeImmutable;
use DateTimeInterface;

class Todo
{
    public readonly bool $completed;

    public readonly string $dateCreated;

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        bool|int $completed,
        string $date_created
    ) {
        $this->completed = (bool) $completed;
        // Normalise the DB datetime string (Y-m-d H:i:s) to ISO-8601 for JSON,
        // matching the response JSON Schema "format": "date-time".
        $this->dateCreated = (new DateTimeImmutable($date_created))->format(DateTimeInterface::ATOM);
    }
}
