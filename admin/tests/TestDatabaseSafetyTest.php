<?php

declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Support\TestDatabase;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class TestDatabaseSafetyTest extends TestCase
{
    public function test_same_size_seed_code_edit_with_preserved_timestamp_changes_key(): void
    {
        $file = base_path() . '/app/task9_seed_probe_' . bin2hex(random_bytes(6)) . '.php';
        try {
            file_put_contents($file, "<?php return 'a';\n");
            $stamp = filemtime($file);
            $before = TestDatabase::key();
            file_put_contents($file, "<?php return 'b';\n");
            touch($file, $stamp);
            clearstatcache(true, $file);
            self::assertSame($stamp, filemtime($file));
            self::assertSame(18, filesize($file));
            self::assertNotSame($before, TestDatabase::key());
        } finally { @unlink($file); }
    }

    public function test_inherited_key_cannot_hide_alternate_core_directory_or_external_sql_changes(): void
    {
        $root = sys_get_temp_dir() . '/task9-core-' . bin2hex(random_bytes(6));
        mkdir($root . '/migrations', 0700, true);
        $old = getenv('CORE_DATABASE_PATH');
        try {
            $before = TestDatabase::key();
            foreach (glob(\app\common\service\database\MigrationService::coreDirectory() . '/*.sql') as $file) copy($file, $root . '/migrations/' . basename($file));
            putenv('CORE_DATABASE_PATH=' . $root);
            putenv(TestDatabase::KEY_ENV . '=' . $before);
            $alternate = TestDatabase::key();
            self::assertNotSame($before, $alternate, 'resolved authoritative directory is an input');
            $file = $root . '/migrations/006_operation_log_force_delete.sql';
            $stamp = filemtime($file);
            $size = filesize($file);
            $sql = file_get_contents($file);
            file_put_contents($file, str_replace('force-delete', 'force-deletE', $sql));
            touch($file, $stamp);
            clearstatcache(true, $file);
            self::assertSame($size, filesize($file));
            self::assertSame($stamp, filemtime($file));
            self::assertNotSame($alternate, TestDatabase::key(), 'external SQL content is hashed');
        } finally {
            putenv($old === false ? 'CORE_DATABASE_PATH' : 'CORE_DATABASE_PATH=' . $old);
            foreach (glob($root . '/migrations/*') as $file) unlink($file);
            rmdir($root . '/migrations'); rmdir($root);
        }
    }

    public function test_inherited_key_cannot_hide_loaded_database_config_change(): void
    {
        $before = TestDatabase::key();
        $property = new \ReflectionProperty(\Webman\Config::class, 'config');
        $config = $property->getValue();
        $config['database']['connections']['sqlite']['prefix'] = 'different_';
        $property->setValue(null, $config);
        (new \ReflectionProperty(\Webman\Config::class, 'flatCache'))->setValue(null, []);
        putenv(TestDatabase::KEY_ENV . '=' . $before);
        self::assertNotSame($before, TestDatabase::key());
    }

    public function test_failed_attach_preserves_foreign_keys_and_does_not_detach_existing_databases(): void
    {
        $connection = \support\Db::connection();
        $pdo = $connection->getPdo();
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec("ATTACH DATABASE ':memory:' AS existing");
        $error = null;
        try {
            (new \ReflectionMethod(TestDatabase::class, 'restore'))->invoke(null, $connection, sys_get_temp_dir() . '/missing-' . bin2hex(random_bytes(6)) . '/schema.sqlite');
        } catch (\Throwable $caught) { $error = $caught; }
        self::assertInstanceOf(\PDOException::class, $error);
        self::assertSame(1, (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn());
        self::assertSame(['main', 'existing'], array_column($pdo->query('PRAGMA database_list')->fetchAll(PDO::FETCH_ASSOC), 'name'));
    }

    public function test_failed_detach_still_restores_foreign_keys(): void
    {
        $pdo = new class('sqlite::memory:') extends PDO {
            public function exec(string $statement): int|false
            {
                if (str_starts_with($statement, 'DETACH DATABASE')) throw new \PDOException('simulated detach failure');
                return parent::exec($statement);
            }
        };
        $connection = new \Illuminate\Database\SQLiteConnection($pdo, ':memory:');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $error = null;
        try { (new \ReflectionMethod(TestDatabase::class, 'restore'))->invoke(null, $connection, ':memory:'); }
        catch (\Throwable $caught) { $error = $caught; }
        self::assertInstanceOf(\PDOException::class, $error);
        self::assertSame('simulated detach failure', $error->getMessage());
        self::assertSame(1, (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn());
    }
}
