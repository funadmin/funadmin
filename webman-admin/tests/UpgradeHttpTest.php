<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\CasbinService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use support\Context;
use support\Db;
use support\Request;
use Webman\App;
use Webman\Route;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class UpgradeHttpTest extends TestCase
{
    private array $session;

    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'casbin_rule', 'auth_group', 'admin'] as $table) Db::table($table)->delete();
        Db::table('admin')->insert(['id' => 1, 'username' => 'upgrade_root', 'password' => password_hash('upgrade-root-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('admin')->insert(['id' => 2, 'username' => 'upgrade_reader', 'password' => password_hash('upgrade-reader-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '升级只读', 'code' => 'upgrade_reader', 'level' => 10, 'data_scope' => 'self', 'status' => 1]);
        $permissionIds = Db::table('permission')->whereIn('code', ['systemupgrade:status', 'systemupgrade:check'])->pluck('id')->all();
        (new CasbinService())->syncAdminRoles(2, [2]);
        (new CasbinService())->syncRolePermissions(2, $permissionIds);
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'upgrade_reader', 'upgrade-reader-password', '127.0.0.2');
        (new \ReflectionProperty(App::class, 'logger'))->setValue(null, new NullLogger());
        Route::load([config_path()]);
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_upgrade_status_is_readable_but_execution_is_not_granted(): void
    {
        self::assertSame(401, $this->call('GET', '/admin/system/upgrade/status', authenticated: false)->getStatusCode());
        self::assertSame(200, $this->call('GET', '/admin/system/upgrade/status')->getStatusCode());
        self::assertSame(403, $this->call('POST', '/admin/system/upgrade/execute', ['manifestId' => 'x', 'operationToken' => 'y'])->getStatusCode());
    }

    public function test_upgrade_write_rejects_missing_csrf_before_execution(): void
    {
        self::assertSame(403, $this->call('POST', '/admin/system/upgrade/execute', ['manifestId' => 'x', 'operationToken' => 'y'], csrf: false)->getStatusCode());
    }

    private function call(string $method, string $path, array $data = [], bool $authenticated = true, bool $csrf = true): \Webman\Http\Response
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $headers = $authenticated ? 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $this->session['cookie'] . "\r\n" : '';
        if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $this->session['csrfToken'] . "\r\n";
        $request = new Request("$method $path HTTP/1.1\r\nHost: localhost\r\n" . $headers . "Content-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $matched = Route::dispatch($method, $path);
        self::assertSame(\FastRoute\Dispatcher::FOUND, $matched[0], "$method $path");
        $request->controller = $matched[1]['callback'][0]; $request->action = $matched[1]['callback'][1]; $request->route = $matched[1]['route'];
        Context::set(\Webman\Http\Request::class, $request);
        return (App::getCallback('', 'admin', $matched[1]['callback'], $matched[2], false, $request->route))($request);
    }
}
