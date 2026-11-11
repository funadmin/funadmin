<?php

declare(strict_types=1);

namespace Tests;

use app\admin\system\controller\AdminUpload;
use app\common\model\Attach;
use app\common\plugin\hook\HookRuntime;
use app\common\plugin\sdk\PluginActivationCompiler;
use app\common\plugin\sdk\PluginActivationReader;
use app\common\service\storage\UploadService;
use app\common\storage\LocalStorageDriver;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use support\Container;
use support\Context;
use support\Db;
use support\Request;
use Webman\App;
use Webman\Http\UploadFile;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UploadHooksIntegrationTest extends TestCase
{
    private string $root;
    private array $sources = [];
    private array $chunkIds = [];

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        $this->root = sys_get_temp_dir() . '/upload-hooks-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/plugins/uploadhooks/hooks', 0700, true);
        file_put_contents($this->root . '/plugins/uploadhooks/hooks/upload.php', <<<'PHP'
<?php
return [
    'completed' => static function ($payload) {
        $GLOBALS['upload_hook_events'][] = $payload;
        if (($GLOBALS['upload_hook_mode'] ?? '') === 'action-error') {
            throw new RuntimeException('upload action fixture failure');
        }
    },
    'result' => static function ($value) {
        if (($GLOBALS['upload_hook_mode'] ?? '') === 'unsafe') {
            $value['url'] = '/unowned-file';
        } elseif (($GLOBALS['upload_hook_mode'] ?? '') === 'scalar') {
            return false;
        } elseif (($GLOBALS['upload_hook_mode'] ?? '') === 'filter-error') {
            throw new RuntimeException('private exception details must stay private');
        } else {
            $value['extensions'] = ['uploadhooks' => ['label' => 'processed']];
        }
        return $value;
    },
];
PHP);
        (new PluginActivationCompiler($this->root . '/activation', $this->root . '/plugins'))->compile(
            ['uploadhooks' => ['code' => 'uploadhooks', 'version' => '1.0.0', 'lifecycle_state' => 'enabled', 'status' => 1]],
            ['uploadhooks' => ['serverHooks' => [
                ['id' => 'completed', 'point' => 'upload.completed', 'type' => 'action', 'file' => 'hooks/upload.php', 'handler' => 'completed'],
                ['id' => 'result', 'point' => 'upload.result', 'type' => 'filter', 'file' => 'hooks/upload.php', 'handler' => 'result'],
            ]]]
        );
        $reader = new PluginActivationReader($this->root . '/activation');
        Container::instance()->addDefinitions([
            HookRuntime::class => fn () => new HookRuntime($reader, $this->root . '/plugins', static function (): void {}),
        ]);
        $GLOBALS['upload_hook_events'] = [];
        $GLOBALS['upload_hook_mode'] = '';
        $this->request();
    }

    protected function tearDown(): void
    {
        foreach (Attach::all() as $attach) {
            (new LocalStorageDriver())->delete((string) $attach->storage_key);
        }
        foreach ($this->sources as $source) {
            if (is_file($source)) unlink($source);
        }
        foreach ($this->chunkIds as $directory) {
            if (is_dir($directory)) $this->removeDirectory($directory);
        }
        $this->removeDirectory($this->root);
        unset($GLOBALS['upload_hook_events'], $GLOBALS['upload_hook_mode']);
        Db::rollBack();
        Context::destroy();
    }

    public function test_completed_hook_fires_once_per_created_or_reused_upload_with_explicit_metadata(): void
    {
        $service = new UploadService();
        $created = $service->attach($this->file('same content'), 3, 7);
        $reused = $service->attach($this->file('same content', 'another.txt'), 3, 7);

        self::assertSame($created->id, $reused->id);
        self::assertCount(2, $GLOBALS['upload_hook_events']);
        self::assertSame((int) $created->id, $GLOBALS['upload_hook_events'][0]['id']);
        self::assertSame(7, $GLOBALS['upload_hook_events'][0]['admin_id']);
        self::assertSame(3, $GLOBALS['upload_hook_events'][0]['member_id']);
        self::assertTrue($GLOBALS['upload_hook_events'][0]['created']);
        self::assertFalse($GLOBALS['upload_hook_events'][0]['reused']);
        self::assertFalse($GLOBALS['upload_hook_events'][1]['created']);
        self::assertTrue($GLOBALS['upload_hook_events'][1]['reused']);
    }

    public function test_unfinished_chunks_do_not_emit_completed_and_merge_emits_once(): void
    {
        $request = $this->request();
        $request->context['admin'] = ['id' => 7];
        $request->setPost(['fileName' => 'joined.txt', 'fileSize' => 11]);
        $chunkId = bin2hex(random_bytes(16));
        $owner = ['admin' => 7, 'member' => 0];
        $this->chunkIds[] = runtime_path('chunks/' . hash('sha256', json_encode($owner) . ':' . $chunkId));
        $service = new UploadService();

        $first = $service->chunkUpload($this->file('hello '), $chunkId, 0, 2);
        self::assertSame('', $first['url']);
        self::assertSame([], $GLOBALS['upload_hook_events']);
        $second = $service->chunkUpload($this->file('world'), $chunkId, 1, 2);

        self::assertNotSame('', $second['url']);
        self::assertCount(1, $GLOBALS['upload_hook_events']);
        self::assertTrue($GLOBALS['upload_hook_events'][0]['created']);
    }

    public function test_completed_action_failure_does_not_undo_saved_file(): void
    {
        $GLOBALS['upload_hook_mode'] = 'action-error';
        $attach = (new UploadService())->attach($this->file('saved before notification'), 0, 7);

        self::assertTrue(Attach::whereKey($attach->id)->exists());
        self::assertFileExists(public_path('storage/' . $attach->storage_key));
        self::assertCount(1, $GLOBALS['upload_hook_events']);
    }

    public function test_admin_upload_filter_can_add_extensions_without_changing_core_values(): void
    {
        $response = $this->uploadResponse();
        $data = json_decode($response->rawBody(), true)['data'];

        self::assertSame(200, $response->getStatusCode(), $response->rawBody());
        self::assertSame('http.txt', $data['name']);
        self::assertSame((string) Attach::first()->id, $data['id']);
        self::assertSame(['uploadhooks' => ['label' => 'processed']], $data['extensions']);
        self::assertStringStartsWith('/storage/', $data['url']);
        self::assertCount(1, $GLOBALS['upload_hook_events']);
    }

    public function test_admin_upload_filter_rejects_unsafe_or_invalid_results_but_keeps_attachment(): void
    {
        foreach (['unsafe', 'scalar', 'filter-error'] as $mode) {
            $GLOBALS['upload_hook_mode'] = $mode;
            $response = $this->uploadResponse();
            self::assertSame(200, $response->getStatusCode(), '沿用后台可恢复错误的 HTTP 协议');
            self::assertSame(422, json_decode($response->rawBody(), true)['code']);
            self::assertStringNotContainsString('private exception details', $response->rawBody());
            self::assertSame(1, Attach::count(), '过滤失败不删除已持久化附件，后续上传复用同一条记录');
        }
    }


    public function test_cms_upload_uses_the_same_storage_hooks_and_can_be_saved_as_a_content_attachment(): void
    {
        require_once __DIR__.'/Cms/bootstrap.php';
        \app\cms\service\Schema::install();\app\cms\service\SiteRoleSchema::install();\app\cms\service\PageFormSchema::install();\app\cms\service\SoftDeleteSchema::install();\app\cms\service\ModelService::seed();
        $site=(new \app\cms\service\SiteService())->save(['code'=>'upload','name'=>'Upload','host'=>'uploads.test'],'1');
        $response=$this->uploadResponse($site['id']);$result=json_decode($response->rawBody(),true);
        self::assertSame(200,$result['code'],$response->rawBody());$file=$result['data'];
        self::assertSame((string)Attach::first()->id,$file['id']);self::assertFileExists(public_path('storage/'.Attach::first()->storage_key));
        self::assertSame(['uploadhooks'=>['label'=>'processed']],$file['extensions']);self::assertCount(1,$GLOBALS['upload_hook_events']);
        $content=(new \app\cms\service\ContentService())->save($site['id'],'zh-cn',[
            'model_id'=>(string)Db::table('cms_content_model')->where('code','download')->value('id'),
            'title'=>'Download','desired_path'=>'/download-test','template'=>'download',
            'body_html'=>'<h2>Manual</h2><p><strong>Download</strong></p>','data'=>['file'=>$file['id']],
        ],'1');
        self::assertSame($file['id'],$content['revision']['data']['file']);
        self::assertSame('<h2>Manual</h2><p><strong>Download</strong></p>',$content['revision']['body_html']);
    }

    private function uploadResponse(?string $siteId = null): \support\Response
    {
        new App(Request::class, new NullLogger(), app_path(), public_path());
        $boundary = 'hook-upload-boundary';
        $body = "--$boundary\r\nContent-Disposition: form-data; name=\"groupId\"\r\n\r\n1\r\n--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"http.txt\"\r\nContent-Type: text/plain\r\n\r\nhook HTTP upload\r\n--$boundary--\r\n";
        if ($siteId !== null) $body = "--$boundary\r\nContent-Disposition: form-data; name=\"site_id\"\r\n\r\n$siteId\r\n" . $body;
        $request = new Request("POST /admin/upload HTTP/1.1\r\nHost: localhost\r\nContent-Type: multipart/form-data; boundary=$boundary\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => $siteId === null ? 7 : 1];
        Context::set(\Webman\Http\Request::class, $request);
        return $siteId === null ? (new AdminUpload())->upload() : (new \app\admin\plugins\cms\controller\Content())->upload();
    }

    private function request(): Request
    {
        $request = new Request("POST /admin/upload HTTP/1.1\r\nHost: localhost\r\n\r\n");
        Context::set(\Webman\Http\Request::class, $request);
        return $request;
    }

    private function file(string $content, string $name = 'test.txt'): UploadFile
    {
        $source = tempnam(runtime_path(), 'hook-upload-');
        $this->sources[] = $source;
        file_put_contents($source, $content);
        return new UploadFile($source, $name, 'text/plain', UPLOAD_ERR_OK);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) return;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($directory);
    }
}
