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
        foreach (['cms', 'qa', 'example'] as $plugin) {
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
            Db::table('permission')->where('code', 'example:test:index')->delete();
            Db::table('permission')->where('code', 'example:test:detail')->update(['name' => 'example:test:detail', 'resource_type' => 'capability']);
            (new PermissionSyncService())->sync('example');
            self::assertSame('获取示例插件测试记录列表', Db::table('permission')->where('code', 'example:test:index')->value('name'));
            self::assertSame('获取指定示例插件测试记录的详情', Db::table('permission')->where('code', 'example:test:detail')->value('name'));
            self::assertSame('route', Db::table('permission')->where('code', 'example:test:detail')->value('resource_type'));
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

    public function test_plugin_permission_cannot_point_at_another_resource(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        try {
            $this->expectExceptionMessage('插件权限不能指定其它资源');
            (new PluginInfrastructureService())->registerResources([['code' => 'qa:moderation:borrow', 'name' => 'x', 'obj' => 'system/config', 'act' => 'update']], [], 'qa');
        } finally {
            Db::rollBack();
        }
    }

    public function test_host_sync_refuses_to_turn_a_capability_into_a_route(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        try {
            $code = 'authorization:role:index';
            Db::table('permission')->where('code', $code)->update(['resource_type' => 'capability']);
            $this->expectExceptionMessage('权限码归属冲突：' . $code);
            (new PermissionSyncService())->sync();
        } finally {
            Db::rollBack();
        }
    }

    public function test_codes_come_from_the_controller_file_even_when_an_older_class_is_already_loaded(): void
    {
        $app = sys_get_temp_dir() . '/funadmin-stale-' . bin2hex(random_bytes(4));
        mkdir($app . '/admin/plugins/stalefix/controller', 0700, true);
        $file = $app . '/admin/plugins/stalefix/controller/Items.php';
        $source = static fn (string $methods): string => "<?php\nnamespace app\\admin\\plugins\\stalefix\\controller;\nuse support\\annotation\\route\\Get;\nuse support\\annotation\\route\\Post;\n"
            . "#[\\support\\annotation\\route\\RouteGroup('/admin/plugin/stalefix/items')]\nfinal class Items extends \\app\\common\\controller\\AdminController\n{\n$methods}\n";
        file_put_contents($file, $source("    #[Get('')]\n    public function index(): \\support\\Response { return \$this->ok(); }\n"));
        require $file;
        // 插件升级：同一 worker 内类已加载，文件新增了方法（含 OpenAPI summary）与一个不注册路由的方法。
        file_put_contents($file, $source("    #[Get('')]\n    public function index(): \\support\\Response { return \$this->ok(); }\n"
            . "    /**\n     * @OA\\Post(path=\"/x\", summary=\"关闭条目。\")\n     */\n    #[Post('/{id}/close')]\n    public function closeItem(int \$id): \\support\\Response { return \$this->ok(); }\n"
            . "    #[Get]\n    public function hidden(): \\support\\Response { return \$this->ok(); }\n    public function helper(): void {}\n"));
        $sync = new PermissionSyncService();
        try {
            self::assertSame(['stalefix:items:close-item', 'stalefix:items:index'], $sync->pluginCodes('stalefix', $app));
            self::assertSame('关闭条目', $sync->summary('stalefix:items:close-item'));
        } finally {
            unlink($file);
            rmdir($app . '/admin/plugins/stalefix/controller'); rmdir($app . '/admin/plugins/stalefix'); rmdir($app . '/admin/plugins'); rmdir($app . '/admin'); rmdir($app);
        }
    }

    public function test_controllers_in_sub_directories_derive_their_own_codes(): void
    {
        $app = sys_get_temp_dir() . '/funadmin-subdir-' . bin2hex(random_bytes(4));
        mkdir($app . '/admin/plugins/subfix/controller/admin', 0700, true);
        $file = $app . '/admin/plugins/subfix/controller/admin/OrderController.php';
        file_put_contents($file, "<?php\nnamespace app\\admin\\plugins\\subfix\\controller\\admin;\nuse support\\annotation\\route\\Post;\nfinal class OrderController\n{\n    #[Post('/admin/plugin/subfix/order/close')]\n    public function close() {}\n}\n");
        try {
            self::assertSame(['subfix:admin-order:close'], (new PermissionSyncService())->pluginCodes('subfix', $app));
        } finally {
            unlink($file);
            foreach (['/admin/plugins/subfix/controller/admin', '/admin/plugins/subfix/controller', '/admin/plugins/subfix', '/admin/plugins', '/admin', ''] as $dir) rmdir($app . $dir);
        }
    }
}
