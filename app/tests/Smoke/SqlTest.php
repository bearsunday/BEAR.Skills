<?php

declare(strict_types=1);

namespace MyVendor\MyProject\Smoke;

use Aura\Sql\ExtendedPdoInterface;
use MyVendor\MyProject\Injector;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;

use function basename;
use function file_get_contents;
use function glob;
use function preg_replace;
use function sprintf;
use function str_contains;
use function stripos;
use function trim;

class SqlTest extends TestCase
{
    private static PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        $injector = Injector::getInstance('app');
        self::$pdo = $injector->getInstance(ExtendedPdoInterface::class)->getPdo();
    }

    /** @dataProvider sqlFileProvider */
    public function testSqlSyntax(string $sqlFile): void
    {
        $sql = $this->prepareSql($sqlFile);
        $stmt = self::$pdo->prepare($sql);

        $this->assertInstanceOf(PDOStatement::class, $stmt);
    }

    /** @dataProvider selectSqlProvider */
    public function testSelectExplain(string $sqlFile): void
    {
        $sql = $this->prepareSql($sqlFile);
        $sql = (string) preg_replace('/\bLIMIT\s+NULL\b/i', 'LIMIT 1', $sql);
        $sql = (string) preg_replace('/\bOFFSET\s+NULL\b/i', 'OFFSET 0', $sql);

        $driver = self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $this->assertSqliteExplain($sql, $sqlFile);

            return;
        }

        $this->assertMysqlExplain($sql, $sqlFile);
    }

    private function assertSqliteExplain(string $sql, string $sqlFile): void
    {
        $stmt = self::$pdo->query('EXPLAIN QUERY PLAN ' . $sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $detail = $row['detail'] ?? '';
            // 'SCAN ... USING COVERING INDEX' is an index scan, not a full table scan
            if (! str_contains($detail, 'SCAN') || str_contains($detail, 'USING')) {
                continue;
            }

            $this->fail(sprintf(
                'Full table scan detected in %s: %s',
                basename($sqlFile),
                $detail,
            ));
        }
    }

    private function assertMysqlExplain(string $sql, string $sqlFile): void
    {
        $stmt = self::$pdo->query('EXPLAIN ' . $sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $type = $row['type'] ?? '';
            $this->assertNotSame('ALL', $type, sprintf(
                'Full table scan detected in %s (table: %s)',
                basename($sqlFile),
                $row['table'] ?? 'unknown',
            ));
        }
    }

    /** @return iterable<string, array{string}> */
    public static function sqlFileProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../../var/sql/*.sql') ?: [] as $file) {
            yield basename($file) => [$file];
        }
    }

    /** @return iterable<string, array{string}> */
    public static function selectSqlProvider(): iterable
    {
        foreach (glob(__DIR__ . '/../../var/sql/*.sql') ?: [] as $file) {
            $sql = (string) file_get_contents($file);
            if (stripos($sql, 'SELECT') === false || stripos($sql, 'INSERT') !== false) {
                continue;
            }

            yield basename($file) => [$file];
        }
    }

    private function prepareSql(string $sqlFile): string
    {
        $sql = trim((string) file_get_contents($sqlFile));
        // Strip leading comment
        $sql = (string) preg_replace('/\A\/\*.*?\*\/\s*/s', '', $sql);
        // Replace named parameters with NULL for syntax check
        $sql = (string) preg_replace('/:[a-zA-Z_]+/', 'NULL', $sql);

        return $sql;
    }
}
