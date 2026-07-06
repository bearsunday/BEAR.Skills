<?php

declare(strict_types=1);

use BEAR\Dotenv\Dotenv;

$dir = __DIR__;
(new Dotenv())->load($dir);

return [
    'paths' => [
        'migrations' => $dir . '/var/phinx/migrations',
        'seeds' => $dir . '/var/phinx/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name' => $dir . '/var/db/app',
            'suffix' => '.sqlite3',
        ],
    ],
];
