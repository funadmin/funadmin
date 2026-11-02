<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\ManagementService;
use app\admin\authorization\service\AuthorizationService;
use app\admin\authentication\service\AdminSessionService as AuthService;
use app\common\service\database\MigrationService;
use DomainException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ManagementTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'casbin_rule', 'admin_menu', 'permission', 'auth_group', 'admin'] as $table) {
            Db::table($table)->delete();
        }
        Db::table('admin')->insert(['id' => 1, 'username' => 'root_admin', 'password' => password_hash('a-long-test-password', PASSWORD_DEFAULT), 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 1, 'name' => '超级管理员', 'code' => 'super_admin', 'level' => 1, 'data_scope' => 'all', 'status' => 1]);
    }

    public function test_super_admin_can_manage_roles_permissions_and_users_without_leaking_password(): void
    {
        $root = ['id' => 1, 'username' => 'root_admin'];
        $service = new ManagementService();
        $permission = $service->save($root['id'], 'permissions', null, ['name' => '查看用户', 'code' => 'system:user:list']);
        $role = $service->save($root['id'], 'roles', null, ['name' => '只读角色', 'code' => 'reader', 'permissionIds' => [$permission['id']]]);
        $admin = $service->save($root['id'], 'users', null, ['username' => 'reader', 'password' => 'a-long-test-password', 'roleIds' => [$role['id']]]);
        self::assertArrayNotHasKey('password', $admin);
        self::assertSame(['system:user:list'], (new AuthorizationService())->userInfo($admin)['permissions']);
        self::assertSame(2, $service->listing($root['id'], 'users', [])['total']);
        self::assertSame(100, $service->listing($root['id'], 'users', ['pageSize' => '100000'])['pageSize']);
        self::assertSame(2, $service->listing($admin['id'], 'users', [])['total']);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        $service->save($admin['id'], 'users', $admin['id'], ['is_super_admin' => true]);
    }

    public function test_self_disable_and_super_admin_escalation_are_rejected(): void
    {
        $root = ['id' => 1, 'username' => 'root_admin'];
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        (new ManagementService())->save($root['id'], 'users', $root['id'], ['status' => 0]);
    }

    public function test_invalid_permission_assignment_rolls_back_role_creation(): void
    {
        $root = ['id' => 1, 'username' => 'root_admin'];
        try {
            (new ManagementService())->save($root['id'], 'roles', null, ['name' => '错误角色', 'code' => 'invalid', 'permissionIds' => [999999]]);
            self::fail('无效授权不能保存');
        } catch (DomainException $error) {
            self::assertSame(422, $error->getCode());
        }
        self::assertSame(0, Db::table('auth_group')->where('id', '<>', 1)->count());
    }

    public function test_menu_visibility_follows_live_role_grants(): void
    {
        $root = ['id' => 1, 'username' => 'root_admin'];
        $service = new ManagementService();
        $permission = $service->save($root['id'], 'permissions', null, ['name' => '后台管理', 'code' => 'system:management:view']);
        $role = $service->save($root['id'], 'roles', null, ['name' => '管理查看', 'code' => 'viewer', 'permissionIds' => [$permission['id']]]);
        $admin = $service->save($root['id'], 'users', null, ['username' => 'viewer', 'password' => 'a-long-test-password', 'roleIds' => [$role['id']]]);
        $service->save($root['id'], 'menus', null, ['name' => '后台管理', 'path' => '/management', 'routeName' => 'WebmanManagement', 'component' => 'webman/management', 'permissionId' => $permission['id']]);
        self::assertCount(1, (new AuthorizationService())->menus($admin));
        Db::table('auth_group')->where('id', $role['id'])->update(['status' => 0]);
        self::assertSame([], (new AuthorizationService())->menus($admin));
    }

    public function test_reserved_menu_route_cannot_replace_login(): void
    {
        $root = ['id' => 1, 'username' => 'root_admin'];
        $service = new ManagementService();
        $permission = $service->save($root['id'], 'permissions', null, ['name' => '查看', 'code' => 'test:view']);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        $service->save($root['id'], 'menus', null, ['name' => '错误路由', 'path' => '/login', 'routeName' => 'Login', 'permission' => $permission['code']]);
    }

    public function test_password_reset_revokes_existing_token(): void
    {
        $root = ['id' => 1, 'username' => 'root_admin'];
        $service = new ManagementService();
        $admin = $service->save($root['id'], 'users', null, ['username' => 'ordinary', 'password' => 'a-long-test-password']);
        $token = (new AuthService())->login('ordinary', 'a-long-test-password', 'reset-test')['access_token'];
        $service->save($root['id'], 'users', $admin['id'], ['password' => 'another-long-password']);
        $this->expectException(DomainException::class);
        (new AuthService())->authenticate($token);
    }

    public function test_bad_pagination_input_is_rejected(): void
    {
        $root = ['id' => 1, 'username' => 'root_admin'];
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        (new ManagementService())->listing($root['id'], 'users', ['pageSize' => []]);
    }

    public function test_null_status_is_validation_error_not_database_error(): void
    {
        $root = ['id' => 1, 'username' => 'root_admin'];
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        (new ManagementService())->save($root['id'], 'roles', null, ['name' => '测试', 'code' => 'null_status', 'status' => null]);
    }

    public function test_menu_form_protocol_round_trips_code_binding_and_component(): void
    {
        $service = new ManagementService();
        $permission = $service->save(1, 'permissions', null, ['name' => '查看', 'code' => 'demo:page:view', 'object' => 'demo/page', 'action' => 'view']);
        $menu = $service->save(1, 'menus', null, ['name' => '示例', 'path' => '/admin/demo-page', 'routeName' => 'DemoPage', 'component' => 'webman/management', 'permission' => $permission['code']]);
        self::assertSame('/admin/demo-page', $menu['path']);
        self::assertSame('DemoPage', $menu['routeName']);
        self::assertSame($permission['code'], $menu['permission']);
        self::assertSame($permission['id'], $menu['permissionId']);
    }

    public function test_partial_update_does_not_enable_a_disabled_user(): void
    {
        $service = new ManagementService();
        $user = $service->save(1, 'users', null, ['username' => 'disabled', 'password' => 'a-long-test-password', 'status' => 0]);
        $updated = $service->save(1, 'users', $user['id'], ['nickname' => '新昵称']);
        self::assertSame(0, (int) $updated['status']);
    }

    public function test_short_password_is_rejected_before_writing(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        (new ManagementService())->save(1, 'users', null, ['username' => 'shortpass', 'password' => 'short']);
    }

    public function test_changing_menu_permission_code_rebinds_the_existing_menu(): void
    {
        $service = new ManagementService();
        $first = $service->save(1, 'permissions', null, ['name' => '第一项', 'code' => 'demo:first:view', 'object' => 'demo/first', 'action' => 'view']);
        $second = $service->save(1, 'permissions', null, ['name' => '第二项', 'code' => 'demo:second:view', 'object' => 'demo/second', 'action' => 'view']);
        $menu = $service->save(1, 'menus', null, ['name' => '示例', 'path' => '/admin/demo-page', 'routeName' => 'DemoPage', 'component' => 'webman/management', 'permission' => $first['code']]);
        $updated = $service->save(1, 'menus', $menu['id'], ['permission' => $second['code']]);
        self::assertSame($second['id'], $updated['permissionId']);
    }

    public function test_menu_binding_rejects_disabled_permissions(): void
    {
        $service = new ManagementService();
        $permission = $service->save(1, 'permissions', null, ['name' => '停用', 'code' => 'demo:disabled:view', 'status' => 0]);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        $service->save(1, 'menus', null, ['name' => '示例', 'path' => '/admin/demo-page', 'routeName' => 'DemoPage', 'component' => 'webman/management', 'permissionId' => $permission['id']]);
    }

    public function test_partial_permission_update_preserves_its_resource_identity(): void
    {
        $service = new ManagementService();
        $permission = $service->save(1, 'permissions', null, ['name' => '能力', 'code' => 'demo:page:view', 'object' => 'demo/page', 'action' => 'view', 'resourceType' => 'capability']);
        $updated = $service->save(1, 'permissions', $permission['id'], ['name' => '改名']);
        self::assertSame('demo/page', $updated['object']);
        self::assertSame('view', $updated['action']);
        self::assertSame('capability', $updated['resourceType']);
    }

    public function test_anonymous_roleless_admin_has_no_management_access(): void
    {
        $admin = (new AuthService())->createAdmin('ordinary', 'a-long-test-password');
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        (new ManagementService())->listing($admin['id'], 'roles', []);
    }
}
