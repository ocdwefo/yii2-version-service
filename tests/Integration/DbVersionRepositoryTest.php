<?php

declare(strict_types=1);

namespace Ocdwefo\VersionService\Tests\Integration;

use Ocdwefo\VersionService\Domain\Repository\VersionRepositoryInterface;
use Ocdwefo\VersionService\Infrastructure\Yii2\DbVersionRepository;
use Ocdwefo\VersionService\Infrastructure\Yii2\Migration\M261117000000CreateVersionTable;
use Ocdwefo\VersionService\Testing\VersionRepositoryContractTestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use yii\db\Connection;

/**
 * The repository contract against a real database.
 *
 * Runs only with VERSION_SERVICE_TEST_DSN (plus _USER and _PASSWORD) pointing to an empty database:
 * the test creates a table with a random name by the migration and drops it at the end.
 */
#[CoversClass(DbVersionRepository::class)]
#[CoversClass(M261117000000CreateVersionTable::class)]
final class DbVersionRepositoryTest extends VersionRepositoryContractTestCase
{
    private static ?Connection $db = null;

    private static string $table = '';

    #[Override]
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dsn = getenv('VERSION_SERVICE_TEST_DSN');
        if (!is_string($dsn) || $dsn === '') {
            return;
        }

        $user = getenv('VERSION_SERVICE_TEST_USER');
        $password = getenv('VERSION_SERVICE_TEST_PASSWORD');

        $db = new Connection([
            'dsn' => $dsn,
            'username' => is_string($user) ? $user : '',
            'password' => is_string($password) ? $password : '',
            'charset' => str_starts_with($dsn, 'mysql:') ? 'utf8mb4' : 'utf8',
            'schemaCache' => null, // no application here, so no cache component to look up
        ]);

        $table = 'version_test_' . bin2hex(random_bytes(4));
        $migration = new M261117000000CreateVersionTable(['db' => $db, 'compact' => true]);
        $migration->tableName = $table;

        ob_start();
        $created = $migration->up();
        $output = (string) ob_get_clean();

        if ($created === false) {
            throw new RuntimeException('The migration failed: ' . $output);
        }

        self::$db = $db;
        self::$table = $table;
    }

    #[Override]
    public static function tearDownAfterClass(): void
    {
        if (self::$db !== null) {
            self::$db->createCommand()->dropTable(self::$table)->execute();
            self::$db->close();
            self::$db = null;
        }

        parent::tearDownAfterClass();
    }

    #[Override]
    protected function setUp(): void
    {
        if (self::$db === null) {
            self::markTestSkipped('Set VERSION_SERVICE_TEST_DSN, VERSION_SERVICE_TEST_USER and VERSION_SERVICE_TEST_PASSWORD to run.');
        }

        parent::setUp();
    }

    #[Override]
    protected function createRepository(): VersionRepositoryInterface
    {
        $db = self::$db ?? throw new RuntimeException('No test database.');
        $db->createCommand()->truncateTable(self::$table)->execute();

        return new DbVersionRepository($db, self::$table);
    }
}
