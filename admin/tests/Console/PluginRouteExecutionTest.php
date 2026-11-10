<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\authentication\service\BrowserAuthService;
use app\common\plugin\sdk\PluginScaffolder;
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
        \Tests\Support\TestDatabase::migrate();
        $this->root = sys_get_temp_dir() . '/funadmin-plugin-route-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        (new PluginScaffolder($this->root . '/plugins'))->scaffold('routefixture');
        (new \app\common\plugin\sdk\PluginActivationCompiler($this->root . '/runtime/plugins/activation', $this->root . '/plugins'))->compile(['routefixture' => ['code' => 'routefixture', 'status' => 1, 'lifecycle_state' => 'enabled']], []);
        Db::table('admin')->insert(['id' => 99, 'username' => 'plugin_route', 'password' => password_hash('isolated-route-password', PASSWORD_DEFAULT), 'status' => 1]);
        \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions([['code' => 'routefixture:index:index', 'name' => '插件读取'], ['code' => 'routefixture:permission-fixture:read', 'name' => '控制器读取']], 'plugin', 'routefixture');
        Db::table('auth_group')->insert(['id' => 99, 'name' => '插件测试', 'code' => 'plugin_route', 'status' => 1]);
        $casbin = new \app\admin\authorization\service\CasbinService();
        $casbin->syncAdminRoles(99, [99]);
        $casbin->syncRolePermissions(99, Db::table('permission')->whereIn('code', ['routefixture:index:index', 'routefixture:permission-fixture:read'])->pluck('id')->all());
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->login = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'plugin_route', 'isolated-route-password', '127.0.0.1');
        file_put_contents($this->root . '/route.php', '<?php \\app\\common\\plugin\\sdk\\PluginRoute::adminGroup(new \\Webman\\Route(), "routefixture", "routefixture:index:index", static function (): void { \\Webman\\Route::post("/plugin-fixture", static fn () => json(["code" => 200])); }, ' . var_export($this->root, true) . ');');
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
        Db::table('permission')->where('code', 'routefixture:index:index')->update(['status' => 0]);
        self::assertSame(403, $this->call(true, true));
    }

    public function test_undeclared_manifest_permission_is_rejected_before_route_registration(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('未在 manifest 中声明');
        \app\common\plugin\sdk\PluginRoute::adminGroup(new Route(), 'routefixture', 'routefixture:item:delete', static function (): void {}, $this->root);
    }

    public function test_helper_blocks_disabled_plugins_even_on_custom_closure_routes(): void
    {
        Route::load([$this->root]);
        (new \app\common\plugin\sdk\PluginActivationCompiler($this->root . '/runtime/plugins/activation', $this->root . '/plugins'))->compile(['routefixture' => ['code' => 'routefixture', 'status' => 0, 'lifecycle_state' => 'disabled']], []);
        self::assertSame(404, $this->call(true, true));
    }

    public function test_plugin_controller_requires_the_derived_per_method_code_owned_by_the_plugin(): void
    {
        (new \ReflectionProperty(App::class, 'logger'))->setValue(null, new \Psr\Log\NullLogger());
        $controller = \app\admin\plugins\routefixture\controller\PermissionFixture::class;
        $call = function (string $action, bool $authenticated = true, bool $csrf = true) use ($controller): int {
            $headers = $authenticated ? 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $this->login['cookie'] . "\r\n" : '';
            if ($csrf) $headers .= 'X-CSRF-TOKEN: ' . $this->login['csrfToken'] . "\r\n";
            $request = new Request("POST /admin/plugins/routefixture/metadata HTTP/1.1\r\nHost: localhost\r\n" . $headers . "\r\n");
            $request->controller = $controller;
            $request->action = $action;
            Context::set(\Webman\Http\Request::class, $request);
            return (App::getCallback('', 'admin', [$controller, $action], [], false))($request)->getStatusCode();
        };
        self::assertSame(401, $call('read', false));
        self::assertSame(200, $call('read'));
        self::assertSame(403, $call('read', csrf: false));
        // 未登记推导码的方法拒绝；方法上的权限注解对插件无效，不能借用其它权限码。
        self::assertSame(403, $call('undeclared'));
        self::assertSame(403, $call('foreign'));
        // 同名权限码若不归属本插件（例如宿主登记），也不能放行。
        Db::table('permission')->insert(['app_name' => 'admin', 'code' => 'routefixture:permission-fixture:undeclared', 'obj' => 'routefixture/permission-fixture', 'act' => 'undeclared',
            'name' => 'x', 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'system', 'source_name' => '']);
        \app\admin\authorization\service\CasbinService::instance()->syncRolePermissions(99, Db::table('permission')->whereIn('code', ['routefixture:permission-fixture:read', 'routefixture:permission-fixture:undeclared'])->pluck('id')->all());
        self::assertSame(403, $call('undeclared'));
        Db::table('permission')->where('code', 'routefixture:permission-fixture:read')->update(['status' => 0]);
        self::assertSame(403, $call('read'));
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

namespace app\admin\plugins\routefixture\controller;

final class PermissionFixture extends \app\common\controller\AdminController
{
    #[\app\common\annotation\Permission('routefixture:index:index')]
    public function read(): \support\Response { return $this->ok(); }
    public function undeclared(): \support\Response { return $this->ok(); }
    // 声明其它插件命名空间的权限码：必须拒绝。
    #[\app\common\annotation\Permission('other:item:list')]
    public function foreign(): \support\Response { return $this->ok(); }
}
