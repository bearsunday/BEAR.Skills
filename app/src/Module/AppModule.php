<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Module;

use BEAR\Dotenv\Dotenv;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\Resource\Module\JsonSchemaModule;
use Ray\AuraSqlModule\AuraSqlModule;
use Ray\MediaQuery\MediaQuerySqlModule;

use function dirname;

class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        (new Dotenv())->load(dirname(__DIR__, 2));

        $this->install(
            new AuraSqlModule(
                (string) getenv('DB_DSN'),
                (string) getenv('DB_USER'),
                (string) getenv('DB_PASS'),
                (string) getenv('DB_SLAVE'),
            ),
        );

        $this->install(
            new MediaQuerySqlModule(
                interfaceDir: $this->appMeta->appDir . '/src/Query',
                sqlDir: $this->appMeta->appDir . '/var/sql',
            ),
        );

        $this->install(
            new JsonSchemaModule(
                $this->appMeta->appDir . '/var/json_schema',
                $this->appMeta->appDir . '/var/json_validate',
            ),
        );

        $this->install(new PackageModule());
    }
}
