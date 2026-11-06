<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\controller\SystemRole;
use app\admin\authorization\service\RoleAuthorizationService;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\AuthorizationService;
use app\admin\authorization\model\AuthGroup;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class AuthorizationIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        foreach (['admin_menu', 'permission', 'permission_field', 'auth_group_field_permission', 'auth_group_inherit', 'auth_group_department', 'department', 'casbin_rule', 'admin_auth_tokens', 'browser_sessions', 'auth_group', 'admin'] as $table) {
            Db::table($table)->delete();
        }
        if ($this->name() === 'test_fresh_install_exposes_the_existing_authorization_pages') {
            (require dirname(__DIR__) . '/database/migrations/007_authorization_resources.php')();
        }
        Db::table('auth_group')->insert(['id' => 1, 'name' => '超级管理员', 'code' => 'super_admin', 'level' => 1, 'status' => 1]);
        Db::table('admin')->insert(['id' => 1, 'username' => 'root', 'password' => 'hidden', 'status' => 1]);
        $request = new Request("GET /admin/system/role HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 1];
        Context::set(\Webman\Http\Request::class, $request);
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_native_authorization_routes_match_the_frontend_protocol(): void
    {
        \Webman\Route::load([config_path()]);
        $paths = array_map(static fn ($route) => $route->getPath(), \Webman\Route::getRoutes());
        foreach (['/admin/system/role', '/admin/system/role/all', '/admin/system/role/parent-options', '/admin/system/role/{id:\d+}/authorization', '/admin/system/menu/tree', '/admin/system/permission/tree', '/admin/system/user'] as $path) {
            self::assertContains($path, $paths);
        }
    }

    public function test_empty_database_has_authorization_relations(): void
    {
        foreach (['department', 'auth_group_inherit', 'auth_group_department', 'permission_field', 'auth_group_field_permission'] as $table) {
            self::assertTrue(Db::schema()->hasTable($table), '缺少授权表：' . $table);
        }
        self::assertTrue(Db::schema()->hasColumn('permission', 'sort_order'));
    }

    public function test_role_creation_listing_and_authorization_preserve_capability(): void
    {
        $body = json_encode(['name' => '测试角色', 'code' => 'operator', 'level' => 100, 'dataScope' => 'self', 'parentId' => 0]);
        Context::set(\Webman\Http\Request::class, new Request("POST /admin/system/role HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body));
        request()->context['admin'] = ['id' => 1];
        $response = (new SystemRole())->create();
        $data = json_decode($response->rawBody(), true);
        self::assertSame(200, $data['code'], $data['msg']);
        $role = AuthGroup::find($data['data']['id']);
        Db::table('permission')->insert(['id' => 10, 'name' => '业务能力', 'code' => 'demo:read', 'obj' => 'demo', 'act' => 'read', 'resource_type' => 'capability', 'status' => 1]);
        (new RoleAuthorizationService())->save($role, ['permissionIds' => [10], 'fieldPermissions' => [], 'dataScope' => 'self', 'departmentIds' => []]);
        self::assertContains(10, (new RoleAuthorizationService())->detail($role)['effectivePermissionIds']);
        $list = json_decode((new SystemRole())->index()->rawBody(), true);
        self::assertSame(1, $list['data']['total']);
        Db::table('admin')->insert(['id' => 2, 'username' => 'operator', 'password' => 'hidden', 'status' => 1]);
        (new CasbinService())->syncAdminRoles(2, [$role->id]);
        self::assertContains('demo:read', (new AuthorizationService())->permissionCodes(2));
    }

    private function asActor(int $id, string $method = 'GET', array $payload = []): void
    {
        $body = json_encode($payload);
        $request = new Request("$method /admin/system/role HTTP/1.1\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $request->context['admin'] = ['id' => $id];
        Context::set(\Webman\Http\Request::class, $request);
    }

    public function test_permission_and_menu_crud_round_trip(): void
    {
        $this->asActor(1, 'POST', ['name' => '测试能力', 'object' => 'demo', 'action' => 'read', 'resourceType' => 'capability']);
        $controller = new \app\admin\authorization\controller\SystemPermission();
        $result = json_decode($controller->create()->rawBody(), true);
        self::assertSame(200, $result['code'], $result['msg']);
        $permissionId = $result['data']['id'];
        self::assertSame('测试能力', $result['data']['name']);
        self::assertNotEmpty(json_decode($controller->tree()->rawBody(), true)['data']);
        $this->asActor(1, 'POST', ['name' => '测试页面', 'path' => '/admin/demo', 'routeName' => 'Demo', 'component' => 'system/role/index', 'permissionId' => $permissionId]);
        $menus = new \app\admin\authorization\controller\SystemMenu();
        $result = json_decode($menus->create()->rawBody(), true);
        self::assertSame(200, $result['code'], $result['msg']);
        self::assertSame('测试页面', $result['data']['name']);
        self::assertNotEmpty(json_decode($menus->tree()->rawBody(), true)['data']);
        self::assertNotEmpty(json_decode($menus->permissionOptions()->rawBody(), true)['data']);
    }

    public function test_lower_role_cannot_grant_capability_or_fields_outside_operator_scope(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '上级', 'code' => 'parent', 'level' => 10, 'status' => 1]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'pid' => $parent->id, 'level' => 20, 'status' => 1]);
        Db::table('auth_group_inherit')->insert(['role_id' => $child->id, 'parent_role_id' => $parent->id]);
        Db::table('admin')->insert(['id' => 2, 'username' => 'operator', 'password' => 'hidden', 'status' => 1]);
        (new CasbinService())->syncAdminRoles(2, [$parent->id]);
        Db::table('permission')->insert(['id' => 10, 'name' => '未授权能力', 'code' => 'demo:read', 'obj' => 'demo', 'act' => 'read', 'resource_type' => 'capability', 'status' => 1]);
        $this->asActor(2);
        try {
            (new RoleAuthorizationService())->save($child, ['permissionIds' => [10], 'fieldPermissions' => [], 'dataScope' => 'self']);
            self::fail('不能越权分配能力');
        } catch (\InvalidArgumentException $error) { self::assertStringContainsString('范围', $error->getMessage()); }
        self::assertSame([], (new CasbinService())->rolePermissionIds($child->id));
    }

    public function test_administrator_create_reset_disable_and_list(): void
    {
        $role = AuthGroup::forceCreate(['name' => '操作员', 'code' => 'operator', 'level' => 10, 'status' => 1]);
        $this->asActor(1, 'POST', ['username' => 'test_operator', 'nickname' => '操作员', 'password' => 'operator-safe-password', 'roleIds' => [$role->id]]);
        $controller = new \app\admin\authorization\controller\SystemAdmin();
        $result = json_decode($controller->create()->rawBody(), true);
        self::assertSame(200, $result['code'], $result['msg']);
        $id = $result['data']['id'];
        self::assertArrayNotHasKey('password', $result['data']);
        $this->asActor(1);
        // 超管本人也会出现在列表中：新建的管理员 + 超管自己。
        self::assertSame(2, json_decode($controller->index()->rawBody(), true)['data']['total']);
        $service = new \app\admin\authentication\service\AdminSessionService();
        $token = $service->login('test_operator', 'operator-safe-password', 'reset-test')['access_token'];
        $this->asActor(1, 'POST', ['password' => 'new-operator-safe-password']);
        self::assertSame(200, json_decode($controller->resetPassword($id)->rawBody(), true)['code']);
        self::assertTrue(password_verify('new-operator-safe-password', Db::table('admin')->where('id', $id)->value('password')));
        $this->asActor(1, 'POST', ['status' => 0]);
        self::assertSame(200, json_decode($controller->status($id)->rawBody(), true)['code']);
        self::assertSame(0, (int) Db::table('admin')->where('id', $id)->value('status'));
    }

    public function test_disabled_ancestor_does_not_grant_inherited_permissions_or_fields(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '停用上级', 'code' => 'parent', 'level' => 10, 'status' => 0]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'pid' => $parent->id, 'level' => 20, 'status' => 1]);
        Db::table('auth_group_inherit')->insert(['role_id' => $child->id, 'parent_role_id' => $parent->id]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo', 'act' => 'read', 'resource_type' => 'capability', 'status' => 1]);
        Db::table('permission_field')->insert(['id' => 10, 'permission_id' => 10, 'name' => '字段', 'resource' => 'demo', 'field' => 'secret']);
        Db::table('auth_group_field_permission')->insert(['role_id' => $parent->id, 'field_id' => 10, 'can_view' => 1]);
        (new CasbinService())->syncRolePermissions($parent->id, [10]);
        self::assertSame([], (new CasbinService())->permissionIdsForRoles([$child->id]));
        self::assertSame([], (new RoleAuthorizationService())->readableFieldsForRoles([$child->id], 'demo'));
    }

    public function test_field_edit_grant_cannot_exceed_operator_and_copy_preserves_capability(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '上级', 'code' => 'parent', 'level' => 10, 'status' => 1]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'pid' => $parent->id, 'level' => 20, 'status' => 1]);
        $copy = AuthGroup::forceCreate(['name' => '复制目标', 'code' => 'copy', 'level' => 30, 'status' => 1]);
        Db::table('auth_group_inherit')->insert(['role_id' => $child->id, 'parent_role_id' => $parent->id]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo', 'act' => 'read', 'resource_type' => 'capability', 'status' => 1]);
        Db::table('permission_field')->insert(['id' => 10, 'permission_id' => 10, 'name' => '字段', 'resource' => 'demo', 'field' => 'secret']);
        Db::table('auth_group_field_permission')->insert(['role_id' => $parent->id, 'field_id' => 10, 'can_view' => 1]);
        (new CasbinService())->syncRolePermissions($parent->id, [10]);
        Db::table('admin')->insert(['id' => 2, 'username' => 'operator', 'password' => 'hidden', 'status' => 1]);
        (new CasbinService())->syncAdminRoles(2, [$parent->id]);
        $this->asActor(2);
        try {
            (new RoleAuthorizationService())->save($child, ['permissionIds' => [10], 'fieldPermissions' => [['fieldId' => 10, 'view' => true, 'edit' => true]], 'dataScope' => 'self']);
            self::fail('只能查看的字段不能授权编辑');
        } catch (\InvalidArgumentException $error) { self::assertStringContainsString('编辑', $error->getMessage()); }
        $this->asActor(1);
        (new RoleAuthorizationService())->save($child, ['permissionIds' => [10], 'fieldPermissions' => [['fieldId' => 10, 'view' => true, 'edit' => false]], 'dataScope' => 'self']);
        (new RoleAuthorizationService())->copy($copy, $child);
        $detail = (new RoleAuthorizationService())->detail($copy);
        self::assertContains(10, $detail['effectivePermissionIds']);
        self::assertTrue($detail['fields'][0]['view']);
        self::assertFalse($detail['fields'][0]['edit']);
    }

    public function test_login_permissions_include_frontend_aliases_without_granting_other_actions(): void
    {
        $role = AuthGroup::forceCreate(['name' => '查看者', 'code' => 'reader', 'level' => 10, 'status' => 1]);
        Db::table('admin')->insert(['id' => 2, 'username' => 'reader', 'password' => 'hidden', 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '角色列表', 'code' => 'systemrole:index', 'obj' => 'admin/systemrole', 'act' => 'index', 'resource_type' => 'route', 'status' => 1]);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$role->id]);
        $casbin->syncRolePermissions($role->id, [10]);
        $info = (new AuthorizationService())->userInfo(['id' => 2]);
        self::assertContains('system:role:list', $info['permissions']);
        self::assertNotContains('system:role:edit', $info['permissions']);
    }

    public function test_menu_validation_rejects_reserved_routes_and_names(): void
    {
        $this->asActor(1, 'POST', ['name' => '伪造登录', 'path' => '/admin/login', 'routeName' => 'Login', 'component' => 'system/role/index', 'type' => 'M']);
        $result = json_decode((new \app\admin\authorization\controller\SystemMenu())->create()->rawBody(), true);
        self::assertSame(422, $result['code']);
        self::assertSame(0, Db::table('admin_menu')->count());
    }

    public function test_public_and_inherited_capabilities_are_effective_without_wildcard_escalation(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '上级', 'code' => 'parent', 'level' => 10, 'status' => 1]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'pid' => $parent->id, 'level' => 20, 'status' => 1]);
        Db::table('auth_group_inherit')->insert(['role_id' => $child->id, 'parent_role_id' => $parent->id]);
        Db::table('admin')->insert(['id' => 2, 'username' => 'reader', 'password' => 'hidden', 'status' => 1]);
        Db::table('permission')->insert([
            ['id' => 10, 'name' => '继承能力', 'code' => 'demo:read', 'obj' => 'demo', 'act' => 'read', 'is_public' => 0, 'resource_type' => 'capability', 'status' => 1],
            ['id' => 11, 'name' => '公开能力', 'code' => 'demo:public', 'obj' => 'demo', 'act' => 'public', 'is_public' => 1, 'resource_type' => 'capability', 'status' => 1],
        ]);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$child->id]);
        $casbin->syncRolePermissions($parent->id, [10]);
        $codes = (new AuthorizationService())->permissionCodes(2);
        self::assertContains('demo:read', $codes);
        self::assertContains('demo:public', $codes);
        self::assertNotContains('*', $codes);
    }

    public function test_resource_registry_registers_and_removes_owned_capabilities(): void
    {
        $registry = new \app\admin\authorization\service\ResourceRegistryService();
        $registry->registerPermissions([['code' => 'demo:page:view', 'obj' => 'demo/page', 'act' => 'view', 'name' => '测试页面']], 'plugin', 'demo');
        self::assertSame(1, Db::table('permission')->where('source_name', 'demo')->count());
        $registry->removePermissions('plugin', 'demo');
        self::assertSame(0, Db::table('permission')->where('source_name', 'demo')->whereNull('deleted_at')->count());
    }

    public function test_fresh_install_exposes_the_existing_authorization_pages(): void
    {
        foreach (['system/role/index', 'system/user/index', 'system/menu/index', 'system/permission/index', 'system/dept/index'] as $component) {
            self::assertTrue(Db::table('admin_menu')->where('query', 'like', '%' . rawurlencode($component) . '%')->exists(), $component);
        }
    }

    public function test_node_authorization_uses_webman_identity_and_observes_revocation(): void
    {
        $role = AuthGroup::forceCreate(['name' => '查看者', 'code' => 'reader', 'level' => 10, 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability', 'status' => 1]);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$role->id]);
        $casbin->syncRolePermissions($role->id, [10]);
        $this->asActor(2);
        $authorization = new \app\admin\authorization\service\AdminAuthorizationService();
        self::assertTrue($authorization->nodeAccess('demo/page/read'));
        self::assertFalse($authorization->nodeAccess('demo/page/write'));
        $casbin->syncRolePermissions($role->id, []);
        self::assertFalse($authorization->nodeAccess('demo/page/read'));
        $this->asActor(0);
        self::assertFalse($authorization->nodeAccess('demo/page/read'));
    }

    public function test_node_authorization_does_not_keep_a_previous_request_identity(): void
    {
        $authorization = new \app\admin\authorization\service\AdminAuthorizationService();
        self::assertTrue($authorization->nodeAccess('admin/systemrole/index'));
        $this->asActor(0);
        self::assertFalse($authorization->nodeAccess('admin/systemrole/index'));
    }

    public function test_administrator_read_requires_explicit_permission(): void
    {
        Db::table('admin')->insert(['id' => 2, 'username' => 'reader', 'password' => 'hidden', 'status' => 1]);
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(403);
        (new AuthorizationService())->requirePermission(2, 'system:user:list');
    }

    public function test_native_role_write_rejects_missing_csrf(): void
    {
        $password = 'management-safe-password';
        Db::table('admin')->where('id', 1)->update(['password' => password_hash($password, PASSWORD_DEFAULT)]);
        $auth = new \app\admin\authentication\service\BrowserAuthService();
        $session = $auth->bootstrap('');
        $login = $auth->login($session['cookie'], $session['csrfToken'], 'root', $password, 'management-test');
        $body = json_encode(['name' => '拒绝写入', 'code' => 'csrf_denied']);
        $request = new Request("POST /admin/system/role HTTP/1.1\r\nCookie: " . $auth::COOKIE . '=' . $login['cookie'] . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
        $response = (new \app\admin\middleware\CheckAdminApiCsrfMiddleware())->process($request, static function (): \support\Response {
            self::fail('缺少 CSRF 时不得执行写入');
        });
        self::assertSame(403, json_decode($response->rawBody(), true)['code']);
        self::assertSame(0, Db::table('auth_group')->where('code', 'csrf_denied')->count());
    }

    public function test_reserved_role_does_not_implicitly_grant_all_fields(): void
    {
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo', 'act' => 'read', 'resource_type' => 'capability']);
        Db::table('permission_field')->insert(['id' => 10, 'permission_id' => 10, 'name' => '敏感字段', 'resource' => 'demo', 'field' => 'secret']);
        self::assertSame([], (new RoleAuthorizationService())->readableFieldsForRoles([1], 'demo'));
    }

    public function test_primary_parent_permissions_match_node_and_login_authorization(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '上级', 'code' => 'parent', 'level' => 10, 'status' => 1]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'pid' => $parent->id, 'level' => 20, 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo', 'act' => 'read', 'resource_type' => 'capability']);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$child->id]);
        $casbin->syncRolePermissions($parent->id, [10]);
        self::assertContains('demo:read', (new AuthorizationService())->permissionCodes(2));
        self::assertContains(10, (new \app\admin\authorization\service\RoleScopeService())->permissionIdsForRoles([$child->id]));
    }

    public function test_authorization_uses_the_official_casbin_engine(): void
    {
        self::assertTrue(is_subclass_of(\app\admin\authorization\repository\casbin\DatabaseAdapter::class, \Casbin\Persist\Adapter::class));
        self::assertInstanceOf(\Casbin\Enforcer::class, (new CasbinService())->enforcer());
        self::assertFileDoesNotExist(dirname(__DIR__) . '/app/admin/authorization/repository/casbin/ThinkAdapter.php');
    }

    public function test_casbin_role_links_drive_node_and_login_permissions(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '上级', 'code' => 'parent', 'level' => 10, 'status' => 1]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'level' => 20, 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability']);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$child->id]);
        $casbin->syncRoleInheritance($child->id, [$parent->id]);
        $casbin->syncRolePermissions($parent->id, [10]);
        self::assertTrue($casbin->enforceAdmin(2, 'demo/page', 'read'));
        self::assertContains('demo:read', (new AuthorizationService())->permissionCodes(2));
        self::assertContains(10, (new \app\admin\authorization\service\RoleScopeService())->permissionIdsForRoles([$child->id]));
        $casbin->syncRoleInheritance($child->id, []);
        self::assertFalse($casbin->enforceAdmin(2, 'demo/page', 'read'));
    }

    public function test_independent_authorizers_observe_revocation_and_transaction_rollback(): void
    {
        $role = AuthGroup::forceCreate(['name' => '查看者', 'code' => 'reader', 'level' => 10, 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability']);
        $writer = new CasbinService();
        $reader = new CasbinService();
        $writer->syncAdminRoles(2, [$role->id]);
        $writer->syncRolePermissions($role->id, [10]);
        self::assertTrue($reader->enforceAdmin(2, 'demo/page', 'read'));
        Db::beginTransaction();
        $writer->syncRolePermissions($role->id, []);
        self::assertFalse($reader->enforceAdmin(2, 'demo/page', 'read'));
        Db::rollBack();
        self::assertTrue($reader->enforceAdmin(2, 'demo/page', 'read'));
        $writer->syncRolePermissions($role->id, []);
        self::assertFalse($reader->enforceAdmin(2, 'demo/page', 'read'));
    }

    public function test_native_controller_cannot_modify_reserved_administrator(): void
    {
        $this->asActor(1, 'POST', ['status' => 0]);
        $response = (new \app\admin\authorization\controller\SystemAdmin())->status(1);
        self::assertSame(403, json_decode($response->rawBody(), true)['code']);
        self::assertSame(1, (int) Db::table('admin')->where('id', 1)->value('status'));
    }

    public function test_super_administrator_is_listed_and_flagged(): void
    {
        $role = AuthGroup::forceCreate(['name' => '操作员', 'code' => 'operator', 'level' => 10, 'status' => 1]);
        Db::table('admin')->insert(['id' => 2, 'username' => 'operator', 'password' => 'hidden', 'status' => 1]);
        (new CasbinService())->syncAdminRoles(2, [$role->id]);
        $this->asActor(1);
        $controller = new \app\admin\authorization\controller\SystemAdmin();
        $rows = json_decode($controller->index()->rawBody(), true)['data']['list'];
        self::assertSame([1, 2], array_column($rows, 'id'));
        self::assertTrue($rows[0]['isSuper']);
        self::assertFalse($rows[1]['isSuper']);
        self::assertSame(200, json_decode($controller->detail(1)->rawBody(), true)['code']);
    }

    public function test_lower_administrator_list_never_includes_super_administrator(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '上级', 'code' => 'parent', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'pid' => $parent->id, 'level' => 20, 'status' => 1]);
        Db::table('admin')->insert([['id' => 2, 'username' => 'manager', 'password' => 'hidden', 'status' => 1], ['id' => 3, 'username' => 'staff', 'password' => 'hidden', 'status' => 1]]);
        (new CasbinService())->syncAdminRoles(2, [$parent->id]);
        (new CasbinService())->syncAdminRoles(3, [$child->id]);
        $this->asActor(2);
        $controller = new \app\admin\authorization\controller\SystemAdmin();
        self::assertSame([3], array_column(json_decode($controller->index()->rawBody(), true)['data']['list'], 'id'));
        self::assertSame(404, json_decode($controller->detail(1)->rawBody(), true)['code']);
        $this->asActor(2, 'PUT', ['nickname' => '越权修改']);
        self::assertSame(403, json_decode($controller->update(1)->rawBody(), true)['code']);
        self::assertNotSame('越权修改', Db::table('admin')->where('id', 1)->value('real_name'));
    }

    public function test_super_administrator_edits_own_profile_but_keeps_reserved_role_and_status(): void
    {
        Db::table('department')->insert(['id' => 1, 'name' => '总部', 'pid' => 0, 'status' => 1]);
        (new CasbinService())->syncAdminRoles(1, [1]);
        $this->asActor(1, 'PUT', ['nickname' => '超级管理员', 'email' => 'root@example.com', 'mobile' => '13800000000', 'deptId' => 1, 'status' => 0, 'roleIds' => []]);
        $response = json_decode((new \app\admin\authorization\controller\SystemAdmin())->update(1)->rawBody(), true);
        self::assertSame(200, $response['code'], $response['msg']);
        $row = Db::table('admin')->where('id', 1)->first();
        self::assertSame('超级管理员', $row->real_name);
        self::assertSame('root@example.com', $row->email);
        self::assertSame(1, (int) $row->status);
        self::assertSame([1], (new \app\admin\authorization\service\RoleScopeService())->adminRoleIds(1));
    }

    public function test_super_administrator_resets_own_password(): void
    {
        $this->asActor(1, 'POST', ['password' => 'replacement-safe-password']);
        $response = json_decode((new \app\admin\authorization\controller\SystemAdmin())->resetPassword(1)->rawBody(), true);
        self::assertSame(200, $response['code'], $response['msg']);
        self::assertTrue(password_verify('replacement-safe-password', Db::table('admin')->where('id', 1)->value('password')));
    }

    public function test_super_administrator_cannot_be_deleted(): void
    {
        $this->asActor(1, 'DELETE', ['ids' => [1]]);
        $response = json_decode((new \app\admin\authorization\controller\SystemAdmin())->delete()->rawBody(), true);
        self::assertSame(403, $response['code']);
        self::assertSame(1, Db::table('admin')->where('id', 1)->count());
    }

    public function test_role_workspace_can_still_find_disabled_roles(): void
    {
        $role = AuthGroup::forceCreate(['name' => '停用角色', 'code' => 'disabled', 'level' => 10, 'status' => 0]);
        $rows = json_decode((new SystemRole())->all()->rawBody(), true)['data'];
        self::assertContains((int) $role->id, array_column($rows, 'id'));
    }

    public function test_native_password_reset_revokes_existing_tokens(): void
    {
        Db::table('admin')->insert(['id' => 2, 'username' => 'reader', 'password' => password_hash('original-safe-password', PASSWORD_DEFAULT), 'status' => 1]);
        $role = AuthGroup::forceCreate(['name' => '查看者', 'code' => 'reader', 'level' => 10, 'status' => 1]);
        (new CasbinService())->syncAdminRoles(2, [$role->id]);
        $auth = new \app\admin\authentication\service\AdminSessionService();
        $token = $auth->login('reader', 'original-safe-password', 'management-reset')['access_token'];
        $this->asActor(1, 'POST', ['password' => 'replacement-safe-password']);
        $response = (new \app\admin\authorization\controller\SystemAdmin())->resetPassword(2);
        self::assertSame(200, json_decode($response->rawBody(), true)['code']);
        self::assertSame(0, Db::table('admin_auth_tokens')->where('admin_id', 2)->count());
        $this->expectException(\DomainException::class);
        $auth->authenticate($token);
    }

    public function test_resource_registry_tree_is_idempotent_and_binds_owned_capability(): void
    {
        $registry = new \app\admin\authorization\service\ResourceRegistryService();
        $registry->registerPermissions([['code' => 'demo:page:view', 'obj' => 'demo/page', 'act' => 'view']], 'plugin', 'demo');
        $tree = [['name' => '示例目录', 'href' => '/admin/demo', 'query' => 'name=DemoRoot&type=M&component=Layout', 'menulist' => [
            ['name' => '示例页面', 'href' => 'page', 'permission' => 'demo:page:view', 'query' => 'name=DemoPage&type=C&component=system/role/index'],
        ]]];
        $registry->registerTree($tree, sourceType: 'plugin', sourceName: 'demo');
        $registry->registerTree($tree, sourceType: 'plugin', sourceName: 'demo');
        self::assertSame(2, Db::table('admin_menu')->where('source_name', 'demo')->count());
        $page = Db::table('admin_menu')->where('href', 'page')->first();
        self::assertSame((int) Db::table('permission')->where('code', 'demo:page:view')->value('id'), (int) $page->permission_id);
        self::assertGreaterThan(0, (int) $page->pid);
    }

    public function test_request_authorization_rejects_unauthenticated_even_when_flag_is_true(): void
    {
        $this->asActor(0);
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(401);
        (new \app\admin\authorization\service\AdminAuthorizationService())->roleAccess(true);
    }

    public function test_request_authorization_uses_matched_resource_and_live_identity(): void
    {
        $role = AuthGroup::forceCreate(['name' => '查看者', 'code' => 'reader', 'level' => 10, 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '查看角色', 'code' => 'systemrole:index', 'obj' => 'admin/systemrole', 'act' => 'index', 'resource_type' => 'route']);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$role->id]);
        $casbin->syncRolePermissions($role->id, [10]);
        $this->asActor(2);
        request()->controller = SystemRole::class;
        request()->action = 'index';
        $authorization = new \app\admin\authorization\service\AdminAuthorizationService();
        self::assertTrue($authorization->roleAccess());
        request()->action = 'create';
        $this->expectException(\DomainException::class);
        $this->expectExceptionCode(403);
        $authorization->roleAccess();
    }

    public function test_casbin_domains_do_not_share_membership_or_business_inheritance(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '上级', 'code' => 'parent', 'level' => 10, 'status' => 1]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'pid' => $parent->id, 'level' => 20, 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability']);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$child->id]);
        $casbin->syncRolePermissions($parent->id, [10]);
        $casbin->syncAdminRoles(2, [$child->id], 'other');
        $casbin->syncRolePermissions($parent->id, [10], 'other');
        self::assertTrue($casbin->enforceAdmin(2, 'demo/page', 'read'));
        self::assertFalse($casbin->enforceAdmin(2, 'demo/page', 'read', 'other'));
        $casbin->syncRoleInheritance($child->id, [$parent->id], 'other');
        self::assertTrue($casbin->enforceAdmin(2, 'demo/page', 'read', 'other'));
        self::assertFalse($casbin->enforcer('other')->enforce('admin:2', 'default', 'demo/page', 'read'));
        $casbin->syncRolePermissions($parent->id, [], 'other');
        self::assertFalse($casbin->enforceAdmin(2, 'demo/page', 'read', 'other'));
        self::assertTrue($casbin->enforceAdmin(2, 'demo/page', 'read'));
    }

    public function test_casbin_does_not_promote_permission_tree_ancestors_to_grants(): void
    {
        $role = AuthGroup::forceCreate(['name' => '查看者', 'code' => 'reader', 'level' => 10, 'status' => 1]);
        Db::table('permission')->insert([
            ['id' => 10, 'pid' => 0, 'name' => '写入', 'code' => 'demo:write', 'obj' => 'demo/page', 'act' => 'write', 'resource_type' => 'capability'],
            ['id' => 11, 'pid' => 10, 'name' => '查看', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability'],
        ]);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$role->id]);
        $casbin->syncRolePermissions($role->id, [11]);
        self::assertSame([11], $casbin->rolePermissionIds($role->id));
        self::assertFalse($casbin->enforceAdmin(2, 'demo/page', 'write'));
        self::assertTrue($casbin->enforceAdmin(2, 'DEMO/PAGE', 'READ'));
        self::assertNotContains('demo:write', (new AuthorizationService())->permissionCodes(2));
    }

    public function test_disabled_role_keeps_direct_grants_for_the_workspace_but_cannot_authorize(): void
    {
        $role = AuthGroup::forceCreate(['name' => '查看者', 'code' => 'reader', 'level' => 10, 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability']);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$role->id]);
        $casbin->syncRolePermissions($role->id, [10]);
        Db::table('auth_group')->where('id', $role->id)->update(['status' => 0]);
        self::assertFalse($casbin->enforceAdmin(2, 'demo/page', 'read'));
        self::assertSame([], $casbin->permissionIdsForRoles([$role->id]));
        self::assertSame([10], $casbin->rolePermissionIds($role->id));
    }

    public function test_casbin_denies_disabled_inheritance_bridges_and_deleted_resources(): void
    {
        $parent = AuthGroup::forceCreate(['name' => '上级', 'code' => 'parent', 'level' => 10, 'status' => 1]);
        $bridge = AuthGroup::forceCreate(['name' => '中间角色', 'code' => 'bridge', 'pid' => $parent->id, 'level' => 20, 'status' => 1]);
        $child = AuthGroup::forceCreate(['name' => '下级', 'code' => 'child', 'pid' => $bridge->id, 'level' => 30, 'status' => 1]);
        Db::table('permission')->insert(['id' => 10, 'name' => '能力', 'code' => 'demo:read', 'obj' => 'demo/page', 'act' => 'read', 'resource_type' => 'capability']);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(2, [$child->id]);
        $casbin->syncRolePermissions($parent->id, [10]);
        self::assertTrue($casbin->enforceAdmin(2, 'demo/page', 'read'));
        Db::table('auth_group')->where('id', $bridge->id)->update(['status' => 0]);
        self::assertFalse($casbin->enforceAdmin(2, 'demo/page', 'read'));
        Db::table('auth_group')->where('id', $bridge->id)->update(['status' => 1]);
        Db::table('permission')->where('id', 10)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        self::assertFalse($casbin->enforceAdmin(2, 'demo/page', 'read'));
        self::assertSame([], $casbin->permissionIdsForRoles([$child->id]));
    }

    public function test_role_guard_rejects_self_inheritance(): void
    {
        $role = AuthGroup::forceCreate(['name' => '测试角色', 'code' => 'operator', 'level' => 10, 'status' => 1]);
        $this->expectException(\InvalidArgumentException::class);
        (new \app\admin\authorization\service\RoleGuardService())->assertInheritance($role->id, 10, [$role->id]);
    }
}
