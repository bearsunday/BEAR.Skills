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

## 4.2 Generate Environment Variable Files (Koriym.EnvJson)

**env.schema.json** - Schema definition:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
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

## 4.5 Update bin/app.php (EnvJson Loading)

Load EnvJson at the application entry point:

**bin/app.php:**
```php
<?php
declare(strict_types=1);

use Koriym\EnvJson\EnvJson;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables
(new EnvJson())->load(dirname(__DIR__));

exit((require dirname(__DIR__) . '/bootstrap.php')($argv));
```

**public/index.php:**
```php
<?php
declare(strict_types=1);

use Koriym\EnvJson\EnvJson;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load environment variables
(new EnvJson())->load(dirname(__DIR__));

exit((require dirname(__DIR__) . '/bootstrap.php')());
```

## 4.6 Configure tests/bootstrap.php

```php
<?php
declare(strict_types=1);

use Koriym\EnvJson\EnvJson;

require dirname(__DIR__) . '/vendor/autoload.php';

// Load test environment variables (prefer env.test.json if it exists)
$envFile = file_exists(dirname(__DIR__) . '/env.test.json') ? 'env.test.json' : 'env.json';
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

## 4.7 Place the ALPS Profile

```bash
mkdir -p docs
cp {alps_profile_path} docs/alps.json

# Generate HTML documentation
asd docs/alps.json -o docs/alps.html
```
