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
        Db::table('plugin')->insert(['code' => 'sample', 'name' => '示例插件', 'version' => '1.0.0', 'status' => 1, 'lifecycle_state' => 'enabled', 'manifest' => '{}', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
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

    public function test_disabled_plugin_configuration_persists_to_file_and_registry(): void
    {
        $code = 'config' . bin2hex(random_bytes(5));
        $parent = base_path('plugins');
        $createdParent = !is_dir($parent);
        $directory = $parent . '/' . $code;
        (new \app\common\plugin\sdk\PluginScaffolder($parent))->scaffold($code);
        Db::table('plugin')->insert(['code' => $code, 'version' => '1.0.0', 'status' => 0, 'lifecycle_state' => 'disabled']);
        try {
            $service = new PluginCenterService();
            self::assertSame(20, $service->get($code)['page_size']['value']);
            $error = null;
            try { $service->save($code, ['page_size' => 7]); }
            catch (\Throwable $exception) { $error = $exception->getMessage(); }
            self::assertNull($error, (string) $error);
            self::assertSame(7, $service->get($code)['page_size']['value']);
            self::assertSame(7, json_decode(Db::table('plugin')->where('code', $code)->value('config'), true, 512, JSON_THROW_ON_ERROR)['page_size']);
            self::assertSame('disabled', Db::table('plugin')->where('code', $code)->value('lifecycle_state'));
        } finally {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            rmdir($directory);
            if ($createdParent) @rmdir($parent);
        }
    }
}
