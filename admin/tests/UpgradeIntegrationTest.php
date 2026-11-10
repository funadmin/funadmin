<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\service\UpgradeService;
use app\common\model\UpgradeManifest;
use app\common\model\UpgradeTask;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UpgradeIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        Context::destroy();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_upgrade_migration_creates_task_and_manifest_tables_and_status_is_read_only(): void
    {
        self::assertTrue(Db::schema()->hasTable('upgrade_task'));
        self::assertTrue(Db::schema()->hasTable('upgrade_manifest'));
        self::assertSame('upgrade_task', (new UpgradeTask())->getTable());
        self::assertSame('upgrade_manifest', (new UpgradeManifest())->getTable());
        $status = (new UpgradeService())->status();
        self::assertArrayHasKey('currentVersion', $status);
        self::assertIsArray($status['tasks']);
    }

    public function test_upgrade_operation_rejects_unknown_payload_before_any_task_is_created(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('仅接受 manifestId 与 operationToken');
        (new UpgradeService())->execute(['manifestId' => 'x', 'operationToken' => 'y', 'unexpected' => true]);
    }
}
