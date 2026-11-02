<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\system\command\UpgradeRecover;
use app\admin\system\service\UpgradeService;
use app\common\model\UpgradeTask;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class UpgradeRecoveryExecutionTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        UpgradeRecoveryFixture::create();
        $this->root = sys_get_temp_dir() . '/funadmin-upgrade-recover-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/runtime/upgrade', 0700, true);
    }

    protected function tearDown(): void
    {
        Db::schema()->dropIfExists('upgrade_task');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    private function command(): CommandTester
    {
        self::assertSame(UpgradeRecover::class, (new \ReflectionMethod(UpgradeRecover::class, '__construct'))->getDeclaringClass()->getName());
        return new CommandTester(new UpgradeRecover(new UpgradeService($this->root, $this->root . '/runtime/upgrade')));
    }

    public function test_pre_deploy_stale_task_releases_slot_and_repeat_is_noop(): void
    {
        $id = UpgradeRecoveryFixture::seed();
        $command = $this->command();
        self::assertSame(0, $command->execute([]), $command->getDisplay());
        $result = json_decode($command->getDisplay(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($result['recovered']);
        $record = Db::table('upgrade_task')->where('id', $id)->first();
        self::assertSame('failed', $record->status);
        self::assertSame('stale_pre_deploy', $record->stage);
        self::assertNull($record->active_slot);
        self::assertNull($record->lease_expires_at);
        self::assertSame(0, $command->execute([]));
        self::assertFalse(json_decode($command->getDisplay(), true)['recovered']);
    }

    public function test_live_lease_and_system_lock_cannot_be_taken_over(): void
    {
        $id = UpgradeRecoveryFixture::seed(expired: false);
        $before = (array) Db::table('upgrade_task')->where('id', $id)->first();
        $command = $this->command();
        self::assertSame(0, $command->execute([]), $command->getDisplay());
        self::assertFalse(json_decode($command->getDisplay(), true)['recovered']);
        self::assertSame($before, (array) Db::table('upgrade_task')->where('id', $id)->first());
        $lock = fopen($this->root . '/runtime/upgrade/system-upgrade.lock', 'c+');
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        try { self::assertSame(1, $command->execute([])); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
        self::assertSame($before, (array) Db::table('upgrade_task')->where('id', $id)->first());
    }

    public function test_missing_post_deploy_backup_requires_manual_recovery_without_releasing_slot(): void
    {
        $id = UpgradeRecoveryFixture::seed('deploy');
        $command = $this->command();
        self::assertSame(1, $command->execute([]));
        self::assertStringContainsString('备份不完整', $command->getDisplay());
        $record = Db::table('upgrade_task')->where('id', $id)->first();
        self::assertSame('requires_manual_recovery', $record->status);
        self::assertSame('restore_failed', $record->stage);
        self::assertSame(1, (int) $record->active_slot);
    }

    public function test_backup_path_with_parent_segments_is_rejected_before_reading_external_artifacts(): void
    {
        mkdir($this->root . '/runtime/upgrade/backup', 0700, true);
        mkdir($this->root . '/outside', 0700);
        file_put_contents($this->root . '/outside/database.snapshot', 'external');
        $id = UpgradeRecoveryFixture::seed('deploy', attributes: [
            'backup_path' => $this->root . '/runtime/upgrade/backup/../../../outside',
            'db_backup_path' => $this->root . '/runtime/upgrade/backup/../../../outside/database.snapshot',
            'backup_hash' => str_repeat('a', 64), 'db_backup_hash' => str_repeat('b', 64),
        ]);
        $command = $this->command();
        self::assertSame(1, $command->execute([]));
        self::assertStringContainsString('备份不完整', $command->getDisplay());
        self::assertSame('external', file_get_contents($this->root . '/outside/database.snapshot'));
    }

    public function test_backup_metadata_rejects_parent_path_before_hash_or_restore(): void
    {
        $backup = $this->root . '/runtime/upgrade/backup/fixture';
        mkdir($backup, 0700, true);
        file_put_contents($backup . '/database.snapshot', 'snapshot');
        UpgradeRecoveryFixture::seed('deploy', attributes: [
            'backup_path' => $backup, 'db_backup_path' => $backup . '/database.snapshot',
            'backup_hash' => str_repeat('a', 64), 'db_backup_hash' => str_repeat('b', 64),
            'metadata' => json_encode(['files' => ['../outside.php'], 'createdFiles' => []], JSON_THROW_ON_ERROR),
        ]);
        $command = $this->command();
        self::assertSame(1, $command->execute([]));
        self::assertStringContainsString('备份文件路径无效', $command->getDisplay());
    }

    public function test_old_owner_cannot_renew_after_lease_was_reclaimed(): void
    {
        $id = UpgradeRecoveryFixture::seed(expired: false);
        $task = UpgradeTask::query()->findOrFail($id);
        Db::table('upgrade_task')->where('id', $id)->update(['lease_expires_at' => date('Y-m-d H:i:s', time() + 7200)]);
        $service = new UpgradeService($this->root, $this->root . '/runtime/upgrade');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('租约已丢失');
        (new \ReflectionMethod($service, 'renewLease'))->invoke($service, $task, ['stage' => 'restore_files']);
    }

    public function test_stale_owner_cannot_finalize_task_after_database_lease_changed(): void
    {
        $id = UpgradeRecoveryFixture::seed(expired: false);
        $task = UpgradeTask::query()->findOrFail($id);
        Db::table('upgrade_task')->where('id', $id)->update(['lease_expires_at' => date('Y-m-d H:i:s', time() + 7200)]);
        $service = new UpgradeService($this->root, $this->root . '/runtime/upgrade');
        $rejected = false;
        try { (new \ReflectionMethod($service, 'persistTaskState'))->invoke($service, $task, ['status' => 'restored', 'active_slot' => null]); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('租约已丢失', $error->getMessage()); }
        self::assertTrue($rejected);
        self::assertSame('running', Db::table('upgrade_task')->where('id', $id)->value('status'));
        self::assertSame(1, (int) Db::table('upgrade_task')->where('id', $id)->value('active_slot'));
    }

    public function test_task_metadata_cast_and_internal_mass_assignment_work(): void
    {
        $task = UpgradeTask::query()->create(['idempotency_token' => bin2hex(random_bytes(16)), 'status' => 'failed', 'stage' => 'verify', 'metadata' => ['files' => ['app/example.php']]]);
        self::assertSame(['files' => ['app/example.php']], $task->fresh()->metadata);
    }
}
