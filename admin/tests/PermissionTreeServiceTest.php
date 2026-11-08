<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\model\Permission;
use app\admin\authorization\service\PermissionTreeService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/** 权限资源树由权限码推导：模块分组 → 资源分组 → 接口/能力权限。 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PermissionTreeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
    }

    protected function tearDown(): void
    {
        Db::rollBack();
    }

    /** @return array{0: object|null, 1: object|null} 资源分组、模块分组 */
    private static function ancestors(string $code): array
    {
        $row = Db::table('permission')->where('code', $code)->first();
        $resource = $row ? Db::table('permission')->where('id', $row->pid)->first() : null;
        $module = $resource ? Db::table('permission')->where('id', $resource->pid)->first() : null;
        return [$resource, $module];
    }

    public function test_fresh_install_has_only_module_groups_at_the_top_level(): void
    {
        $top = Db::table('permission')->where('pid', 0)->whereNull('deleted_at')->get();
        self::assertNotEmpty($top);
        foreach ($top as $row) self::assertSame(Permission::TYPE_GROUP, $row->resource_type, (string) $row->name);
        self::assertSame($top->count(), $top->unique('name')->count(), '模块分组不得重名');
    }

    public function test_every_coded_permission_sits_under_its_module_and_resource_group(): void
    {
        foreach (Db::table('permission')->whereNotNull('code')->whereNull('deleted_at')->get() as $row) {
            [$module, $resource] = explode(':', (string) $row->code);
            [$resourceGroup, $moduleGroup] = self::ancestors((string) $row->code);
            self::assertNotNull($resourceGroup, $row->code);
            self::assertSame(Permission::TYPE_GROUP, $resourceGroup->resource_type, $row->code);
            self::assertNotNull($moduleGroup, $row->code);
            self::assertSame(0, (int) $moduleGroup->pid, $row->code);
            self::assertSame(Permission::TYPE_GROUP, $moduleGroup->resource_type, $row->code);
            // 同一资源的权限共享同一个资源分组。
            $siblings = Db::table('permission')->where('code', 'like', $module . ':' . $resource . ':%')->whereNull('deleted_at')->pluck('pid')->unique()->all();
            self::assertSame([(int) $resourceGroup->id], array_map('intval', array_values($siblings)), $row->code);
        }
    }

    public function test_host_groups_use_readable_labels(): void
    {
        [$resource, $module] = self::ancestors('authorization:department:tree');
        self::assertSame('部门管理', $resource->name);
        self::assertSame('权限管理', $module->name);
        [$resource, $module] = self::ancestors('system:member:index');
        self::assertSame('会员管理', $resource->name);
        self::assertSame('系统管理', $module->name);
    }

    public function test_no_empty_groups_and_no_legacy_codes_remain(): void
    {
        $groups = Db::table('permission')->where('resource_type', Permission::TYPE_GROUP)->whereNull('deleted_at')->pluck('id')->all();
        foreach ($groups as $id) self::assertTrue(Db::table('permission')->where('pid', $id)->whereNull('deleted_at')->exists(), "空分组 #$id");
        self::assertFalse(Db::table('permission')->whereNotNull('code')->where('code', 'not like', '%:%:%')->exists(), '只允许三段式权限码');
    }

    public function test_organize_moves_new_rows_keeps_manual_rows_and_menu_groups_and_is_idempotent(): void
    {
        $service = new PermissionTreeService();
        self::assertSame(['created' => 0, 'moved' => 0, 'removed' => 0], $service->organize());
        $stray = Db::table('permission')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'code' => 'system:member:fixture', 'obj' => 'system/member', 'act' => 'fixture', 'name' => '新方法', 'resource_type' => 'route', 'status' => 1, 'source_type' => 'system', 'source_name' => '']);
        $manual = Db::table('permission')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'code' => 'custom:report:view', 'obj' => 'custom/report', 'act' => 'view', 'name' => '手工能力', 'resource_type' => 'capability', 'status' => 1, 'source_type' => 'manual', 'source_name' => '']);
        $menuGroup = Db::table('permission')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'name' => '插件目录', 'resource_type' => 'group', 'status' => 1, 'source_type' => 'plugin', 'source_name' => 'fixture']);
        Db::table('admin_menu')->insert(['pid' => 0, 'permission_id' => $menuGroup, 'app_name' => 'admin', 'name' => '插件目录', 'href' => '/plugin/fixture', 'query' => 'type=M', 'source_type' => 'plugin', 'source_name' => 'fixture']);
        $empty = Db::table('permission')->insertGetId(['pid' => 0, 'app_name' => 'admin', 'name' => '空分组', 'resource_type' => 'group', 'status' => 1, 'source_type' => 'system', 'source_name' => 'legacy']);

        $result = $service->organize();

        self::assertSame(1, $result['moved']);
        self::assertSame((int) Db::table('permission')->where('code', 'system:member:index')->value('pid'), (int) Db::table('permission')->where('id', $stray)->value('pid'));
        self::assertSame(0, (int) Db::table('permission')->where('id', $manual)->value('pid'), '手工权限保持管理员设定的位置');
        self::assertTrue(Db::table('permission')->where('id', $menuGroup)->exists(), '菜单引用的分组不得删除');
        self::assertFalse(Db::table('permission')->where('id', $empty)->exists(), '空分组应删除');
        self::assertSame(['created' => 0, 'moved' => 0, 'removed' => 0], $service->organize());
    }

    public function test_plugin_manifest_names_its_resource_groups(): void
    {
        Db::table('plugin')->insert(['code' => 'demo', 'name' => '演示插件', 'manifest' => json_encode(['adminWeb' => ['permissionGroups' => ['site' => '站点管理', 'sites' => '站点管理']]], JSON_UNESCAPED_UNICODE)]);
        foreach (['demo:site:edit' => 'capability', 'demo:sites:index' => 'route'] as $code => $type) {
            [$module, $resource, $action] = explode(':', $code);
            Db::table('permission')->insert(['pid' => 0, 'app_name' => 'admin', 'code' => $code, 'obj' => "$module/$resource", 'act' => $action, 'name' => $code, 'resource_type' => $type, 'status' => 1, 'source_type' => 'plugin', 'source_name' => 'demo']);
        }
        (new PermissionTreeService())->organize();
        [$capabilityGroup, $module] = self::ancestors('demo:site:edit');
        [$routeGroup] = self::ancestors('demo:sites:index');
        self::assertSame('演示插件', $module->name);
        self::assertSame('站点管理', $capabilityGroup->name);
        self::assertSame((int) $capabilityGroup->id, (int) $routeGroup->id, '同名分组合并站点操作与对应接口');
    }

    public function test_plugin_permissions_group_under_the_plugin_and_go_away_with_it(): void
    {
        $service = new PermissionTreeService();
        Db::table('permission')->insert(['pid' => 0, 'app_name' => 'admin', 'code' => 'demo:item:index', 'obj' => 'demo/item', 'act' => 'index', 'name' => '列表', 'resource_type' => 'route', 'status' => 1, 'source_type' => 'plugin', 'source_name' => 'demo']);
        $service->organize();
        [$resource, $module] = self::ancestors('demo:item:index');
        self::assertSame(['plugin', 'demo'], [$module->source_type, $module->source_name]);
        self::assertSame(['plugin', 'demo'], [$resource->source_type, $resource->source_name]);
        // 卸载按 source_type/source_name 清理插件权限，分组随之一并删除。
        Db::table('permission')->where('source_type', 'plugin')->where('source_name', 'demo')->delete();
        self::assertFalse(Db::table('permission')->whereIn('id', [$resource->id, $module->id])->exists());
    }
}
