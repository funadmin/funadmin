<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\model\AttachGroup;
use app\common\model\Attach;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AttachmentIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        Context::destroy();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_migration_creates_attachment_tables_default_group_and_logical_model_tables(): void
    {
        self::assertTrue(Db::schema()->hasTable('attach'));
        self::assertTrue(Db::schema()->hasTable('attach_group'));
        self::assertSame('attach', (new Attach())->getTable());
        self::assertSame('attach_group', (new AttachGroup())->getTable());
        self::assertSame(1, Db::table('attach_group')->where('id', 1)->value('id'));
    }

    public function test_attachment_rows_support_soft_delete_and_keep_storage_metadata(): void
    {
        $groupId = (int) Db::table('attach_group')->insertGetId(['name' => '验收分组', 'pid' => 0, 'status' => 1, 'sort_order' => 10]);
        $item = Attach::forceCreate([
            'group_id' => $groupId,
            'name' => 'sample.txt',
            'original_name' => 'sample.txt',
            'path' => '/storage/sample.txt',
            'url' => '/storage/sample.txt',
            'storage_key' => 'sample.txt',
            'driver' => 'local',
            'ext' => 'txt',
            'mime' => 'text/plain',
            'size' => 1,
            'status' => 1,
        ]);
        self::assertSame('sample.txt', $item->storage_key);
        $item->delete();
        self::assertNull(Db::table('attach')->where('id', $item->id)->whereNull('deleted_at')->value('deleted_at'));
        self::assertNotNull(Db::table('attach')->where('id', $item->id)->value('deleted_at'));
    }
}
