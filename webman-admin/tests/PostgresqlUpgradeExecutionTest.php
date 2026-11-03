<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\service\UpgradeService;
use app\admin\system\service\UpgradeManifestVerifier;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

final class PostgresqlUpgradeExecutionTest extends TestCase
{
    public function test_signed_offline_upgrade_backs_up_migrates_and_recovers_on_failure(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        $root = sys_get_temp_dir() . '/pg-upgrade-' . bin2hex(random_bytes(5));
        mkdir($root . '/database/migrations/pgsql', 0700, true);
        mkdir($root . '/app', 0700);
        $schema = 'upgrade_' . bin2hex(random_bytes(5));
        $connection = Db::connection();
        $connection->statement('CREATE SCHEMA "' . $schema . '"');
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $previous = $manager->getDefaultConnection();
        $config = \Illuminate\Container\Container::getInstance()['config'];
        $connections = $config['database.connections'];
        $connections['pg_upgrade'] = array_replace($connection->getConfig(), ['name' => 'pg_upgrade', 'search_path' => $schema]);
        $config['database.connections'] = $connections;
        $manager->setDefaultConnection('pg_upgrade');
        $previousPath = getenv('CORE_DATABASE_PATH');
        putenv('CORE_DATABASE_PATH=' . $root . '/database');
        try {
            (new MigrationService())->run();
            Db::schema()->create('system_migration', static function ($table): void { $table->id(); $table->string('scope'); $table->string('version'); $table->string('checksum', 64); $table->bigInteger('executed_at'); });
            $sql = 'CREATE TABLE fun_upgrade_fixture(id bigint PRIMARY KEY, title text); INSERT INTO fun_upgrade_fixture VALUES (1,\'升级完成\');';
            file_put_contents($root . '/database/migrations/pgsql/001_upgrade.sql', $sql);
            file_put_contents($root . '/app/fixture.php', 'old');
            $keypair = sodium_crypto_sign_keypair();
            $verifier = new UpgradeManifestVerifier(base64_encode(sodium_crypto_sign_publickey($keypair)));
            $build = static function (string $sql, string $source) use ($root, $keypair, $verifier): string {
                $files = ['database/migrations/pgsql/001_upgrade.sql' => hash('sha256', $sql), 'app/fixture.php' => hash('sha256', $source)];
                $manifest = ['version' => '1.1.0', 'databaseDriver' => 'pgsql', 'coreMigrations' => ['001_upgrade.sql' => hash('sha256', $sql)], 'files' => $files];
                $manifest['signature'] = ['algorithm' => 'ed25519', 'value' => base64_encode(sodium_crypto_sign_detached($verifier->canonicalPayload($manifest), sodium_crypto_sign_secretkey($keypair)))];
                $path = $root . '/upgrade-' . bin2hex(random_bytes(4)) . '.zip';
                $zip = new \ZipArchive(); $zip->open($path, \ZipArchive::CREATE);
                $zip->addFromString('upgrade.json', json_encode($manifest)); $zip->addFromString('database/migrations/pgsql/001_upgrade.sql', $sql); $zip->addFromString('app/fixture.php', $source); $zip->close();
                return $path;
            };
            self::assertSame(Db::connection()->getName(), (new \app\common\model\UpgradeTask())->getConnection()->getName(), '升级模型必须使用当前连接');
            self::assertTrue(Db::schema()->hasColumn('upgrade_task', 'manifest_id'));
            $service = new UpgradeService($root, $root . '/runtime/upgrade', manifestVerifier: $verifier);
            $token = 'pg-upgrade-' . bin2hex(random_bytes(8));
            $archive = $build($sql, 'new');
            $task = $service->upload($archive, $token);
            self::assertSame('success', $task['status']);
            self::assertSame('new', file_get_contents($root . '/app/fixture.php'));
            self::assertSame('升级完成', Db::table('upgrade_fixture')->value('title'));
            self::assertSame($task['id'], $service->upload($archive, $token)['id']);
            // 新升级在事务迁移失败后恢复此前数据库和文件。
            $failed = "CREATE TABLE fun_should_rollback(id integer); SELECT * FROM pg_nonexistent_fixture;";
            file_put_contents($root . '/database/migrations/pgsql/002_failure.sql', $failed);
            $files = ['database/migrations/pgsql/001_upgrade.sql' => hash('sha256', $sql), 'database/migrations/pgsql/002_failure.sql' => hash('sha256', $failed), 'app/fixture.php' => hash('sha256', 'broken')];
            $manifest = ['version' => '1.2.0', 'databaseDriver' => 'pgsql', 'coreMigrations' => ['001_upgrade.sql' => hash('sha256', $sql), '002_failure.sql' => hash('sha256', $failed)], 'files' => $files];
            $manifest['signature'] = ['algorithm' => 'ed25519', 'value' => base64_encode(sodium_crypto_sign_detached($verifier->canonicalPayload($manifest), sodium_crypto_sign_secretkey($keypair)))];
            $archive = $root . '/failure.zip'; $zip = new \ZipArchive(); $zip->open($archive, \ZipArchive::CREATE);
            $zip->addFromString('upgrade.json', json_encode($manifest)); $zip->addFromString('database/migrations/pgsql/001_upgrade.sql', $sql); $zip->addFromString('database/migrations/pgsql/002_failure.sql', $failed); $zip->addFromString('app/fixture.php', 'broken'); $zip->close();
            try { $service->upload($archive, 'pg-failure-' . bin2hex(random_bytes(8))); self::fail('失败迁移必须报错'); }
            catch (\RuntimeException) {
                self::assertSame('restored_after_failure', Db::table('upgrade_task')->orderByDesc('id')->value('stage'), (string) Db::table('upgrade_task')->orderByDesc('id')->value('error_message'));
                self::assertSame('new', file_get_contents($root . '/app/fixture.php'));
                self::assertFalse(Db::schema()->hasTable('should_rollback'));
                self::assertSame('升级完成', Db::table('upgrade_fixture')->value('title'));
            }
        } finally {
            $manager->setDefaultConnection($previous); $manager->purge('pg_upgrade');
            $connection->statement('DROP SCHEMA "' . $schema . '" CASCADE');
            putenv($previousPath === false ? 'CORE_DATABASE_PATH' : 'CORE_DATABASE_PATH=' . $previousPath);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($root);
        }
    }
}
