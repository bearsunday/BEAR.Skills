<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateTodoTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('todo', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'string', ['limit' => 64])
              ->addColumn('title', 'string', ['limit' => 255])
              ->addColumn('completed', 'boolean', ['default' => false])
              ->addColumn('date_created', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['completed'])
              ->addIndex(['date_created'])
              ->create();
    }
}
