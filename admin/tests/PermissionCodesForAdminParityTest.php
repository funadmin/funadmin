<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\model\AuthGroup;
use app\admin\authorization\repository\casbin\DatabaseAdapter;
use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\PermissionResource;
use Casbin\Enforcer;
use Casbin\Rbac\DefaultRoleManager\DomainManager;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * permissionCodesForAdmin 必须与逐条 enforce() 的旧实现给出完全相同的权限码（含顺序），
 * 且一次调用只评估一次隐式权限，不再对每条权限调用 enforce()。
 */
final class PermissionCodesForAdminParityTest extends TestCase
{
    use \Tests\Support\RefreshCoreDatabase;

    /** @var array<string, list<string>> 每组权限的权限码 */
    private array $chunks = [];

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        Db::beginTransaction();
        foreach (['casbin_rule', 'auth_group_inherit', 'auth_group_department', 'admin_department', 'department', 'auth_group', 'admin'] as $table) {
            Db::table($table)->delete();
        }
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
    }

    public function test_codes_match_the_per_permission_enforce_oracle_for_every_fixture_admin(): void
    {
        $service = new CasbinService();
        foreach ([1, 2, 3, 4, 5, 999] as $adminId) {
            foreach ([null, 'tenant'] as $domain) {
                self::assertSame(
                    $this->legacyCodes($adminId, $domain),
                    $service->permissionCodesForAdmin($adminId, $domain),
                    "admin {$adminId} domain " . ($domain ?? 'default')
                );
            }
        }
        self::assertSame([], $service->permissionCodesForAdmin(0));
    }

    public function test_fixture_decisions_are_not_vacuous(): void
    {
        $service = new CasbinService();
        $public = 'fixture:public';
        $codes = fn (int $adminId, ?string $domain = null): array => $service->permissionCodesForAdmin($adminId, $domain);

        // 公开权限对任何有效管理员都可见，即便没有角色。
        self::assertSame([$public], $codes(5));
        self::assertContains($public, $codes(999));

        // 超管：只看其角色实际授权（"*" 由 userInfo 负责，不在此处）。
        self::assertEqualsCanonicalizing(array_merge($this->chunks['super'], [$public]), $codes(1));

        // 多角色 + pid 继承 + auth_group_inherit 继承，并包含同 obj/act 的重复能力行。
        $multi = $codes(2);
        foreach (['editor', 'auditor', 'parent', 'inherited', 'child'] as $chunk) {
            foreach ($this->chunks[$chunk] as $code) self::assertContains($code, $multi, $chunk);
        }
        self::assertContains('fixture:duplicate-pair', $multi);
        self::assertNotContains($this->chunks['dept'][0], $multi);

        // 部门数据范围角色。
        self::assertEqualsCanonicalizing(array_merge($this->chunks['dept'], [$public]), $codes(3));

        // 停用/软删/空码权限与停用角色均不授予。
        $mixed = $codes(4);
        foreach ($this->chunks['disabledPermission'] as $code) self::assertNotContains($code, $mixed);
        foreach ($this->chunks['disabledRole'] as $code) self::assertNotContains($code, $mixed);
        foreach ($this->chunks['mixedActive'] as $code) self::assertContains($code, $mixed);
        self::assertCount(count($this->chunks['mixedActive']) + 1, $mixed);

        // 其他 domain 的授权互不泄漏。
        self::assertEqualsCanonicalizing(array_merge($this->chunks['tenant'], [$public]), $codes(2, 'tenant'));
        self::assertSame([$public], $codes(3, 'tenant'));
    }

    public function test_one_call_evaluates_implicit_permissions_once_without_per_permission_enforce(): void
    {
        $service = new CountingCasbinService();
        $codes = $service->permissionCodesForAdmin(2);

        self::assertNotEmpty($codes);
        self::assertSame(1, CountingEnforcer::$builds);
        self::assertSame(0, CountingEnforcer::$enforceCalls);
        self::assertSame(1, CountingEnforcer::$implicitCalls);
    }

    public function test_one_call_uses_a_bounded_number_of_queries(): void
    {
        $service = new CasbinService();
        $queries = 0;
        Db::connection()->listen(static function () use (&$queries): void {
            $queries++;
        });
        $service->permissionCodesForAdmin(2);
        // 夹具在未提交事务内写入授权表，AuthzCache 绕过 worker 缓存，这里量的是冷构建：
        // enforcer 构建（角色、casbin_rule、继承表）+ 1 次读取有效权限；不随权限条数增长。
        // 原为 6：Task 6 去掉了 AuthGroup::count()（深度改用已加载的有效角色数）与运行时 hasTable 探测。
        self::assertSame(4, $queries);
    }

    /** 旧实现：对每条有效 route/capability 权限调用一次 enforce()。 */
    private function legacyCodes(int $adminId, ?string $domain): array
    {
        if ($adminId <= 0) return [];
        $enforcer = (new CasbinService())->enforcer($domain);
        $permissions = \app\admin\authorization\model\Permission::where('status', 1)->whereNull('deleted_at')
            ->whereIn('resource_type', ['route', 'capability'])->get();
        $codes = [];
        foreach ($permissions as $permission) {
            if ($permission->is_public || $enforcer->enforce(PermissionResource::subject($adminId), PermissionResource::domain($domain), (string) $permission->obj, (string) $permission->act)) {
                if ($permission->code !== null && $permission->code !== '') $codes[] = (string) $permission->code;
            }
        }
        return array_values(array_unique($codes));
    }

    private function seedFixtures(): void
    {
        $now = date('Y-m-d H:i:s');
        // 唯一的公开权限（覆盖基线中原有的公开权限，便于断言）。
        Db::table('permission')->where('is_public', 1)->update(['is_public' => 0]);
        Db::table('permission')->insert(['id' => 90001, 'code' => 'fixture:public', 'obj' => 'fixture/public', 'act' => 'get', 'name' => 'public', 'resource_type' => 'capability', 'status' => 1, 'is_public' => 1, 'created_at' => $now]);

        $ids = Db::table('permission')->where('status', 1)->whereNull('deleted_at')->where('is_public', 0)
            ->whereIn('resource_type', ['route', 'capability'])->orderBy('id')->pluck('id')->map('intval')->all();
        self::assertGreaterThan(100, count($ids));
        $take = static function (int $offset, int $length) use ($ids): array {
            return array_slice($ids, $offset, $length);
        };
        $groups = [
            'super' => $take(0, 5), 'editor' => $take(10, 20), 'auditor' => $take(25, 15), 'parent' => $take(50, 8),
            'inherited' => $take(60, 7), 'child' => $take(70, 3), 'dept' => $take(80, 9), 'mixed' => $take(100, 12),
            'disabledRole' => $take(120, 6), 'tenant' => $take(130, 4),
        ];

        // 同一 obj/act 的另一条能力行：通过 editor 的第一条授权间接获得。
        $first = Db::table('permission')->where('id', $groups['editor'][0])->first();
        Db::table('permission')->insert(['id' => 90002, 'code' => 'fixture:duplicate-pair', 'obj' => $first->obj, 'act' => $first->act, 'name' => 'dup', 'resource_type' => 'capability', 'status' => 1, 'created_at' => $now]);

        Db::table('auth_group')->insert([
            ['id' => 1, 'pid' => 0, 'name' => '超级管理员', 'code' => 'super_admin', 'level' => 1, 'data_scope' => 'all', 'status' => 1],
            ['id' => 10, 'pid' => 0, 'name' => 'editor', 'code' => 'editor', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 11, 'pid' => 0, 'name' => 'auditor', 'code' => 'auditor', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 12, 'pid' => 0, 'name' => 'parent', 'code' => 'parent', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 13, 'pid' => 12, 'name' => 'child', 'code' => 'child', 'level' => 20, 'data_scope' => 'self', 'status' => 1],
            ['id' => 14, 'pid' => 0, 'name' => 'inherited', 'code' => 'inherited', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 20, 'pid' => 0, 'name' => 'dept', 'code' => 'dept', 'level' => 10, 'data_scope' => 'dept', 'status' => 1],
            ['id' => 30, 'pid' => 0, 'name' => 'mixed', 'code' => 'mixed', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
            ['id' => 31, 'pid' => 0, 'name' => 'disabled', 'code' => 'disabled', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
        ]);
        Db::table('auth_group_inherit')->insert(['role_id' => 13, 'parent_role_id' => 14, 'created_at' => $now]);
        Db::table('department')->insert(['id' => 7, 'pid' => 0, 'name' => 'Sales', 'status' => 1]);
        Db::table('admin')->insert([
            ['id' => 1, 'username' => 'root', 'password' => 'x', 'dept_id' => 0, 'status' => 1],
            ['id' => 2, 'username' => 'multi', 'password' => 'x', 'dept_id' => 0, 'status' => 1],
            ['id' => 3, 'username' => 'scoped', 'password' => 'x', 'dept_id' => 7, 'status' => 1],
            ['id' => 4, 'username' => 'mixed', 'password' => 'x', 'dept_id' => 0, 'status' => 1],
            ['id' => 5, 'username' => 'nobody', 'password' => 'x', 'dept_id' => 0, 'status' => 1],
        ]);
        Db::table('admin_department')->insert(['admin_id' => 3, 'dept_id' => 7, 'created_at' => $now]);

        $casbin = new CasbinService();
        foreach (['super' => 1, 'editor' => 10, 'auditor' => 11, 'parent' => 12, 'inherited' => 14, 'dept' => 20, 'mixed' => 30, 'disabledRole' => 31] as $group => $roleId) {
            $casbin->syncRolePermissions($roleId, $groups[$group]);
        }
        $casbin->syncRolePermissions(13, $groups['child']);
        $casbin->syncRolePermissions(10, $groups['tenant'], 'tenant');
        $casbin->syncAdminRoles(1, [1]);
        $casbin->syncAdminRoles(2, [10, 11, 13]);
        $casbin->syncAdminRoles(3, [20]);
        $casbin->syncAdminRoles(4, [30, 31]);
        $casbin->syncAdminRoles(999, [10]); // 不存在的管理员仍按策略求值，保持与旧实现一致。
        $casbin->syncAdminRoles(2, [10], 'tenant');

        // 授权写入后再让部分权限失效：停用、软删、空码；并停用角色 31。
        $mixed = $groups['mixed'];
        Db::table('permission')->whereIn('id', array_slice($mixed, 0, 3))->update(['status' => 0]);
        Db::table('permission')->whereIn('id', array_slice($mixed, 3, 2))->update(['deleted_at' => $now]);
        Db::table('permission')->where('id', $mixed[5])->update(['code' => null]);
        AuthGroup::where('id', 31)->update(['status' => 0]);

        $codes = static fn (array $ids): array => Db::table('permission')->whereIn('id', $ids ?: [0])->orderBy('id')->pluck('code')->filter()->values()->all();
        $this->chunks = array_map($codes, $groups);
        $this->chunks['editor'][] = 'fixture:duplicate-pair';
        $this->chunks['disabledPermission'] = Db::table('permission')->whereIn('id', array_slice($mixed, 0, 5))->pluck('code')->all();
        $this->chunks['mixedActive'] = $codes(array_slice($mixed, 6));
    }
}

final class CountingEnforcer extends Enforcer
{
    public static int $builds = 0;
    public static int $enforceCalls = 0;
    public static int $implicitCalls = 0;

    public function enforce(...$rvals): bool
    {
        self::$enforceCalls++;
        return parent::enforce(...$rvals);
    }

    public function getImplicitPermissionsForUser(string $user, string ...$domain): array
    {
        self::$implicitCalls++;
        return parent::getImplicitPermissionsForUser($user, ...$domain);
    }
}

final class CountingCasbinService extends CasbinService
{
    protected function buildEnforcer(string $domain): Enforcer
    {
        CountingEnforcer::$builds++;
        $adapter = new DatabaseAdapter($domain);
        $enforcer = new CountingEnforcer(config_path() . '/casbin/rbac_model.conf', $adapter, enableLog: false);
        $enforcer->enableAutoSave(false);
        $enforcer->setRoleManager(new DomainManager(max(10, $adapter->activeRoleCount() + 1)));
        $enforcer->buildRoleLinks();
        return $enforcer;
    }
}
