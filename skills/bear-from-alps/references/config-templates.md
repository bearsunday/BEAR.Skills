# Configuration File Templates

## 4.1 Update composer.json

```json
{
  "name": "{vendor}/{package}",
  "autoload": {
    "psr-4": {
      "{Vendor}\\{Package}\\": "src/"
    }
  }
}
```

## 4.2 Environment Variable Files (Koriym.EnvJson)

bear/skeleton already ships `env.schema.json` and `env.dist.json` (and koriym/env-json). Do not regenerate them — add the DB_* properties to the existing files.

**env.schema.json** - add to `required` and `properties`:

```json
{
  "required": ["TZ", "DB_DSN"],
  "properties": {
    "TZ": {
      "description": "Timezone",
      "type": "string",
      "default": "Asia/Tokyo"
    },
    "DB_DSN": {
      "description": "Database connection DSN",
      "type": "string",
      "examples": ["sqlite:var/db/app.sqlite3", "mysql:host=localhost;dbname=mydb"]
    },
    "DB_USER": {
      "description": "Database user",
      "type": "string"
    },
    "DB_PASS": {
      "description": "Database password",
      "type": "string"
    },
    "DB_SLAVE": {
      "description": "Slave database DSN for read replica",
      "type": "string"
    }
  }
}
```

**env.json** - Environment variables:

```json
{
  "$schema": "./env.schema.json",
  "TZ": "Asia/Tokyo",
  "DB_DSN": "sqlite:var/db/app.sqlite3",
  "DB_USER": "",
  "DB_PASS": "",
  "DB_SLAVE": ""
}
```

**env.dist.json** - Distribution template (exclude env.json in .gitignore and commit this file instead):

```json
{
  "$schema": "./env.schema.json",
  "TZ": "Asia/Tokyo",
  "DB_DSN": "sqlite:var/db/app.sqlite3",
  "DB_USER": "",
  "DB_PASS": "",
  "DB_SLAVE": ""
}
```

## 4.3 Generate phinx.php

```php
<?php
use Koriym\EnvJson\EnvJson;

$dir = __DIR__;
(new EnvJson())->load($dir);

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
        'testing' => [
            'adapter' => 'sqlite',
            'name' => $dir . '/var/db/test',
            'suffix' => '.sqlite3',
        ],
    ],
];
```

## 4.4 Update .gitignore

```bash
# Add the following to .gitignore
echo "env.json" >> .gitignore
echo "var/db/*.sqlite3" >> .gitignore
```

## 4.5 Configure tests/bootstrap.php

```php
<?php
declare(strict_types=1);

use Koriym\EnvJson\EnvJson;

require dirname(__DIR__) . '/vendor/autoload.php';

// EnvJson::load() keeps already-valid process env vars and reads the JSON file
// only as a fallback, so an inherited DB_DSN (developer shell, CI) would silently
// send tests to a non-test database. Force the test values into the process
// environment first, then load for validation.
$envFile = file_exists(dirname(__DIR__) . '/env.test.json') ? 'env.test.json' : 'env.json';
$env = json_decode(file_get_contents(dirname(__DIR__) . '/' . $envFile), true, 512, JSON_THROW_ON_ERROR);
unset($env['$schema']);
foreach ($env as $name => $value) {
    putenv("{$name}={$value}");
}
(new EnvJson())->load(dirname(__DIR__), $envFile);
```

**env.test.json** - Test environment variables:
```json
{
  "$schema": "./env.schema.json",
  "TZ": "Asia/Tokyo",
  "DB_DSN": "sqlite:var/db/test.sqlite3",
  "DB_USER": "",
  "DB_PASS": "",
  "DB_SLAVE": ""
}
```

Wire it into PHPUnit: set `bootstrap="tests/bootstrap.php"` in phpunit.xml.dist (the skeleton default is `vendor/autoload.php`).

## 4.6 Place the ALPS Profile

```bash
mkdir -p docs
cp {alps_profile_path} docs/alps.json

# Generate HTML documentation
asd docs/alps.json -o docs/alps.html
```
