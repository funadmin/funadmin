<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionSyncService;
use app\admin\plugin\service\PluginInfrastructureService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

/**
 * 代码即权限：插件安装/启用/升级时按控制器方法推导权限码并登记，不依赖已加载的路由表。
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PluginDerivedPermissionTest extends TestCase
{
    public function test_plugin_codes_derived_from_controller_files_match_the_registered_routes(): void
    {
        $sync = new PermissionSyncService();
        foreach (['cms', 'qa', 'example', 'market'] as $plugin) {
            $fromRoutes = array_keys($sync->derivedCodes($plugin));
            $fromFiles = $sync->pluginCodes($plugin);
            self::assertNotEmpty($fromFiles, $plugin);
            self::assertSame($fromRoutes, $fromFiles, $plugin);
        }
        self::assertSame([], $sync->pluginCodes('nonexistent'));
    }

    public function test_enabling_a_plugin_registers_its_derived_codes_and_retires_undeclared_ones(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        try {
            $derived = (new PermissionSyncService())->pluginCodes('qa');
            Db::table('permission')->whereIn('code', $derived)->delete();
            Db::table('permission')->insert(['app_name' => 'admin', 'code' => 'qa:legacy:gone', 'obj' => 'qa/legacy', 'act' => 'gone', 'name' => 'x',
                'resource_type' => 'route', 'status' => 1, 'is_public' => 0, 'source_type' => 'plugin', 'source_name' => 'qa']);
            $manifest = json_decode((string) file_get_contents(dirname(__DIR__) . '/plugins/qa/plugin.json'), true, flags: JSON_THROW_ON_ERROR);

            (new PluginInfrastructureService())->registerResources($manifest['adminWeb']['permissions'], $manifest['adminWeb']['menu'], 'qa');

            $active = Db::table('permission')->where('source_type', 'plugin')->where('source_name', 'qa')->where('status', 1)->whereIn('resource_type', ['route', 'capability'])->pluck('code')->all();
            sort($active);
            self::assertSame($derived, $active);
            self::assertSame('审核问答投稿', Db::table('permission')->where('code', 'qa:moderation:review')->value('name'));
            self::assertSame('route', Db::table('permission')->where('code', 'qa:moderation:review')->value('resource_type'));
            $menu = Db::table('admin_menu')->where('source_name', 'qa')->where('href', '/plugin/qa/moderation')->first();
            self::assertSame('qa:moderation:index', Db::table('permission')->where('id', $menu->permission_id)->value('code'));
        } finally {
            Db::rollBack();
        }
    }

    public function test_unnamed_derived_codes_default_to_the_openapi_summary_and_type_route(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        try {
            Db::table('permission')->where('code', 'market:order:close')->delete();
            Db::table('permission')->where('code', 'market:order:detail')->update(['name' => 'market:order:detail', 'resource_type' => 'capability']);
            (new PermissionSyncService())->sync('market');
            self::assertSame('关闭指定市场订单', Db::table('permission')->where('code', 'market:order:close')->value('name'));
            self::assertSame('按订单号获取市场订单详情', Db::table('permission')->where('code', 'market:order:detail')->value('name'));
            self::assertSame('route', Db::table('permission')->where('code', 'market:order:detail')->value('resource_type'));
        } finally {
            Db::rollBack();
        }
    }

    public function test_manifest_code_without_a_controller_method_is_a_capability_permission(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        try {
            (new PluginInfrastructureService())->registerResources([['code' => 'qa:moderation:export-all', 'name' => '导出全部'], ['code' => 'qa:moderation:index', 'name' => '查看问答投稿']], [], 'qa');
            self::assertSame('capability', Db::table('permission')->where('code', 'qa:moderation:export-all')->value('resource_type'));
            self::assertSame('route', Db::table('permission')->where('code', 'qa:moderation:index')->value('resource_type'));
            self::assertSame('查看问答投稿', Db::table('permission')->where('code', 'qa:moderation:index')->value('name'));
        } finally {
            Db::rollBack();
        }
    }
}
