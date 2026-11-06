<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\PermissionCode;
use app\admin\authorization\service\PermissionGrantMigration;
use app\admin\authorization\service\PermissionMigrationPlan;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 授权等价：每个旧权限各授予一个独立角色，迁移后对每个 (角色, 路由)，
 * "角色拥有该路由所需的新权限"必须与"迁移前角色拥有该路由所需的旧权限"完全一致。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PermissionGrantMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        foreach (['casbin_rule', 'auth_group'] as $table) Db::table($table)->delete();
        // 升级前的状态：插件清单登记过的旧权限码全部存在（当前清单已改为推导码，因此按冻结映射补登记）。
        $map = require dirname(__DIR__) . '/database/permission-maps/plugin-per-method.php';
        foreach (array_keys($map) as $old) {
            if (!Db::table('permission')->where('code', $old)->exists()) {
                \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions([['code' => $old, 'name' => $old]], 'plugin', explode(':', $old)[0]);
            }
        }
        foreach (\app\cms\service\PermissionCatalog::operations() as $operation) {
            if (!Db::table('permission')->where('code', $operation)->exists()) {
                \app\admin\authorization\service\ResourceRegistryService::instance()->registerPermissions([['code' => $operation, 'name' => $operation]], 'plugin', 'cms');
            }
        }
        // 测试库可能已执行过 034 迁移：恢复旧权限码为启用状态，模拟升级前。
        Db::table('permission')->whereIn('code', array_keys($map))->update(['status' => 1]);
    }

    protected function tearDown(): void
    {
        Db::rollBack();
    }

    private static function before(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/fixtures/auth-requirements-before.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_plugin_grants_expand_so_every_role_keeps_exactly_the_same_routes(): void
    {
        $before = self::before();
        $plan = PermissionMigrationPlan::expansion($before, [PermissionMigrationPlan::class, 'isPluginRoute']);
        foreach (["cms", "qa", "example", "market"] as $p) (new \app\admin\authorization\service\PermissionSyncService())->sync($p);

        // 每个旧权限一个角色，另加一个持有多个旧权限的角色，以及一个无授权角色。
        $oldCodes = array_keys($plan);
        $roles = [];
        $roleId = 500;
        foreach ($oldCodes as $old) $roles[++$roleId] = [$old];
        $roles[++$roleId] = ['market:order:view', 'cms:site:list'];
        $roles[++$roleId] = [];
        $casbin = new CasbinService();
        foreach ($roles as $id => $codes) {
            Db::table('auth_group')->insert(['id' => $id, 'name' => 'r' . $id, 'code' => 'r' . $id, 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
            $this->grantByCode($casbin, $id, $codes);
        }

        (new PermissionGrantMigration())->apply($plan, self::keep());

        foreach ($before as $routeKey => $requirement) {
            if (!PermissionMigrationPlan::isPluginRoute($routeKey) || $requirement['guard'] !== 'plugin') continue;
            preg_match('/=> (.+)::(.+)$/', $routeKey, $target);
            $newCode = PermissionCode::derive($target[1], $target[2]);
            foreach ($roles as $id => $codes) {
                $old = array_values(array_filter((array) $requirement['permission'], static fn ($c) => is_string($c) && !str_starts_with($c, '!')));
                $hadBefore = array_intersect($old, $codes) !== [];
                $hasAfter = $this->roleHas($id, $newCode);
                self::assertSame($hadBefore, $hasAfter, "角色 $id (" . implode(',', $codes) . ") 对 $routeKey 的访问能力发生变化");
            }
        }
    }

    public function test_migration_is_idempotent(): void
    {
        $plan = PermissionMigrationPlan::expansion(self::before(), [PermissionMigrationPlan::class, 'isPluginRoute']);
        foreach (["cms", "qa", "example", "market"] as $p) (new \app\admin\authorization\service\PermissionSyncService())->sync($p);
        Db::table('auth_group')->insert(['id' => 900, 'name' => 'r', 'code' => 'r900', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        $this->grantByCode(new CasbinService(), 900, ['market:plugin:manage']);
        $migration = new PermissionGrantMigration();
        $migration->apply($plan);
        $first = Db::table('casbin_rule')->orderBy('v2')->orderBy('v3')->get(['v0', 'v2', 'v3'])->toArray();
        $migration->apply($plan);
        self::assertEquals($first, Db::table('casbin_rule')->orderBy('v2')->orderBy('v3')->get(['v0', 'v2', 'v3'])->toArray());
    }

    public function test_cms_site_operations_stay_registered_with_their_grants(): void
    {
        $plan = PermissionMigrationPlan::expansion(self::before(), [PermissionMigrationPlan::class, 'isPluginRoute']);
        foreach (["cms", "qa", "example", "market"] as $p) (new \app\admin\authorization\service\PermissionSyncService())->sync($p);
        Db::table('auth_group')->insert(['id' => 901, 'name' => 'r', 'code' => 'r901', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        $this->grantByCode(new CasbinService(), 901, \app\cms\service\PermissionCatalog::operations());

        (new PermissionGrantMigration())->apply($plan, self::keep());

        self::assertSame(self::keep(), \app\cms\service\PermissionCatalog::operations());
        $registered = \app\cms\service\PermissionCatalog::registered();
        foreach (\app\cms\service\PermissionCatalog::operations() as $operation) {
            self::assertContains($operation, $registered, "站点操作 $operation 迁移后必须仍可分配");
            $row = Db::table('permission')->where('code', $operation)->first();
            self::assertTrue(Db::table('casbin_rule')->where('ptype', 'p')->where('v0', 'role:901')->where('v2', $row->obj)->where('v3', $row->act)->exists(), "$operation 的授权被撤销");
        }
        foreach (['cms:site:list', 'cms:site:save', 'cms:site:grant', 'cms:category:save', 'qa:moderation:list', 'market:plugin:view'] as $retired) {
            self::assertSame(0, (int) Db::table('permission')->where('code', $retired)->value('status'), "$retired 应停用");
        }
    }

    public function test_plugin_menus_are_rebound_to_the_list_endpoint_code(): void
    {
        foreach (["cms", "qa"] as $p) (new \app\admin\authorization\service\PermissionSyncService())->sync($p);
        $menus = require dirname(__DIR__) . '/database/permission-maps/plugin-per-method-menus.php';
        $plan = PermissionMigrationPlan::expansion(self::before(), [PermissionMigrationPlan::class, 'isPluginRoute']);
        $ids = [];
        foreach ($menus as $old => $new) {
            self::assertSame([$old], array_keys(array_filter($plan, static fn (array $targets): bool => in_array($new, $targets, true))), "$new 必须只由 $old 展开");
            $permissionId = (int) Db::table('permission')->where('code', $old)->value('id');
            $ids[$old] = Db::table('admin_menu')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'name' => $old, 'href' => '/plugin/test/' . str_replace(':', '-', $old),
                'query' => 'type=C&permission=' . rawurlencode($old), 'permission_id' => $permissionId, 'status' => 1, 'source_type' => 'plugin', 'source_name' => explode(':', $old)[0]]);
        }

        $migration = new PermissionGrantMigration();
        $migration->rebindMenus($menus);
        $migration->rebindMenus($menus);

        foreach ($menus as $old => $new) {
            $menu = Db::table('admin_menu')->where('id', $ids[$old])->first();
            self::assertSame($new, Db::table('permission')->where('id', $menu->permission_id)->value('code'), $old);
            self::assertSame('type=C&permission=' . rawurlencode($new), $menu->query, $old);
        }
    }

    private static function keep(): array
    {
        return require dirname(__DIR__) . '/database/permission-maps/plugin-per-method-keep.php';
    }

    private function grantByCode(CasbinService $casbin, int $roleId, array $codes): void
    {
        $ids = $codes === [] ? [] : Db::table('permission')->whereIn('code', $codes)->pluck('id')->all();
        self::assertCount(count($codes), $ids, '测试前置：旧权限必须存在 ' . implode(',', $codes));
        $casbin->syncRolePermissions($roleId, $ids);
    }

    private function roleHas(int $roleId, string $code): bool
    {
        ['obj' => $obj, 'act' => $act] = PermissionCode::resource($code);
        return Db::table('casbin_rule')->where('ptype', 'p')->where('v0', 'role:' . $roleId)->where('v2', $obj)->where('v3', $act)->exists();
    }
}
