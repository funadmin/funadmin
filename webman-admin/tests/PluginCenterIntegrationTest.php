<?php

declare(strict_types=1);

namespace Tests;

use app\admin\plugin\service\PluginCenterService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

final class PluginCenterIntegrationTest extends TestCase
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

    public function test_installed_plugin_serialization_reads_operation_history_with_eloquent_queries(): void
    {
        Db::table('plugin')->insert(['code' => 'sample', 'name' => '示例插件', 'version' => '1.0.0', 'status' => 1, 'lifecycle_state' => 'enabled', 'needs_reinstall' => 0, 'manifest' => '{}', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        Db::table('plugin_operation')->insert(['plugin_code' => 'sample', 'operation' => 'install', 'status' => 'success', 'stage' => 'done', 'progress' => 100, 'operation_token' => 'sample-operation', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
        self::assertCount(1, (new PluginCenterService())->installed());
    }

    public function test_plugin_center_discovery_and_installed_queries_use_webman_eloquent_without_plugin_records(): void
    {
        self::assertTrue(Db::schema()->hasTable('plugin'), '插件生命周期表必须由核心基线提供');
        $service = new PluginCenterService();
        self::assertIsArray($service->installed());
        self::assertIsArray($service->discovered());
    }
}
