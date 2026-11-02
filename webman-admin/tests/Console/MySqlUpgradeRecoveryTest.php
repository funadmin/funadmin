<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\system\command\UpgradeRecover;
use app\admin\system\service\UpgradeDatabaseBackup;
use app\admin\system\service\UpgradeService;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class MySqlUpgradeRecoveryTest extends TestCase
{
    private bool $ready = false;
    private string $root;

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') !== '1') $this->markTestSkipped('需要独立 Console MySQL 测试库');
        self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
        $this->root = sys_get_temp_dir() . '/funadmin-upgrade-mysql-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/runtime/upgrade/backup/fixture/app', 0700, true);
        mkdir($this->root . '/app', 0700, true);
        UpgradeRecoveryFixture::create();
        Db::schema()->create('upgrade_payload', static function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id(); $table->string('value');
        });
        Db::table('upgrade_payload')->insert(['value' => 'snapshot-data']);
        $this->ready = true;
    }

    protected function tearDown(): void
    {
        if (!$this->ready) return;
        Db::schema()->dropIfExists('upgrade_payload');
        Db::schema()->dropIfExists('upgrade_task');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    private function preparedTask(): array
    {
        $backup = $this->root . '/runtime/upgrade/backup/fixture';
        file_put_contents($backup . '/app/example.php', 'snapshot-file');
        file_put_contents($this->root . '/app/example.php', 'deployed-file');
        file_put_contents($this->root . '/app/created.php', 'new-file');
        $hashes = ['app/created.php' => null, 'app/example.php' => hash('sha256', 'snapshot-file')];
        $id = UpgradeRecoveryFixture::seed('deploy', attributes: [
            'backup_path' => $backup, 'backup_hash' => hash('sha256', json_encode($hashes, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            'db_backup_path' => $backup . '/database.snapshot', 'db_backup_hash' => str_repeat('a', 64),
            'metadata' => json_encode(['files' => ['app/example.php', 'app/created.php'], 'createdFiles' => ['app/created.php']], JSON_THROW_ON_ERROR),
        ]);
        $service = new UpgradeDatabaseBackup();
        $hash = $service->backup($backup . '/database.snapshot');
        self::assertSame(0600, fileperms($backup . '/database.snapshot') & 0777);
        Db::table('upgrade_task')->where('id', $id)->update(['db_backup_hash' => $hash]);
        Db::table('upgrade_payload')->update(['value' => 'deployed-data']);
        return [$id, $backup];
    }

    public function test_concurrent_stale_claim_in_mysql_has_one_winner(): void
    {
        UpgradeRecoveryFixture::seed();
        $code = 'require "tests/Console/mysql-bootstrap.php"; $service = new \\app\\admin\\system\\service\\UpgradeService(); $task = (new ReflectionMethod($service, "claimStaleTask"))->invoke($service); echo $task ? "won" : "lost";';
        $children = [];
        for ($i = 0; $i < 2; $i++) {
            $process = proc_open([PHP_BINARY, '-r', $code], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            self::assertIsResource($process);
            fclose($pipes[0]); $children[] = [$process, $pipes];
        }
        $results = [];
        foreach ($children as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $error);
        }
        sort($results);
        self::assertSame(['lost', 'won'], $results);
    }

    public function test_post_deploy_stale_command_restores_real_database_and_files(): void
    {
        [$id, $backup] = $this->preparedTask();
        $command = new CommandTester(new UpgradeRecover(new UpgradeService($this->root, $this->root . '/runtime/upgrade')));
        self::assertSame(0, $command->execute([]), $command->getDisplay());
        $result = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($result['recovered']);
        self::assertSame('snapshot-data', Db::table('upgrade_payload')->value('value'));
        self::assertSame('snapshot-file', file_get_contents($this->root . '/app/example.php'));
        self::assertFileDoesNotExist($this->root . '/app/created.php');
        $task = Db::table('upgrade_task')->where('id', $id)->first();
        self::assertSame('restored', $task->status);
        self::assertSame('restore_complete', $task->stage);
        self::assertNull($task->active_slot);
        self::assertMatchesRegularExpression('/^funadmin_backup_[a-f0-9]{16}$/D', $task->db_backup_schema);
        self::assertSame(0, $command->execute([]));
        self::assertFalse(json_decode($command->getDisplay(), true)['recovered']);
    }

    public function test_snapshot_round_trip_preserves_relations_views_binary_and_generated_columns(): void
    {
        $pdo = Db::connection()->getPdo();
        $pdo->exec('CREATE TABLE console_upgrade_child (id BIGINT PRIMARY KEY, parent_id BIGINT UNSIGNED, payload VARBINARY(32), amount INT, doubled INT GENERATED ALWAYS AS (amount * 2) STORED, FOREIGN KEY (parent_id) REFERENCES console_upgrade_payload(id)) ENGINE=InnoDB');
        $pdo->exec('CREATE VIEW console_upgrade_view AS SELECT id, value FROM console_upgrade_payload');
        $pdo->prepare('INSERT INTO console_upgrade_child (id,parent_id,payload,amount) VALUES (1,1,?,7)')->execute(["\x00\xff\x01"]);
        $backup = $this->root . '/runtime/upgrade/backup/fixture/objects.snapshot';
        $service = new UpgradeDatabaseBackup();
        try {
            $hash = $service->backup($backup);
            $pdo->exec('UPDATE console_upgrade_child SET amount=9');
            $report = $service->restore($backup, $hash);
            self::assertMatchesRegularExpression('/^funadmin_backup_[a-f0-9]{16}$/D', $report['backup_schema']);
            $row = $pdo->query('SELECT payload, amount, doubled FROM console_upgrade_child')->fetch(\PDO::FETCH_ASSOC);
            self::assertSame("\x00\xff\x01", $row['payload']);
            self::assertSame(7, (int) $row['amount']);
            self::assertSame(14, (int) $row['doubled']);
            self::assertSame('snapshot-data', $pdo->query('SELECT value FROM console_upgrade_view')->fetchColumn());
            self::assertSame(Db::connection()->getDatabaseName(), $pdo->query('SELECT DATABASE()')->fetchColumn());
        } finally {
            $pdo->exec('DROP VIEW IF EXISTS console_upgrade_view');
            $pdo->exec('DROP TABLE IF EXISTS console_upgrade_child');
        }
    }

    public function test_shadow_table_definition_hash_is_verified_not_only_row_data(): void
    {
        $path = $this->root . '/runtime/upgrade/backup/fixture/schema.snapshot';
        $service = new UpgradeDatabaseBackup();
        $service->backup($path);
        $lines = file($path);
        foreach ($lines as &$line) {
            $record = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            if (($record['type'] ?? '') === 'schema' && ($record['name'] ?? '') === 'console_upgrade_payload') {
                $record['sql'] = str_replace('varchar(255)', 'varchar(512)', $record['sql']);
                $line = json_encode($record, JSON_THROW_ON_ERROR) . "\n";
            }
        }
        unset($line);
        file_put_contents($path, implode('', $lines));
        $rejected = false;
        try { $service->restore($path, hash_file('sha256', $path)); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('hash', $error->getMessage()); }
        self::assertTrue($rejected);
        self::assertSame('varchar(255)', Db::selectOne("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='console_upgrade_payload' AND COLUMN_NAME='value'")->COLUMN_TYPE);
    }

    public function test_invalid_shadow_data_manifest_preserves_current_database(): void
    {
        $path = $this->root . '/runtime/upgrade/backup/fixture/invalid.snapshot';
        $service = new UpgradeDatabaseBackup();
        $service->backup($path);
        $lines = file($path);
        $manifest = json_decode(array_pop($lines), true, 512, JSON_THROW_ON_ERROR);
        $manifest['table_stats']['console_upgrade_payload']['rows'] = 999;
        $lines[] = json_encode($manifest, JSON_THROW_ON_ERROR) . "\n";
        file_put_contents($path, implode('', $lines));
        Db::table('upgrade_payload')->update(['value' => 'current-data']);
        $rejected = false;
        try { $service->restore($path, hash_file('sha256', $path)); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('shadow', $error->getMessage()); }
        self::assertTrue($rejected);
        self::assertSame('current-data', Db::table('upgrade_payload')->value('value'));
    }

    public function test_replaying_restore_token_does_not_restore_database_again(): void
    {
        [$id, $backup] = $this->preparedTask();
        $service = new UpgradeService($this->root, $this->root . '/runtime/upgrade');
        $token = 'restore-' . bin2hex(random_bytes(12));
        self::assertSame('restored', $service->restore($id, $token)['status']);
        Db::table('upgrade_payload')->update(['value' => 'after-restore']);
        file_put_contents($this->root . '/app/example.php', 'after-restore-file');
        self::assertSame('restored', $service->restore($id, $token)['status']);
        self::assertSame('after-restore', Db::table('upgrade_payload')->value('value'));
        self::assertSame('after-restore-file', file_get_contents($this->root . '/app/example.php'));
    }

    public function test_invalid_file_backup_hash_rejects_before_database_switch(): void
    {
        [$id, $backup] = $this->preparedTask();
        file_put_contents($backup . '/app/example.php', 'tampered-file');
        $command = new CommandTester(new UpgradeRecover(new UpgradeService($this->root, $this->root . '/runtime/upgrade')));
        self::assertSame(1, $command->execute([]));
        self::assertStringContainsString('文件备份 SHA-256', $command->getDisplay());
        self::assertSame('deployed-data', Db::table('upgrade_payload')->value('value'));
        self::assertSame('deployed-file', file_get_contents($this->root . '/app/example.php'));
        self::assertSame('requires_manual_recovery', Db::table('upgrade_task')->where('id', $id)->value('status'));
    }

    public function test_invalid_snapshot_hash_rejects_before_database_or_files_are_modified(): void
    {
        [$id, $backup] = $this->preparedTask();
        file_put_contents($backup . '/database.snapshot', "tampered\n", FILE_APPEND);
        $command = new CommandTester(new UpgradeRecover(new UpgradeService($this->root, $this->root . '/runtime/upgrade')));
        self::assertSame(1, $command->execute([]));
        self::assertStringContainsString('SHA-256', $command->getDisplay());
        self::assertSame('deployed-data', Db::table('upgrade_payload')->value('value'));
        self::assertSame('deployed-file', file_get_contents($this->root . '/app/example.php'));
        self::assertSame('requires_manual_recovery', Db::table('upgrade_task')->where('id', $id)->value('status'));
        self::assertSame(1, (int) Db::table('upgrade_task')->where('id', $id)->value('active_slot'));
    }
}
