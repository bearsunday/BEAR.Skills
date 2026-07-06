<?php

declare(strict_types=1);

chdir(dirname(__DIR__));
passthru('rm -rf ./var/tmp/*');

// Provision the sqlite database: recreate for reproducibility, then migrate.
$dbDir = __DIR__ . '/../var/db';
if (! is_dir($dbDir)) {
    mkdir($dbDir, 0777, true);
}

foreach (glob($dbDir . '/*.sqlite3') ?: [] as $file) {
    unlink($file);
}

passthru(__DIR__ . '/../vendor/bin/phinx migrate -e development', $code);
exit($code === 0 ? 0 : 1);
