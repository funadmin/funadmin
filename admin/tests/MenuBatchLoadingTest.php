<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\AuthorizationService;
use app\admin\authorization\service\CasbinService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 菜单一次性加载权限码：菜单树须与逐条查询权限码的旧实现完全一致（fixtures 为旧实现输出），
 * 且查询次数为常量，不随菜单条数增长。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class MenuBatchLoadingTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
    }

    public function test_editor_menu_tree_matches_the_legacy_output(): void
    {
        self::assertSame($this->fixture('menu-parity-editor.json'), (new AuthorizationService())->menus(['id' => 2]));
    }

    public function test_super_admin_menu_tree_matches_the_legacy_output(): void
    {
        self::assertSame($this->fixture('menu-parity-super-admin.json'), (new AuthorizationService())->menus(['id' => 1]));
    }

    public function test_menus_use_a_constant_number_of_queries(): void
    {
        $service = new AuthorizationService();
        $editor = $this->countQueries(static fn () => $service->menus(['id' => 2]));
        $super = $this->countQueries(static fn () => $service->menus(['id' => 1]));

        // 再加 30 个授权菜单（含权限），查询次数不变。
        $now = '2026-01-01 00:00:00';
        for ($i = 0; $i < 30; $i++) {
            Db::table('permission')->insert(['id' => 92000 + $i, 'code' => 'fx:extra:' . $i, 'obj' => 'fx/extra' . $i, 'act' => 'index', 'name' => 'x', 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'created_at' => $now]);
            Db::table('admin_menu')->insert(['id' => 300 + $i, 'pid' => 100, 'permission_id' => 92000 + $i, 'app_name' => 'admin', 'name' => 'x' . $i, 'href' => 'x' . $i, 'query' => 'name=X' . $i, 'icon' => '', 'sort_order' => 10 + $i, 'status' => 1, 'source_type' => 'admin_web']);
        }
        (new CasbinService())->syncRolePermissions(10, array_merge([91001, 91003, 91005, 91007, 91009], range(92000, 92029)));
        self::assertCount(33, $service->menus(['id' => 2])[2]['children']);

        self::assertSame($editor, $this->countQueries(static fn () => $service->menus(['id' => 2])));
        self::assertSame($super, $this->countQueries(static fn () => $service->menus(['id' => 1])));
        // 管理员 + 直接角色(casbin_rule + 角色状态) + 角色行 + 菜单行 + 1 次权限码映射；
        // 非超管另有一次 enforcer 构建(3) 与有效权限读取(1)。夹具写在未提交事务内，AuthzCache 绕过 worker 缓存，
        // 这里量的是冷构建。editor 原为 12：Task 6 去掉了 enforcer 构建中的 AuthGroup::count() 与 hasTable 探测。
        self::assertSame(10, $editor);
        self::assertSame(6, $super);
    }

    private function fixture(string $name): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/fixtures/' . $name), true, flags: JSON_THROW_ON_ERROR);
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

    /** 覆盖：直接授权、公开权限、权限码大小写不一致、空码、停用/缺失权限的父级补全、停用/软删/他应用/孤儿/环状菜单。 */
    private function seedFixtures(): void
    {
        foreach (['casbin_rule', 'auth_group_inherit', 'auth_group_department', 'admin_department', 'admin_menu', 'department', 'auth_group', 'admin'] as $table) {
            Db::table($table)->delete();
        }
        $now = '2026-01-01 00:00:00';
        $permission = static fn (int $id, ?string $code, string $obj, int $status = 1, int $public = 0): array => ['id' => $id, 'code' => $code, 'obj' => $obj, 'act' => 'index', 'name' => $obj, 'resource_type' => 'route', 'status' => $status, 'is_public' => $public, 'created_at' => $now];
        Db::table('permission')->insert([
            $permission(91001, 'fx:user:view', 'fx/user'),
            $permission(91002, 'fx:secret:view', 'fx/secret'),
            $permission(91003, 'fx:log:view', 'fx/log'),
            $permission(91004, 'fx:tools:view', 'fx/tools'),
            $permission(91005, 'fx:tools:gen', 'fx/gen'),
            $permission(91006, 'fx:off:view', 'fx/off', 0),
            $permission(91007, 'Fx:Mixed:View', 'fx/mixed'),
            $permission(91008, 'fx:public:view', 'fx/public', 1, 1),
            $permission(91009, null, 'fx/nocode'),
        ]);
        Db::table('auth_group')->insert([
            ['id' => 1, 'pid' => 0, 'name' => '超级管理员', 'code' => 'super_admin', 'level' => 1, 'data_scope' => 'all', 'status' => 1],
            ['id' => 10, 'pid' => 0, 'name' => 'editor', 'code' => 'editor', 'level' => 10, 'data_scope' => 'self', 'status' => 1],
        ]);
        Db::table('admin')->insert([
            ['id' => 1, 'username' => 'root', 'password' => 'x', 'dept_id' => 0, 'status' => 1],
            ['id' => 2, 'username' => 'editor', 'password' => 'x', 'dept_id' => 0, 'status' => 1],
        ]);
        $casbin = new CasbinService();
        $casbin->syncAdminRoles(1, [1]);
        $casbin->syncAdminRoles(2, [10]);
        $casbin->syncRolePermissions(10, [91001, 91003, 91005, 91007, 91009]);

        $menu = static fn (int $id, int $pid, int $permissionId, string $name, string $href, string $query, int $sort, array $extra = []): array => array_merge([
            'id' => $id, 'pid' => $pid, 'permission_id' => $permissionId, 'app_name' => 'admin', 'name' => $name, 'href' => $href,
            'query' => $query, 'icon' => 'i-' . $id, 'sort_order' => $sort, 'status' => 1, 'source_type' => 'admin_web', 'deleted_at' => null,
        ], $extra);
        Db::table('admin_menu')->insert([
            $menu(100, 0, 0, '系统', 'system', 'name=System&type=M&component=Layout&redirect=/system/user', 2),
            $menu(101, 100, 91001, '用户', 'user', 'name=User&type=C&component=system%2Fuser%2Findex', 2),
            $menu(102, 100, 91002, '秘密', 'secret', 'name=Secret&type=C', 1),
            $menu(103, 100, 91003, '日志', 'log', 'name=Log&type=C&hidden=1&keepAlive=1&affix=true&formKey=logs', 1),
            $menu(104, 100, 91008, '公开', 'public', 'name=Public', 3),
            $menu(110, 0, 91004, '工具', '/tools', 'name=Tools&type=M&component=Layout', 1),
            $menu(111, 110, 91005, '生成', 'gen', 'name=Gen&type=C', 1),
            $menu(112, 111, 91007, '大小写', 'mixed', 'name=Mixed&type=C', 1),
            $menu(113, 111, 91009, '无码', 'nocode', 'name=NoCode&type=C', 2),
            $menu(120, 0, 0, '首页', 'dashboard', 'name=Dashboard&type=C&affix=1', 1),
            $menu(130, 0, 0, '停用', 'off', 'name=Off', 0, ['status' => 0]),
            $menu(131, 0, 0, '已删', 'gone', 'name=Gone', 0, ['deleted_at' => $now]),
            $menu(132, 0, 0, '他应用', 'other', 'name=Other', 0, ['app_name' => 'other']),
            $menu(140, 999, 91001, '孤儿', 'orphan', 'name=Orphan', 0),
            $menu(150, 0, 91006, '停用权限', 'offperm', 'name=OffPerm&type=M', 5),
            $menu(151, 150, 91001, '停用权限子', 'offchild', 'name=OffChild', 1),
            $menu(160, 0, 88888, '缺失权限', 'missing', 'name=Missing&type=M', 6),
            $menu(161, 160, 0, '缺失权限子', 'missingchild', 'name=MissingChild', 1),
            $menu(170, 171, 0, '环A', 'loopa', 'name=LoopA', 7),
            $menu(171, 170, 0, '环B', 'loopb', 'name=LoopB', 8),
        ]);
    }
}
