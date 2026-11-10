<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\SystemStorage;
use app\common\storage\StorageDriverInterface;
use app\common\storage\StorageDriverRegistry;
use app\common\storage\StoredFile;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use Webman\Http\UploadFile;

/** 切换上传驱动：配置行必须用 Eloquent 读取（不是 ThinkPHP find()），且下一次 syscfg() 立即看到新值。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class StorageDriverUpdateTest extends TestCase
{
    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        Db::table('config')->insert(['code' => 'upload_driver', 'group' => 'upload', 'value' => 'local', 'status' => 1, 'type' => 'select']);
        syscfg_forget('upload', 'upload_driver');
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_switching_driver_persists_and_is_visible_to_the_next_syscfg_read(): void
    {
        self::assertSame('local', syscfg('upload', 'upload_driver'), 'warm the syscfg cache with the old value');
        $registry = new StorageDriverRegistry(null, static fn (string $point, mixed $value): mixed => $value);
        $registry->register(new class implements StorageDriverInterface {
            public function name(): string { return 'fake'; }
            public function label(): string { return '测试驱动'; }
            public function source(): string { return 'core'; }
            public function available(): bool { return true; }
            public function store(UploadFile $file, string $directory, string $rule = ''): StoredFile { throw new \LogicException('unused'); }
            public function delete(string $storageKey): bool { return false; }
        });
        $body = json_encode(['driver' => 'fake'], JSON_THROW_ON_ERROR);
        $request = new Request("PUT /admin/system/storage HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);

        $response = json_decode((new SystemStorage($registry))->update()->rawBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response['code'], (string) ($response['msg'] ?? ''));
        self::assertSame('fake', Db::table('config')->where('code', 'upload_driver')->value('value'));
        self::assertSame('fake', syscfg('upload', 'upload_driver'), 'driver switch must invalidate the cached upload config group');
    }
}
