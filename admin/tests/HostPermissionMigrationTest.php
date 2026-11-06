<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\CasbinService;
use app\admin\authorization\service\HostPermissionMigration;
use app\admin\authorization\service\PermissionCode;
use app\admin\authorization\service\PermissionResource;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 宿主改名与授权等价：迁移前每个角色能访问的宿主接口，迁移后完全一致；
 * 旧权限行原地改名（ID、名称、菜单、字段授权保留），迁移前未登记的接口只登记、不授予。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class HostPermissionMigrationTest extends TestCase
{
    /** @var array<string, string> 旧权限码 → 旧 obj|act */
    private array $legacy = [];

    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        foreach (['casbin_rule', 'auth_group'] as $table) Db::table($table)->delete();
        // 还原升级前状态：删除宿主推导码行，按冻结快照登记旧权限码（旧推导 obj/act）。
        $map = self::map();
        Db::table('permission')->whereIn('code', array_merge(...array_values($map)))->where('source_type', '<>', 'plugin')->delete();
        foreach (self::before() as $routeKey => $requirement) {
            if ($requirement['guard'] !== 'admin') continue;
            preg_match('/=> (.+)::(.+)$/', $routeKey, $target);
            $request = new \stdClass();
            $request->controller = $target[1];
            $request->action = $target[2];
            $resource = PermissionResource::fromRequest($request);
            $public = $requirement['permission'] === null && $requirement['login']
                && (new \app\common\auth\AuthRequirementResolver())->for($target[1], $target[2])->permission;
            if ($public && !Db::table('permission')->where('obj', $resource['obj'])->where('act', $resource['act'])->exists()) {
                Db::table('permission')->insert(['app_name' => 'admin', 'code' => $resource['code'], 'obj' => $resource['obj'], 'act' => $resource['act'],
                    'name' => '公开', 'resource_type' => 'route', 'status' => 1, 'is_public' => 1, 'source_type' => 'admin_web', 'source_name' => 'test']);
            }
            if (!is_string($requirement['permission']) || str_starts_with($requirement['permission'], '!')) continue;
            $this->legacy[$requirement['permission']] = $resource['obj'] . '|' . $resource['act'];
            if (!Db::table('permission')->where('obj', $resource['obj'])->where('act', $resource['act'])->exists()) {
                Db::table('permission')->insert(['app_name' => 'admin', 'code' => $requirement['permission'], 'obj' => $resource['obj'], 'act' => $resource['act'],
                    'name' => '旧:' . $requirement['permission'], 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'admin_web', 'source_name' => 'test']);
            }
        }
    }

    protected function tearDown(): void
    {
        Db::rollBack();
    }

    public function test_every_role_keeps_exactly_the_same_host_routes_and_rows_are_renamed_in_place(): void
    {
        $roles = [];
        $id = 600;
        foreach (array_keys($this->legacy) as $old) $roles[++$id] = [$old];
        $roles[++$id] = ['systemrole:index', 'systemoperationlog:delete'];
        $roles[++$id] = [];
        $casbin = new CasbinService();
        foreach ($roles as $roleId => $codes) {
            Db::table('auth_group')->insert(['id' => $roleId, 'name' => 'r' . $roleId, 'code' => 'r' . $roleId, 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
            $ids = $codes === [] ? [] : Db::table('permission')->whereIn('code', $codes)->pluck('id')->all();
            self::assertCount(count($codes), $ids, implode(',', $codes));
            $casbin->syncRolePermissions($roleId, $ids);
        }
        $idsBefore = Db::table('permission')->whereIn('code', array_keys($this->legacy))->pluck('id', 'code')->all();
        $nameBefore = Db::table('permission')->where('code', 'systemrole:index')->value('name');
        $menuId = Db::table('admin_menu')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'name' => 'm', 'href' => 'host-migration-test',
            'query' => 'component=system/role/index&permission=systemrole:index&type=C', 'permission_id' => $idsBefore['systemrole:index'], 'status' => 1, 'source_type' => 'admin_web', 'source_name' => 'test']);

        $migration = new HostPermissionMigration();
        $migration->apply(self::map());
        (new \app\admin\authorization\service\PermissionSyncService())->sync();
        $migration->apply(self::map());

        $checked = 0;
        foreach (self::before() as $routeKey => $requirement) {
            if ($requirement['guard'] !== 'admin' || $requirement['permission'] === null) continue;
            preg_match('/=> (.+)::(.+)$/', $routeKey, $target);
            // 冻结的升级前快照仍记录已移除模块（身份中心）的接口；这些控制器已不存在，不再参与比较。
            if (!class_exists($target[1])) continue;
            $code = PermissionCode::derive($target[1], $target[2]);
            self::assertTrue(Db::table('permission')->where('code', $code)->where('status', 1)->exists(), "$code 必须登记");
            foreach ($roles as $roleId => $codes) {
                $had = in_array($requirement['permission'], $codes, true);
                self::assertSame($had, $this->roleHas($roleId, $code), "角色 $roleId (" . implode(',', $codes) . ") 对 $routeKey 的访问能力发生变化");
                $checked++;
            }
        }
        self::assertGreaterThan(35000, $checked);
        // 因权限行 is_public 而"登录即可"的接口改名后仍然公开。
        $public = 0;
        foreach (self::before() as $routeKey => $requirement) {
            if ($requirement['guard'] !== 'admin' || $requirement['permission'] !== null || !$requirement['login']) continue;
            preg_match('/=> (.+)::(.+)$/', $routeKey, $target);
            if (!(new \app\common\auth\AuthRequirementResolver())->for($target[1], $target[2])->permission) continue;
            self::assertSame(1, (int) Db::table('permission')->where('code', PermissionCode::derive($target[1], $target[2]))->value('is_public'), $routeKey);
            $public++;
        }
        self::assertGreaterThan(0, $public);
        // 原地改名：ID 与名称不变，旧 obj/act 授权全部移走。
        self::assertSame($idsBefore['systemrole:index'], (int) Db::table('permission')->where('code', 'authorization:role:index')->value('id'));
        self::assertSame($nameBefore, Db::table('permission')->where('code', 'authorization:role:index')->value('name'));
        self::assertSame(0, Db::table('casbin_rule')->where('v2', 'like', 'admin/%')->count());
        $menu = Db::table('admin_menu')->where('id', $menuId)->first();
        self::assertSame($idsBefore['systemrole:index'], (int) $menu->permission_id);
        self::assertSame('component=system/role/index&permission=authorization:role:index&type=C', $menu->query);
    }

    public function test_disabled_legacy_rows_stay_disabled_and_grant_nothing(): void
    {
        Db::table('auth_group')->insert(['id' => 710, 'name' => 'd', 'code' => 'd710', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        (new CasbinService())->syncRolePermissions(710, Db::table('permission')->whereIn('code', ['systemconfig:index', 'systemoperationlog:delete'])->pluck('id')->all());
        Db::table('permission')->whereIn('code', ['systemconfig:index', 'systemoperationlog:delete'])->update(['status' => 0]);

        $migration = new HostPermissionMigration();
        $migration->apply(self::map());
        (new \app\admin\authorization\service\PermissionSyncService())->sync();
        $migration->apply(self::map());

        foreach (['system:config:index', 'system:operation-log:delete', 'system:operation-log:clear'] as $code) {
            self::assertSame(0, (int) Db::table('permission')->where('code', $code)->value('status'), "$code 升级前已停用，升级后必须保持停用");
        }
    }

    public function test_capability_whose_code_collides_with_a_derived_route_is_retired_without_granting_the_route(): void
    {
        Db::table('permission')->where('code', 'development:business:publish')->delete();
        Db::table('permission')->insert(['app_name' => 'admin', 'code' => 'development:business:publish', 'obj' => 'development/business', 'act' => 'publish',
            'name' => '业务发布', 'resource_type' => 'capability', 'status' => 1, 'is_public' => 0, 'source_type' => 'admin_web', 'source_name' => 'business']);
        Db::table('auth_group')->insert(['id' => 700, 'name' => 'cap', 'code' => 'cap', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
        (new CasbinService())->syncRolePermissions(700, Db::table('permission')->where('code', 'development:business:publish')->pluck('id')->all());

        (new HostPermissionMigration())->apply(self::map());
        (new \app\admin\authorization\service\PermissionSyncService())->sync();

        $route = Db::table('permission')->where('code', 'development:business:publish')->first();
        self::assertSame('route', $route->resource_type);
        self::assertFalse($this->roleHas(700, 'development:business:publish'), '只持有同名能力权限的角色不能因改名获得接口权限');
    }

    public function test_core_menus_bind_the_derived_code_of_their_page_list_method(): void
    {
        $capability = Db::table('permission')->insertGetId(['app_name' => 'admin', 'code' => 'development:business:view-test', 'obj' => 'development/business', 'act' => 'view-test',
            'name' => '查看业务', 'resource_type' => 'capability', 'status' => 1, 'is_public' => 0, 'source_type' => 'admin_web', 'source_name' => 'business']);
        $menuId = Db::table('admin_menu')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'name' => '业务开发', 'href' => 'business/mine-test',
            'query' => 'component=development/business/mine&name=BusinessDevelopment&type=C&permission=development:business:view-test&keepAlive=1', 'permission_id' => $capability,
            'status' => 1, 'source_type' => 'admin_web', 'source_name' => 'business_development']);
        $migration = new HostPermissionMigration();
        $migration->apply(self::map());
        (new \app\admin\authorization\service\PermissionSyncService())->sync();
        $migration->rebindCoreMenus(require dirname(__DIR__) . '/database/permission-maps/host-menus.php');
        $migration->rebindCoreMenus(require dirname(__DIR__) . '/database/permission-maps/host-menus.php');

        $menu = Db::table('admin_menu')->where('id', $menuId)->first();
        self::assertSame('development:business:modules', Db::table('permission')->where('id', $menu->permission_id)->value('code'));
        self::assertSame('component=development/business/mine&name=BusinessDevelopment&type=C&permission=development:business:modules&keepAlive=1', $menu->query);
        foreach (require dirname(__DIR__) . '/database/permission-maps/host-menus.php' as $name => $code) {
            foreach (Db::table('admin_menu')->where('source_type', 'admin_web')->where('query', 'like', '%name=' . $name . '&%')->get() as $row) {
                self::assertSame($code, Db::table('permission')->where('id', $row->permission_id)->value('code'), $name);
            }
        }
    }

    public function test_previously_generated_host_modules_are_migrated_from_their_legacy_resources(): void
    {
        $app = sys_get_temp_dir() . '/funadmin-generated-' . bin2hex(random_bytes(4));
        mkdir($app . '/admin/generated/shop/controller', 0700, true);
        file_put_contents($app . '/admin/generated/shop/controller/OrderItemController.php', "<?php\nnamespace app\\admin\\generated\\shop\\controller;\nuse support\\annotation\\route\\Get;\nuse support\\annotation\\route\\Delete;\n#[\\support\\annotation\\route\\RouteGroup('/admin/generated/order-item')]\nfinal class OrderItemController\n{\n    #[Get('')]\n    public function index() {}\n    #[Delete('')]\n    public function recycle() {}\n}\n");
        try {
            $map = HostPermissionMigration::generatedLegacyMap($app);
            self::assertSame(['admin/generated.orderitemcontroller|index' => ['generated:order-item:index'], 'admin/generated.orderitemcontroller|recycle' => ['generated:order-item:recycle']], $map);
            $id = Db::table('permission')->insertGetId(['app_name' => 'admin', 'code' => 'generated:order-item:list', 'obj' => 'admin/generated.orderitemcontroller', 'act' => 'index',
                'name' => '订单列表', 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'generated', 'source_name' => 'order-item']);
            Db::table('auth_group')->insert(['id' => 720, 'name' => 'g', 'code' => 'g720', 'level' => 10, 'data_scope' => 'all', 'status' => 1]);
            (new CasbinService())->syncRolePermissions(720, [$id]);
            (new HostPermissionMigration())->apply($map);
            self::assertSame('generated:order-item:index', Db::table('permission')->where('id', $id)->value('code'));
            self::assertTrue($this->roleHas(720, 'generated:order-item:index'));
            self::assertFalse($this->roleHas(720, 'generated:order-item:recycle'));
        } finally {
            unlink($app . '/admin/generated/shop/controller/OrderItemController.php');
            foreach (['/admin/generated/shop/controller', '/admin/generated/shop', '/admin/generated', '/admin', ''] as $dir) rmdir($app . $dir);
        }
    }

    public function test_group_rows_carry_no_code_and_empty_groups_are_removed(): void
    {
        $group = static fn (array $values): int => Db::table('permission')->insertGetId($values + ['app_name' => 'admin', 'code' => null, 'obj' => '', 'act' => '',
            'resource_type' => 'group', 'status' => 1, 'is_public' => 0, 'source_type' => 'admin_web', 'source_name' => 'test']);
        $coded = $group(['name' => '系统升级', 'code' => 'test-group:upgrade:list', 'obj' => 'test-group/upgrade', 'act' => 'list']);
        Db::table('permission')->insert(['pid' => $coded, 'app_name' => 'admin', 'code' => 'test-group:upgrade:status', 'obj' => 'test-group/upgrade', 'act' => 'status',
            'name' => '查看', 'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'admin_web', 'source_name' => 'test']);
        $empty = $group(['name' => 'CMS 内容管理']);
        $outer = $group(['name' => '空的上级']);
        $inner = $group(['name' => '空的下级', 'pid' => $outer]);
        $menuBound = $group(['name' => '菜单目录']);
        Db::table('admin_menu')->insert(['pid' => 0, 'app_name' => 'admin', 'name' => '目录', 'href' => '/group-clean-test', 'query' => 'type=M', 'permission_id' => $menuBound,
            'status' => 1, 'source_type' => 'admin_web', 'source_name' => 'test']);

        $migration = new HostPermissionMigration();
        $migration->cleanGroups();
        $migration->cleanGroups();

        $row = Db::table('permission')->where('id', $coded)->first();
        self::assertNull($row->code, '目录行不能带权限码');
        self::assertSame(['', ''], [$row->obj, $row->act]);
        self::assertSame(0, Db::table('permission')->whereIn('id', [$empty, $outer, $inner])->count(), '没有子项的目录（含嵌套）删除');
        self::assertTrue(Db::table('permission')->where('id', $menuBound)->exists(), '仍被菜单引用的目录保留');
        self::assertSame(0, Db::table('permission')->where('resource_type', 'group')->whereNotNull('code')->count());
    }

    private function roleHas(int $roleId, string $code): bool
    {
        ['obj' => $obj, 'act' => $act] = PermissionCode::resource($code);
        return Db::table('casbin_rule')->where('ptype', 'p')->where('v0', 'role:' . $roleId)->where('v2', $obj)->where('v3', $act)->exists();
    }

    private static function map(): array
    {
        return require dirname(__DIR__) . '/database/permission-maps/host-per-method.php';
    }

    private static function before(): array
    {
        return json_decode((string) file_get_contents(__DIR__ . '/fixtures/auth-requirements-before.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
