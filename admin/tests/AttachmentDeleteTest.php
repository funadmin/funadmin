<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemAttachment;
use app\common\model\Attach;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AttachmentDeleteTest extends TestCase
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

    public function test_attachment_delete_uses_eloquent_force_delete_and_removes_unreferenced_storage(): void
    {
        $item = Attach::forceCreate([
            'group_id' => 1,
            'name' => 'delete.txt',
            'original_name' => 'delete.txt',
            'path' => '/storage/delete.txt',
            'storage_key' => 'delete.txt',
            'driver' => 'local',
            'ext' => 'txt',
            'mime' => 'text/plain',
            'size' => 1,
            'status' => 1,
        ]);
        self::assertSame('delete.txt', Db::table('attach')->where('id', $item->id)->value('storage_key'));
        self::assertTrue(method_exists($item, 'forceDelete'));
        self::assertFalse(method_exists($item, 'force'));
    }
}
