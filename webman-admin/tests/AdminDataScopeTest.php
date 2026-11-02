<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\DataScopeService;
use app\admin\authorization\service\RoleScopeService;
use app\common\service\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AdminDataScopeTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        foreach (['fun_scope_records', 'fun_auth_group_department', 'fun_auth_group_inherit', 'fun_department'] as $table) {
            Db::connection()->getPdo()->exec('DROP TABLE IF EXISTS "' . $table . '"');
        }
        foreach (['permission', 'auth_group', 'casbin_rule', 'admin'] as $table) {
            if (Db::schema()->hasTable($table)) {
                Db::table($table)->delete();
            }
        }
        Db::schema()->create('department', static function (Blueprint $table): void {
            $table->id(); $table->integer('pid')->default(0); $table->string('name');
            $table->boolean('status')->default(true); $table->timestamps(); $table->softDeletes();
        });
        foreach (['auth_group_inherit' => ['role_id', 'parent_role_id'], 'auth_group_department' => ['role_id', 'dept_id']] as $name => $fields) {
            Db::schema()->create($name, static function (Blueprint $table) use ($fields): void { $table->id(); foreach ($fields as $field) $table->integer($field); });
        }
        foreach ([[1, 0], [2, 1], [3, 0]] as [$id, $pid]) Db::table('department')->insert(['id' => $id, 'pid' => $pid, 'name' => '部门' . $id]);
        foreach ([[1, 0], [2, 1], [3, 2], [4, 3]] as [$id, $dept]) Db::table('admin')->insert(['id' => $id, 'username' => 'admin' . $id, 'password' => 'unused', 'dept_id' => $dept, 'status' => 1]);
        Db::table('auth_group')->insert(['id' => 2, 'name' => '主管', 'code' => 'manager', 'data_scope' => 'dept_and_children']);
        Db::table('auth_group')->insert(['id' => 3, 'name' => '下级', 'code' => 'child', 'pid' => 2, 'data_scope' => 'self']);
        $this->rule('g', ['admin:2', 'role:2', 'default']);
        $request = new Request("GET /admin/test HTTP/1.1\r\n\r\n");
        $request->context['admin'] = ['id' => 2];
        Context::set(\Webman\Http\Request::class, $request);
    }

    protected function tearDown(): void { Context::destroy(); }

    public function test_request_identity_controls_role_scope_without_process_global_session(): void
    {
        $roles = new RoleScopeService();
        self::assertFalse($roles->isSuperAdmin());
        self::assertSame([2], $roles->currentRoleIds());
        self::assertSame([3], $roles->manageableRoleIds());
        request()->context['admin'] = ['id' => 1];
        self::assertTrue($roles->isSuperAdmin());
        self::assertEqualsCanonicalizing([2, 3], $roles->manageableRoleIds());
        unset(request()->context['admin']);
        self::assertFalse($roles->isSuperAdmin());
        self::assertSame([], $roles->currentRoleIds());
    }

    public function test_department_scope_includes_descendants_and_excludes_other_branches(): void
    {
        $scope = new DataScopeService();
        self::assertSame(['all' => false, 'adminId' => 2, 'departmentIds' => [1, 2]], $scope->resolve());
        self::assertEqualsCanonicalizing([2, 3], $scope->visibleAdminIds());
        self::assertSame([1, 2], $scope->departmentTreeIds(1));
        Db::table('department')->where('id', 2)->update(['status' => 0]);
        self::assertSame([1], $scope->departmentTreeIds(1));
        self::assertSame([1, 2], $scope->departmentSubtreeIds(1));
        request()->context['admin'] = ['id' => 1];
        self::assertTrue($scope->resolve()['all']);
        unset(request()->context['admin']);
        self::assertSame(['all' => false, 'adminId' => 0, 'departmentIds' => []], $scope->resolve());
    }

    public function test_scoped_or_condition_cannot_escape_existing_query_filters(): void
    {
        Db::schema()->create('scope_records', static function (Blueprint $table): void { $table->id(); $table->integer('admin_id'); $table->integer('dept_id'); $table->integer('tenant_id'); });
        foreach ([[2, 3, 1], [4, 2, 1], [4, 3, 1], [2, 1, 2]] as [$admin, $dept, $tenant]) Db::table('scope_records')->insert(['admin_id' => $admin, 'dept_id' => $dept, 'tenant_id' => $tenant]);
        $query = Db::table('scope_records')->where('tenant_id', 1);
        self::assertSame([1, 2], (new DataScopeService())->apply($query)->orderBy('id')->pluck('id')->all());
    }

    public function test_public_permission_ancestors_and_live_grants_are_resolved_without_stale_cache(): void
    {
        Db::table('permission')->insert(['id' => 10, 'pid' => 0, 'code' => null, 'name' => '目录', 'resource_type' => 'group']);
        Db::table('permission')->insert(['id' => 11, 'pid' => 10, 'code' => 'test:index', 'obj' => 'admin/test', 'act' => 'index', 'name' => '列表']);
        Db::table('permission')->insert(['id' => 12, 'pid' => 10, 'code' => 'test:public', 'obj' => 'admin/test', 'act' => 'options', 'name' => '选项', 'is_public' => 1]);
        $this->rule('p', ['role:2', 'default', 'admin/test', 'index']);
        $roles = new RoleScopeService();
        self::assertEqualsCanonicalizing([10, 11, 12], $roles->permissionIdsForRoles([2]));
        Db::table('permission')->where('id', 11)->update(['status' => 0]);
        self::assertEqualsCanonicalizing([10, 12], $roles->permissionIdsForRoles([2]));
        Db::table('permission')->where('id', 10)->update(['pid' => 12]);
        self::assertEqualsCanonicalizing([10, 12], $roles->permissionIdsForRoles([]));
    }

    public function test_disabled_roles_do_not_act_as_permission_inheritance_bridges(): void
    {
        Db::table('permission')->insert(['id' => 11, 'code' => 'test:index', 'obj' => 'admin/test', 'act' => 'index', 'name' => '列表']);
        Db::table('auth_group_inherit')->insert(['role_id' => 2, 'parent_role_id' => 3]);
        $this->rule('p', ['role:3', 'default', 'admin/test', 'index']);
        $roles = new RoleScopeService();
        self::assertSame([11], $roles->permissionIdsForRoles([2]));
        Db::table('auth_group')->where('id', 2)->update(['status' => 0]);
        self::assertSame([], $roles->permissionIdsForRoles([2]));
    }

    public function test_custom_departments_are_loaded_from_role_relations(): void
    {
        Db::table('auth_group')->where('id', 2)->update(['data_scope' => 'custom']);
        Db::table('auth_group_department')->insert(['role_id' => 2, 'dept_id' => 3]);
        self::assertSame([3], (new DataScopeService())->resolve()['departmentIds']);
    }

    private function rule(string $type, array $values): void
    {
        $row = ['ptype' => $type, 'rule_hash' => hash('sha256', $type . implode('|', $values))];
        foreach ($values as $index => $value) $row['v' . $index] = $value;
        Db::table('casbin_rule')->insert($row);
    }
}
