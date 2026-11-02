<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\authentication\service\BrowserAuthService;
use app\common\plugin\sdk\PluginScaffolder;
use app\common\service\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use Webman\App;
use Webman\Route;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class PluginRouteExecutionTest extends TestCase
{
    private string $root;
    private array $login;

    protected function setUp(): void
    {
        (new MigrationService())->run();
        $this->root = sys_get_temp_dir() . '/funadmin-plugin-route-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        (new PluginScaffolder($this->root . '/plugins'))->scaffold('routefixture');
        Db::table('admin')->insert(['id' => 99, 'username' => 'plugin_route', 'password' => password_hash('isolated-route-password', PASSWORD_DEFAULT), 'status' => 1]);
        \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions([['code' => 'routefixture:item:list', 'name' => '插件读取']], 'plugin', 'routefixture');
        Db::table('auth_group')->insert(['id' => 99, 'name' => '插件测试', 'code' => 'plugin_route', 'status' => 1]);
        $casbin = new \app\admin\authorization\service\CasbinService();
        $casbin->syncAdminRoles(99, [99]);
        $casbin->syncRolePermissions(99, Db::table('permission')->where('code', 'routefixture:item:list')->pluck('id')->all());
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->login = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'plugin_route', 'isolated-route-password', '127.0.0.1');
        file_put_contents($this->root . '/route.php', '<?php \\app\\common\\plugin\\sdk\\PluginRoute::adminGroup(new \\Webman\\Route(), "routefixture", "routefixture:item:list", static function (): void { \\Webman\\Route::post("/plugin-fixture", static fn () => json(["code" => 200])); }, ' . var_export($this->root, true) . ');');
    }

    protected function tearDown(): void
    {
        Context::destroy();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_native_plugin_route_checks_browser_login_manifest_permission_and_csrf(): void
    {
        Route::load([$this->root]);
        self::assertSame(401, $this->call(false, false));
        self::assertSame(403, $this->call(true, false));
        self::assertSame(200, $this->call(true, true));
        Db::table('permission')->where('code', 'routefixture:item:list')->update(['status' => 0]);
        self::assertSame(403, $this->call(true, true));
    }

    public function test_undeclared_manifest_permission_is_rejected_before_route_registration(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未在 manifest 中声明');
        \app\common\plugin\sdk\PluginRoute::adminGroup(new Route(), 'routefixture', 'routefixture:item:delete', static function (): void {}, $this->root);
    }

    private function call(bool $authenticated, bool $csrf): int
    {
        $headers = $authenticated ? 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $this->login['cookie'] . "\r\n" : '';
        if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $this->login['csrfToken'] . "\r\n";
        $request = new Request("POST /plugin-fixture HTTP/1.1\r\nHost: localhost\r\n" . $headers . "\r\n");
        $match = Route::dispatch('POST', '/plugin-fixture');
        self::assertSame(\FastRoute\Dispatcher::FOUND, $match[0]);
        $request->route = $match[1]['route'];
        Context::set(\Webman\Http\Request::class, $request);
        return (App::getCallback('', 'admin', $match[1]['callback'], $match[2], false, $request->route))($request)->getStatusCode();
    }
}
