<?php

declare(strict_types=1);

namespace Tests\Document;

use support\Db;

final class DocumentPluginLifecycleTest extends DocumentTestCase
{
    private function plugin(): object
    {
        require_once dirname(__DIR__, 2) . '/plugins/document/Plugin.php';
        return new \plugins\document\Plugin();
    }

    public function test_enabling_the_plugin_seeds_defaults_and_disabling_keeps_data(): void
    {
        $plugin = $this->plugin();
        self::assertTrue($plugin->enabled());
        self::assertGreaterThan(0, Db::table('documents')->count());
        $before = Db::table('documents')->count();

        self::assertTrue($plugin->disabled());
        self::assertSame($before, Db::table('documents')->count(), '停用不删除数据');

        self::assertTrue($plugin->enabled());
        self::assertSame($before, Db::table('documents')->count(), '重新启用不重复插入');
    }

    public function test_purge_is_not_supported_so_data_survives_uninstall(): void
    {
        $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/plugins/document/plugin.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($manifest['purge']['supported']);
        self::assertFalse($this->plugin()->purgeData());
    }
}
