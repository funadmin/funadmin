<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\BrowserAuthService;
use app\common\storage\StorageDriverRegistry;
use app\admin\authorization\service\CasbinService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use support\Container;
use support\Context;
use support\Db;
use support\Request;
use Webman\App;
use Webman\Route;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AttachmentHttpTest extends TestCase
{
    private array $session;

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'casbin_rule', 'auth_group', 'admin'] as $table) Db::table($table)->delete();
        Db::table('admin')->insert(['id' => 1, 'username' => 'attachment_root', 'password' => password_hash('attachment-root-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('admin')->insert(['id' => 2, 'username' => 'attachment_reader', 'password' => password_hash('attachment-reader-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '附件只读', 'code' => 'attachment_reader', 'level' => 10, 'data_scope' => 'self', 'status' => 1]);
        $permissionIds = Db::table('permission')->whereIn('code', ['system:attachment:index', 'system:attachment-group:tree', 'system:storage:index'])->pluck('id')->all();
        (new CasbinService())->syncAdminRoles(2, [2]);
        (new CasbinService())->syncRolePermissions(2, $permissionIds);
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'attachment_reader', 'attachment-reader-password', '127.0.0.2');
        (new \ReflectionProperty(App::class, 'logger'))->setValue(null, new NullLogger());
        Container::instance()->addDefinitions([StorageDriverRegistry::class => static fn (): StorageDriverRegistry => new StorageDriverRegistry()]);
        Route::load([config_path()]);
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_attachment_routes_require_authentication_and_exact_read_permissions(): void
    {
        self::assertSame(401, $this->call('GET', '/admin/system/attachment', authenticated: false)->getStatusCode());
        $list = $this->call('GET', '/admin/system/attachment');
        self::assertSame(200, $list->getStatusCode(), $list->rawBody());
        self::assertSame(200, $this->call('GET', '/admin/system/attachment-group/tree')->getStatusCode());
        self::assertSame(200, $this->call('GET', '/admin/system/storage')->getStatusCode());
        self::assertSame(403, $this->call('POST', '/admin/system/attachment/move', ['ids' => [1], 'groupId' => 1])->getStatusCode());
    }

    public function test_attachment_write_requires_csrf_before_persisting(): void
    {
        self::assertSame(403, $this->call('POST', '/admin/system/attachment/move', ['ids' => [1], 'groupId' => 1], csrf: false)->getStatusCode());
    }

    public function test_authorized_multipart_upload_persists_file_and_keeps_csrf(): void
    {
        new App(Request::class, new NullLogger(), app_path(), public_path());
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'attachment_root', 'attachment-root-password', '127.0.0.3');
        $boundary = 'native-attachment-boundary';
        $body = "--$boundary\r\nContent-Disposition: form-data; name=\"groupId\"\r\n\r\n1\r\n--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"http.txt\"\r\nContent-Type: text/plain\r\n\r\nnative HTTP upload\r\n--$boundary--\r\n";
        $stored = null;
        try {
            foreach ([false, true] as $csrf) {
                $headers = 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $this->session['cookie'] . "\r\n";
                if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $this->session['csrfToken'] . "\r\n";
                $request = new Request("POST /admin/upload HTTP/1.1\r\nHost: localhost\r\n" . $headers . "Content-Type: multipart/form-data; boundary=$boundary\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
                $matched = Route::dispatch('POST', '/admin/upload');
                self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0]);
                $request->controller = $matched[1]['callback'][0];
                $request->action = $matched[1]['callback'][1];
                $request->route = $matched[1]['route'];
                Context::set(\Webman\Http\Request::class, $request);
                $response = (App::getCallback('', 'admin', $matched[1]['callback'], $matched[2], false, $request->route))($request);
                self::assertSame($csrf ? 200 : 403, $response->getStatusCode(), $response->rawBody() . ' ' . (string) $response->exception());
                if (!$csrf) self::assertSame(0, Db::table('attach')->count());
            }
            $stored = \app\common\model\Attach::firstOrFail();
            self::assertSame(1, (int) $stored->admin_id);
            self::assertSame('native HTTP upload', file_get_contents(public_path('storage/' . $stored->storage_key)));
            $data = json_decode($response->rawBody(), true)['data'];
            self::assertSame('http.txt', $data['name']);
            self::assertSame(18, $data['size']);
            self::assertFalse($data['reused']);
        } finally {
            if ($stored !== null) (new \app\common\storage\LocalStorageDriver())->delete($stored->storage_key);
        }
    }

    private function call(string $method, string $path, array $data = [], bool $authenticated = true, bool $csrf = true): \Webman\Http\Response
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $headers = $authenticated ? 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $this->session['cookie'] . "\r\n" : '';
        if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $this->session['csrfToken'] . "\r\n";
        $request = new Request("$method $path HTTP/1.1\r\nHost: localhost\r\n" . $headers . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $matched = Route::dispatch($method, $path);
        self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], "$method $path");
        $request->controller = $matched[1]['callback'][0];
        $request->action = $matched[1]['callback'][1];
        $request->route = $matched[1]['route'];
        Context::set(\Webman\Http\Request::class, $request);
        return (App::getCallback('', 'admin', $matched[1]['callback'], $matched[2], false, $request->route))($request);
    }
}
