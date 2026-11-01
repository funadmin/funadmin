<?php

declare(strict_types=1);

namespace Tests;

use app\service\ManagementService;
use app\service\AuthorizationService;
use app\service\AuthService;
use app\service\MigrationService;
use DomainException;
use PHPUnit\Framework\TestCase;
use support\Db;

final class ManagementTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertTrue(class_exists(ManagementService::class), '后台管理服务尚未实现');
        (new MigrationService())->run();
        foreach (['admin_roles', 'role_permissions', 'admin_tokens', 'admins', 'menus', 'roles', 'permissions'] as $table) {
            Db::table($table)->delete();
        }
    }

    public function test_super_admin_can_manage_roles_permissions_and_users_without_leaking_password(): void
    {
        $auth = new AuthService();
        $root = $auth->createAdmin('root_admin', 'a-long-test-password');
        Db::table('admins')->where('id', $root['id'])->update(['is_super_admin' => true]);
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
        $root = (new AuthService())->createAdmin('root_admin', 'a-long-test-password');
        Db::table('admins')->where('id', $root['id'])->update(['is_super_admin' => true]);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        (new ManagementService())->save($root['id'], 'users', $root['id'], ['status' => 0]);
    }

    public function test_invalid_permission_assignment_rolls_back_role_creation(): void
    {
        $root = (new AuthService())->createAdmin('root_admin', 'a-long-test-password');
        Db::table('admins')->where('id', $root['id'])->update(['is_super_admin' => true]);
        try {
            (new ManagementService())->save($root['id'], 'roles', null, ['name' => '错误角色', 'code' => 'invalid', 'permissionIds' => [999999]]);
            self::fail('无效授权不能保存');
        } catch (DomainException $error) {
            self::assertSame(422, $error->getCode());
        }
        self::assertSame(0, Db::table('roles')->count());
    }

    public function test_menu_visibility_follows_live_role_grants(): void
    {
        $root = (new AuthService())->createAdmin('root_admin', 'a-long-test-password');
        Db::table('admins')->where('id', $root['id'])->update(['is_super_admin' => true]);
        $service = new ManagementService();
        $permission = $service->save($root['id'], 'permissions', null, ['name' => '后台管理', 'code' => 'system:management:view']);
        $role = $service->save($root['id'], 'roles', null, ['name' => '管理查看', 'code' => 'viewer', 'permissionIds' => [$permission['id']]]);
        $admin = $service->save($root['id'], 'users', null, ['username' => 'viewer', 'password' => 'a-long-test-password', 'roleIds' => [$role['id']]]);
        $service->save($root['id'], 'menus', null, ['name' => '后台管理', 'path' => '/management', 'routeName' => 'WebmanManagement', 'permission' => 'system:management:view']);
        self::assertCount(1, (new AuthorizationService())->menus($admin));
        $service->save($root['id'], 'roles', $role['id'], ['status' => 0]);
        self::assertSame([], (new AuthorizationService())->menus($admin));
    }

    public function test_reserved_menu_route_cannot_replace_login(): void
    {
        $root = (new AuthService())->createAdmin('root_admin', 'a-long-test-password');
        Db::table('admins')->where('id', $root['id'])->update(['is_super_admin' => true]);
        $service = new ManagementService();
        $permission = $service->save($root['id'], 'permissions', null, ['name' => '查看', 'code' => 'test:view']);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        $service->save($root['id'], 'menus', null, ['name' => '错误路由', 'path' => '/login', 'routeName' => 'Login', 'permission' => $permission['code']]);
    }

    public function test_password_reset_revokes_existing_token(): void
    {
        $root = (new AuthService())->createAdmin('root_admin', 'a-long-test-password');
        Db::table('admins')->where('id', $root['id'])->update(['is_super_admin' => true]);
        $service = new ManagementService();
        $admin = $service->save($root['id'], 'users', null, ['username' => 'ordinary', 'password' => 'a-long-test-password']);
        $token = (new AuthService())->login('ordinary', 'a-long-test-password', 'reset-test')['access_token'];
        $service->save($root['id'], 'users', $admin['id'], ['password' => 'another-long-password']);
        $this->expectException(DomainException::class);
        (new AuthService())->authenticate($token);
    }

    public function test_bad_pagination_input_is_rejected(): void
    {
        $root = (new AuthService())->createAdmin('root_admin', 'a-long-test-password');
        Db::table('admins')->where('id', $root['id'])->update(['is_super_admin' => true]);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        (new ManagementService())->listing($root['id'], 'users', ['pageSize' => []]);
    }

    public function test_null_status_is_validation_error_not_database_error(): void
    {
        $root = (new AuthService())->createAdmin('root_admin', 'a-long-test-password');
        Db::table('admins')->where('id', $root['id'])->update(['is_super_admin' => true]);
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(422);
        (new ManagementService())->save($root['id'], 'roles', null, ['name' => '测试', 'code' => 'null_status', 'status' => null]);
    }

    public function test_anonymous_roleless_admin_has_no_management_access(): void
    {
        $admin = (new AuthService())->createAdmin('ordinary', 'a-long-test-password');
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        (new ManagementService())->listing($admin['id'], 'roles', []);
    }
}
