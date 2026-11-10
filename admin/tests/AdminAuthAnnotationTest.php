<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\controller\AdminProfile;
use app\admin\authentication\service\BrowserAuthService;
use app\admin\authorization\service\CasbinService;
use app\admin\middleware\CheckAdminApiRoleMiddleware;
use app\admin\plugin\controller\SystemPlugin;
use app\common\annotation\NoNeedLogin;
use app\common\annotation\NoNeedPermission;
use app\common\annotation\Permission;
use app\common\controller\AdminController;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;
use support\Response;

final class AdminAuthAnnotationProbe extends AdminController
{
    #[Permission('authorization:admin:index')]
    public function guarded(): Response { return $this->ok(); }

    #[Permission('borrowfix:item:view')]
    public function borrowed(): Response { return $this->ok(); }

    #[NoNeedLogin]
    public function open(): Response { return $this->ok(data: ['admin' => $this->request->context['admin'] ?? null]); }

    #[NoNeedPermission]
    public function loginOnly(): Response { return $this->ok(); }
}

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminAuthAnnotationTest extends TestCase
{
    private array $reader;
    private array $root;

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'casbin_rule', 'auth_group', 'admin'] as $table) {
            Db::table($table)->delete();
        }
        Db::table('admin')->insert([
            ['id' => 1, 'username' => 'annotation_root', 'password' => password_hash('annotation-root-pass', PASSWORD_DEFAULT), 'status' => 1],
            ['id' => 2, 'username' => 'annotation_reader', 'password' => password_hash('annotation-reader-pass', PASSWORD_DEFAULT), 'status' => 1],
            ['id' => 3, 'username' => 'annotation_nobody', 'password' => password_hash('annotation-nobody-pass', PASSWORD_DEFAULT), 'status' => 1],
        ]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '管理员只读', 'code' => 'annotation_reader', 'level' => 10, 'data_scope' => 'self', 'status' => 1]);
        $permissionIds = Db::table('permission')->where('code', 'authorization:admin:index')->pluck('id')->all();
        self::assertNotEmpty($permissionIds, '基线必须含 authorization:admin:index');
        (new CasbinService())->syncAdminRoles(2, [2]);
        (new CasbinService())->syncRolePermissions(2, $permissionIds);
        $this->root = $this->login('annotation_root', 'annotation-root-pass');
        $this->reader = $this->login('annotation_reader', 'annotation-reader-pass');
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    private function login(string $username, string $password): array
    {
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        return $auth->login($anonymous['cookie'], $anonymous['csrfToken'], $username, $password, '127.0.0.1');
    }

    private function dispatch(string $action, ?array $session): \Webman\Http\Response
    {
        $cookie = $session === null ? '' : 'Cookie: ' . BrowserAuthService::COOKIE . '=' . $session['cookie'] . "\r\n";
        $request = new Request("GET /admin/probe HTTP/1.1\r\nHost: localhost\r\n" . $cookie . "\r\n");
        $request->controller = AdminAuthAnnotationProbe::class;
        $request->action = $action;
        Context::set(\Webman\Http\Request::class, $request);
        $controller = new AdminAuthAnnotationProbe();
        return (new CheckAdminApiRoleMiddleware())->process($request, static fn () => $controller->$action());
    }

    public function test_hardcoded_exceptions_are_replaced_by_annotations(): void
    {
        // /admin/profile 不继承 AdminController、不经过后台权限中间件，自行校验浏览器会话；注解对它无效，不应保留误导性标注。
        self::assertFalse(is_subclass_of(AdminProfile::class, AdminController::class));
        self::assertSame([], (new \ReflectionClass(AdminProfile::class))->getAttributes(NoNeedPermission::class));
        self::assertNotEmpty((new \ReflectionMethod(SystemPlugin::class, 'enabledModules'))->getAttributes(NoNeedPermission::class));
        $source = (string) file_get_contents((new \ReflectionClass(CheckAdminApiRoleMiddleware::class))->getFileName());
        self::assertStringNotContainsString('admin/adminprofile', $source);
        self::assertStringNotContainsString('enabledmodules', $source);
    }

    public function test_permission_annotation_is_enforced_and_super_admin_bypasses(): void
    {
        $nobody = $this->login('annotation_nobody', 'annotation-nobody-pass');
        self::assertSame(401, $this->dispatch('guarded', null)->getStatusCode());
        self::assertSame(200, $this->dispatch('guarded', $this->reader)->getStatusCode(), '持有注解权限码放行');
        self::assertSame(403, $this->dispatch('guarded', $nobody)->getStatusCode(), '无权限 403');
        self::assertSame(200, $this->dispatch('guarded', $this->root)->getStatusCode(), '超级管理员放行');
    }

    public function test_host_annotation_cannot_borrow_a_plugin_permission_code(): void
    {
        $id = Db::table('permission')->insertGetId(['app_name' => 'admin', 'code' => 'borrowfix:item:view', 'obj' => 'borrowfix/item', 'act' => 'view', 'name' => '插件权限',
            'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'plugin', 'source_name' => 'borrowfix']);
        (new CasbinService())->syncRolePermissions(2, array_merge(Db::table('permission')->where('code', 'authorization:admin:index')->pluck('id')->all(), [$id]));
        $reader = $this->login('annotation_reader', 'annotation-reader-pass');
        self::assertSame(403, $this->dispatch('borrowed', $reader)->getStatusCode(), '宿主方法不能用注解借用插件的权限码');
    }

    public function test_no_need_permission_only_requires_login(): void
    {
        $nobody = $this->login('annotation_nobody', 'annotation-nobody-pass');
        self::assertSame(401, $this->dispatch('loginOnly', null)->getStatusCode());
        self::assertSame(200, $this->dispatch('loginOnly', $nobody)->getStatusCode());
    }

    public function test_no_need_login_never_injects_an_admin_context(): void
    {
        $response = $this->dispatch('open', null);
        self::assertSame(200, $response->getStatusCode());
        self::assertNull(json_decode($response->rawBody(), true)['data']['admin']);
    }
}
