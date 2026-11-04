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

    public function test_local_storage_accepts_webman_uploads_and_rejects_unsafe_paths(): void
    {
        $source = tempnam(runtime_path(), 'native-upload-');
        file_put_contents($source, 'native upload fixture');
        $driver = new \app\common\storage\LocalStorageDriver();
        $stored = null;
        try {
            $file = new \Webman\Http\UploadFile($source, 'sample.TXT', 'text/plain', UPLOAD_ERR_OK);
            $stored = $driver->store($file, 'native-runtime-test');
            self::assertMatchesRegularExpression('#^native-runtime-test/[a-f0-9]{32}\.txt$#', $stored->key);
            self::assertSame('native upload fixture', file_get_contents(public_path('storage/' . $stored->key)));
            self::assertTrue($driver->delete($stored->key));
            self::assertFileDoesNotExist(public_path('storage/' . $stored->key));
            self::assertFalse($driver->delete('../outside.txt'));
            $this->expectException(\RuntimeException::class);
            $driver->store($file, '../outside');
        } finally {
            if ($stored !== null) $driver->delete($stored->key);
            if (is_file($source)) unlink($source);
        }
    }

    public function test_native_upload_service_validates_webman_files_before_storage(): void
    {
        $source = tempnam(runtime_path(), 'upload-validation-');
        file_put_contents($source, '<?php echo 1;');
        $request = new \support\Request("POST /admin/upload HTTP/1.1\r\nHost: localhost\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $request);
        try {
            $service = new \app\common\service\storage\UploadService();
            foreach (['shell.PHP', 'fake.png'] as $name) {
                try {
                    $service->attach(new \Webman\Http\UploadFile($source, $name, 'image/png', UPLOAD_ERR_OK), 0, 7);
                    self::fail('危险扩展名或伪造图片不得落库');
                } catch (\InvalidArgumentException $exception) {
                    self::assertNotSame('', $exception->getMessage());
                }
            }
            self::assertSame(0, Attach::count());
        } finally {
            unlink($source);
        }
    }

    public function test_native_upload_persists_metadata_and_reuses_content(): void
    {
        $request = new \support\Request("POST /admin/upload HTTP/1.1\r\nHost: localhost\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $request);
        $service = new \app\common\service\storage\UploadService();
        $source = tempnam(runtime_path(), 'upload-success-');
        file_put_contents($source, 'native attachment');
        $stored = null;
        try {
            $stored = $service->attach(new \Webman\Http\UploadFile($source, 'sample.txt', 'text/plain', UPLOAD_ERR_OK), 0, 7);
            self::assertSame(7, (int) $stored->admin_id);
            self::assertSame('sample.txt', $stored->original_name);
            self::assertSame(md5('native attachment'), $stored->md5);
            self::assertSame('text/plain', $stored->mime);
            self::assertSame('native attachment', file_get_contents(public_path('storage/' . $stored->storage_key)));
            file_put_contents($source, 'native attachment');
            $reused = $service->attach(new \Webman\Http\UploadFile($source, 'another.txt', 'text/plain', UPLOAD_ERR_OK), 0, 7);
            self::assertSame($stored->id, $reused->id);
            self::assertSame(1, Attach::count());
        } finally {
            if ($stored !== null) (new \app\common\storage\LocalStorageDriver())->delete($stored->storage_key);
            if (is_file($source)) unlink($source);
        }
    }

    public function test_reused_upload_service_reads_current_request_context(): void
    {
        $first = new \support\Request("POST /admin/upload?path=old-context HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $first->context['admin'] = ['id' => 7];
        Context::set(\Webman\Http\Request::class, $first);
        $service = new \app\common\service\storage\UploadService();
        $second = new \support\Request("POST /admin/upload?path=current-context HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $second->context['admin'] = ['id' => 8];
        $second->setPost(['fileName' => 'current.txt', 'fileSize' => 7]);
        Context::set(\Webman\Http\Request::class, $second);
        $source = tempnam(runtime_path(), 'request-scope-');
        file_put_contents($source, 'current');
        $stored = null;
        try {
            $service->chunkUpload(new \Webman\Http\UploadFile($source, 'current.txt', 'text/plain', UPLOAD_ERR_OK), bin2hex(random_bytes(16)), 0, 1);
            $stored = Attach::firstOrFail();
            self::assertSame(8, (int) $stored->admin_id);
            self::assertStringStartsWith('current-context/', $stored->storage_key);
        } finally {
            if ($stored !== null) (new \app\common\storage\LocalStorageDriver())->delete($stored->storage_key);
            if (is_file($source)) unlink($source);
        }
    }

    public function test_native_chunk_upload_merges_only_complete_owner_scoped_chunks(): void
    {
        $request = new \support\Request("POST /admin/upload HTTP/1.1\r\nHost: localhost\r\n\r\n");
        $request->setPost(['fileName' => 'joined.txt', 'fileSize' => 11]);
        $request->context['admin'] = ['id' => 7];
        Context::set(\Webman\Http\Request::class, $request);
        $service = new \app\common\service\storage\UploadService();
        $id = bin2hex(random_bytes(16));
        $stored = null;
        try {
            foreach (['hello ', 'world'] as $index => $content) {
                $source = tempnam(runtime_path(), 'chunk-test-');
                file_put_contents($source, $content);
                $result = $service->chunkUpload(new \Webman\Http\UploadFile($source, 'joined.txt', 'text/plain', UPLOAD_ERR_OK), $id, $index, 2);
                if ($index === 0) self::assertSame('', $result['url']);
            }
            $stored = Attach::firstOrFail();
            self::assertSame('hello world', file_get_contents(public_path('storage/' . $stored->storage_key)));
            self::assertSame(7, (int) $stored->admin_id);
            self::assertSame($stored->path, $result['url']);
            $this->expectException(\InvalidArgumentException::class);
            $service->chunkMerge('../outside', 2, 'file.txt', 'txt');
        } finally {
            if ($stored !== null) (new \app\common\storage\LocalStorageDriver())->delete($stored->storage_key);
        }
    }

    public function test_image_watermark_uses_installed_native_image_library(): void
    {
        $watermark = public_path('native-watermark-' . bin2hex(random_bytes(8)) . '.png');
        $source = tempnam(runtime_path(), 'image-upload-');
        $image = imagecreatetruecolor(12, 12);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagepng($image, $source);
        $mark = imagecreatetruecolor(2, 2);
        imagefill($mark, 0, 0, imagecolorallocate($mark, 255, 0, 0));
        imagepng($mark, $watermark);
        $key = 'syscfg_' . hash('sha256', "upload\0");
        \support\Cache::set($key, ['cached' => true, 'value' => ['upload_water' => 1, 'upload_water_thumb' => '/' . basename($watermark), 'upload_water_position' => 9, 'upload_water_alpha' => 100]]);
        Context::set(\Webman\Http\Request::class, new \support\Request("POST /admin/upload HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        $stored = null;
        try {
            $stored = (new \app\common\service\storage\UploadService())->attach(new \Webman\Http\UploadFile($source, 'image.png', 'image/png', UPLOAD_ERR_OK), 0, 7);
            $output = imagecreatefrompng(public_path('storage/' . $stored->storage_key));
            self::assertSame(0xff0000, imagecolorat($output, 11, 11));
            self::assertSame(0xffffff, imagecolorat($output, 0, 0));
        } finally {
            \support\Cache::delete($key);
            if ($stored !== null) (new \app\common\storage\LocalStorageDriver())->delete($stored->storage_key);
            if (is_file($source)) unlink($source);
            unlink($watermark);
        }
    }

    public function test_failed_watermark_does_not_leave_an_untracked_public_file(): void
    {
        $source = tempnam(runtime_path(), 'invalid-watermark-');
        $image = imagecreatetruecolor(2, 2);
        imagepng($image, $source);
        $key = 'syscfg_' . hash('sha256', "upload\0");
        \support\Cache::set($key, ['cached' => true, 'value' => ['upload_water' => 1, 'upload_water_thumb' => '/missing-watermark.png']]);
        Context::set(\Webman\Http\Request::class, new \support\Request("POST /admin/upload?path=watermark-failure-test HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        $before = glob(public_path('storage/watermark-failure-test/*')) ?: [];
        try {
            try {
                (new \app\common\service\storage\UploadService())->attach(new \Webman\Http\UploadFile($source, 'image.png', 'image/png', UPLOAD_ERR_OK), 0, 7);
                self::fail('无效水印配置必须拒绝上传');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('水印图片不存在', $exception->getMessage());
            }
            self::assertSame($before, glob(public_path('storage/watermark-failure-test/*')) ?: []);
            self::assertSame(0, Attach::count());
        } finally {
            \support\Cache::delete($key);
            if (is_file($source)) unlink($source);
        }
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
