<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\controller\SystemAdmin;
use app\admin\authorization\model\AdminDepartment;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\PermissionResource;
use PHPUnit\Framework\TestCase;
use support\Context;
use support\Db;
use support\Request;

/**
 * 管理员列表批量加载角色与任职部门：输出须与逐行查询的旧实现完全一致，
 * 且查询次数为常量，不随行数增长。
 */
final class AdminListBatchLoadingTest extends TestCase
{
    use \Tests\Support\RefreshCoreDatabase;

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        foreach (['casbin_rule', 'auth_group_inherit', 'auth_group_department', 'admin_department', 'admin_auth_tokens', 'browser_sessions', 'department', 'auth_group', 'admin'] as $table) {
            Db::table($table)->delete();
        }
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
        Context::destroy();
    }

    public function test_role_ids_by_admins_matches_the_per_admin_enforcer_oracle(): void
    {
        $service = new CasbinService();
        $ids = array_merge(range(1, 23), [999, 0, -3]);
        foreach ([null, 'tenant'] as $domain) {
            $batched = $service->roleIdsByAdmins($ids, $domain);
            $expected = [];
            foreach (array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0))) as $id) {
                $expected[$id] = $this->legacyRoleIds($id, $domain);
            }
            self::assertSame($expected, $batched, 'domain ' . ($domain ?? 'default'));
            foreach ($ids as $id) {
                self::assertSame($this->legacyRoleIds($id, $domain), $service->adminRoleIds($id, $domain), "admin {$id}");
            }
        }
        // 非空且保留写入顺序，停用/软删/不存在的角色被过滤。
        self::assertSame([13, 10], $service->roleIdsByAdmins([4])[4]);
        self::assertSame([10], $service->roleIdsByAdmins([6])[6]);
        self::assertSame([11], $service->roleIdsByAdmins([5])[5]);
        self::assertSame([12], $service->roleIdsByAdmins([4], 'tenant')[4]);
        self::assertSame([], $service->roleIdsByAdmins([]));
    }

    public function test_admin_ids_by_roles_matches_the_per_role_oracle(): void
    {
        $service = new CasbinService();
        foreach ([[10], [11, 13], [13, 10, 11], [31, 40], [999], [], [50, 1]] as $roleIds) {
            foreach ([null, 'tenant'] as $domain) {
                self::assertEqualsCanonicalizing($this->legacyAdminIdsByRoles($roleIds, $domain), $service->adminIdsByRoles($roleIds, $domain));
            }
        }
        self::assertTrue($service->roleHasAdmins(10));
        self::assertFalse($service->roleHasAdmins(12));
        self::assertTrue($service->roleHasAdmins(12, 'tenant'));
    }

    public function test_super_admin_list_matches_the_per_row_output(): void
    {
        $list = $this->listAs(1, 20);
        self::assertCount(20, $list);
        self::assertSame(range(1, 20), array_column($list, 'id'));
        foreach ($list as $row) {
            self::assertSame($this->legacyRow((int) $row['id']), $row, 'admin ' . $row['id']);
        }
        // 显式锚点，防止 oracle 与实现同时出错。
        self::assertSame(['id' => 4, 'isSuper' => false, 'username' => 'u4', 'nickname' => 'Name 4', 'email' => 'u4@example.com', 'mobile' => '1380000004', 'status' => 1, 'deptId' => 0, 'departmentIds' => [], 'roleIds' => [13, 10], 'createdAt' => '2026-01-04 08:00:00', 'updatedAt' => ''], $list[3]);
        self::assertSame([7, 8], $list[14]['departmentIds']); // admin 15: 主部门 9 在关系表中重复出现，被过滤
        self::assertSame(9, $list[14]['deptId']);
        self::assertSame([7], $list[5]['departmentIds']);  // admin 6: 关系表中的主部门 8 被过滤
        self::assertTrue($list[0]['isSuper']);
        self::assertSame([1], $list[0]['roleIds']);
    }

    public function test_non_super_list_matches_the_per_row_output(): void
    {
        $list = $this->listAs(22, 100);
        self::assertSame([5, 6, 9, 12, 13, 17, 18, 21], array_column($list, 'id'));
        foreach ($list as $row) {
            self::assertSame($this->legacyRow((int) $row['id']), $row, 'admin ' . $row['id']);
        }
    }

    public function test_detail_matches_the_per_row_output(): void
    {
        $this->actAs(1);
        $body = json_decode((new SystemAdmin())->detail(15)->rawBody(), true);
        self::assertSame($this->legacyRow(15), $body['data']);
    }

    public function test_super_admin_list_uses_a_constant_number_of_queries(): void
    {
        $five = $this->countQueries(fn (): array => $this->listAs(1, 5));
        $twenty = $this->countQueries(fn (): array => $this->listAs(1, 20));
        // 分页 count + 列表 select + 角色(有效角色 + casbin_rule) + 任职部门，各 1 次。
        self::assertSame(5, $twenty);
        self::assertSame($five, $twenty);
    }

    public function test_non_super_list_uses_a_constant_number_of_queries(): void
    {
        $three = $this->countQueries(fn (): array => $this->listAs(22, 3));
        $all = $this->countQueries(fn (): array => $this->listAs(22, 100));
        self::assertSame($three, $all);
        self::assertSame(24, $all);
    }

    private function countQueries(callable $callback): int
    {
        $count = 0;
        $listening = true;
        Db::connection()->listen(static function () use (&$count, &$listening): void {
            if ($listening) $count++;
        });
        $callback();
        $listening = false;
        return $count;
    }

    private function listAs(int $adminId, int $pageSize): array
    {
        $this->actAs($adminId, "?page=1&pageSize={$pageSize}");
        $body = json_decode((new SystemAdmin())->index()->rawBody(), true);
        self::assertSame(200, $body['code'], (string) ($body['msg'] ?? ''));
        return $body['data']['list'];
    }

    private function actAs(int $adminId, string $query = ''): void
    {
        $request = new Request("GET /admin/system/user{$query} HTTP/1.1\r\nHost: x\r\n\r\n");
        $request->context['admin'] = ['id' => $adminId];
        Context::set(\Webman\Http\Request::class, $request);
    }

    /** 旧实现：逐行读取任职部门，逐行构建 enforcer 读取直接角色。 */
    private function legacyRow(int $adminId): array
    {
        $admin = Db::table('admin')->where('id', $adminId)->first();
        $departmentIds = array_values(array_unique(array_filter(array_merge(
            [(int) $admin->dept_id],
            array_map('intval', AdminDepartment::where('admin_id', $adminId)->pluck('dept_id')->all())
        ))));
        return [
            'id' => $adminId,
            'isSuper' => $adminId === 1,
            'username' => (string) $admin->username,
            'nickname' => (string) $admin->real_name,
            'email' => (string) $admin->email,
            'mobile' => (string) $admin->mobile,
            'status' => (int) $admin->status,
            'deptId' => (int) $admin->dept_id,
            'departmentIds' => array_values(array_filter($departmentIds, static fn (int $id): bool => $id !== (int) $admin->dept_id)),
            'roleIds' => $this->legacyRoleIds($adminId, null),
            'createdAt' => (string) ($admin->created_at ?? ''),
            'updatedAt' => (string) ($admin->updated_at ?? ''),
        ];
    }

    private function legacyRoleIds(int $adminId, ?string $domain): array
    {
        if ($adminId <= 0) return [];
        $roles = (new CasbinService())->enforcer($domain)->getRolesForUserInDomain(PermissionResource::subject($adminId), PermissionResource::domain($domain)) ?? [];
        $ids = [];
        foreach ($roles as $role) {
            if (str_starts_with($role, 'role:')) $ids[] = (int) substr($role, 5);
        }
        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    private function legacyAdminIdsByRoles(array $roleIds, ?string $domain): array
    {
        $ids = [];
        foreach ($roleIds as $roleId) {
            foreach (Db::table('casbin_rule')->where('ptype', 'g')->where('v1', 'role:' . $roleId)->where('v2', PermissionResource::domain($domain))->pluck('v0') as $subject) {
                if (str_starts_with($subject, 'admin:')) $ids[] = (int) substr($subject, 6);
            }
        }
        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    private function seedFixtures(): void
    {
        $now = '2026-01-01 00:00:00';
        Db::table('department')->insert([
            ['id' => 7, 'pid' => 0, 'name' => 'Sales', 'status' => 1],
            ['id' => 8, 'pid' => 7, 'name' => 'Sales East', 'status' => 1],
            ['id' => 9, 'pid' => 0, 'name' => 'Ops', 'status' => 1],
        ]);
        Db::table('auth_group')->insert([
            ['id' => 1, 'pid' => 0, 'name' => '超级管理员', 'code' => 'super_admin', 'level' => 1, 'data_scope' => 'all', 'status' => 1],
            ['id' => 50, 'pid' => 0, 'name' => 'manager', 'code' => 'manager', 'level' => 5, 'data_scope' => 'dept', 'status' => 1],
            ['id' => 10, 'pid' => 50, 'name' => 'editor', 'code' => 'editor', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 11, 'pid' => 50, 'name' => 'auditor', 'code' => 'auditor', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 12, 'pid' => 50, 'name' => 'parent', 'code' => 'parent', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 13, 'pid' => 12, 'name' => 'child', 'code' => 'child', 'level' => 20, 'data_scope' => 'self', 'status' => 1],
            ['id' => 31, 'pid' => 50, 'name' => 'disabled', 'code' => 'disabled', 'level' => 10, 'data_scope' => 'self', 'status' => 0],
            ['id' => 40, 'pid' => 50, 'name' => 'deleted', 'code' => 'deleted', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
        ]);
        Db::table('auth_group')->where('id', 40)->update(['deleted_at' => $now]);

        $casbin = new CasbinService();
        Db::table('admin')->insert(['id' => 1, 'username' => 'root', 'real_name' => 'Root', 'password' => 'x', 'dept_id' => 0, 'status' => 1, 'created_at' => '2026-01-01 08:00:00']);
        $casbin->syncAdminRoles(1, [1]);
        for ($id = 2; $id <= 21; $id++) {
            Db::table('admin')->insert([
                'id' => $id, 'username' => 'u' . $id, 'real_name' => 'Name ' . $id, 'password' => 'x',
                'email' => 'u' . $id . '@example.com', 'mobile' => sprintf('13800000%02d', $id),
                'dept_id' => [0, 7, 8, 9][$id % 4], 'status' => $id % 7 === 0 ? 0 : 1,
                'created_at' => sprintf('2026-01-%02d 08:00:00', $id),
                'updated_at' => $id % 5 === 0 ? sprintf('2026-02-%02d 09:30:00', $id) : null,
            ]);
            $departments = [];
            if ($id % 3 === 0) $departments = array_merge($departments, [8, 7]);
            if ($id % 5 === 0) $departments[] = 9;
            if ($id % 2 === 1) $departments[] = [0, 7, 8, 9][$id % 4]; // 与主部门重复
            foreach (array_unique(array_filter($departments)) as $departmentId) {
                Db::table('admin_department')->insert(['admin_id' => $id, 'dept_id' => $departmentId, 'created_at' => $now]);
            }
            $casbin->syncAdminRoles($id, match ($id % 4) {
                0 => [13, 10],
                1 => [11],
                2 => [10, 31, 40],
                default => [],
            });
            if ($id % 2 === 0) $casbin->syncAdminRoles($id, [12], 'tenant');
        }
        $casbin->syncAdminRoles(5, [11, 999]);
        // 非超管观察者：manager 角色，按部门 7 限定数据范围。
        Db::table('admin')->insert(['id' => 22, 'username' => 'manager', 'password' => 'x', 'dept_id' => 7, 'status' => 1, 'created_at' => '2026-01-22 08:00:00']);
        $casbin->syncAdminRoles(22, [50]);
        // 无角色、无部门的管理员。
        Db::table('admin')->insert(['id' => 23, 'username' => 'idle', 'password' => 'x', 'dept_id' => 0, 'status' => 1, 'created_at' => '2026-01-23 08:00:00']);
    }
}
