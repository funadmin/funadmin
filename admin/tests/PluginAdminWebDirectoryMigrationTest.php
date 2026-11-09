<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

/** 004 插件前端目录迁移：发布登记从 src/admin/modules/{code} 改为 src/admin/plugins/{code}；核心 SQL 与 PHP 两条链路终态一致。 */
final class PluginAdminWebDirectoryMigrationTest extends TestCase
{
    private const MIGRATION = '004_plugin_admin_web_directory';

    public function test_php_migration_is_recorded_by_the_migration_runner(): void
    {
        (new MigrationService())->run();
        self::assertTrue(Db::table('migrations')->where('name', self::MIGRATION . '.php')->exists());
    }

    public function test_php_migration_moves_only_legacy_admin_web_records_idempotently(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $row = static fn (string $source, string $target): int => (int) Db::table('plugin_resource')->insertGetId([
                'plugin_code' => 'demo', 'version' => '1.0.0', 'resource_type' => 'file', 'publication_unit' => null,
                'operation_token' => str_repeat('a', 32), 'source_path' => $source, 'target_path' => $target,
                'sha256' => str_repeat('b', 64), 'tree_hash' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $legacy = $row('web/src/admin/modules/demo/pages/Index.vue', 'web:src/admin/modules/demo/pages/Index.vue');
            $current = $row('web/src/admin/plugins/demo/api.ts', 'web:src/admin/plugins/demo/api.ts');
            $public = $row('public/plugin-assets/demo/src/admin/modules/a.css', 'public:plugin-assets/demo/src/admin/modules/a.css');
            $lookalike = $row('web/src/admin/modules-old/demo/x.ts', 'web:src/admin/modules-old/demo/x.ts');

            $migration = require base_path() . '/database/migrations/' . self::MIGRATION . '.php';
            $migration();
            $snapshot = Db::table('plugin_resource')->whereIn('id', [$legacy, $current, $public, $lookalike])->orderBy('id')->get(['source_path', 'target_path'])->map(fn ($r) => (array) $r)->all();
            $migration();
            self::assertSame($snapshot, Db::table('plugin_resource')->whereIn('id', [$legacy, $current, $public, $lookalike])->orderBy('id')->get(['source_path', 'target_path'])->map(fn ($r) => (array) $r)->all(), '重复执行终态不变');

            self::assertSame([
                ['source_path' => 'web/src/admin/plugins/demo/pages/Index.vue', 'target_path' => 'web:src/admin/plugins/demo/pages/Index.vue'],
                ['source_path' => 'web/src/admin/plugins/demo/api.ts', 'target_path' => 'web:src/admin/plugins/demo/api.ts'],
                ['source_path' => 'public/plugin-assets/demo/src/admin/modules/a.css', 'target_path' => 'public:plugin-assets/demo/src/admin/modules/a.css'],
                ['source_path' => 'web/src/admin/modules-old/demo/x.ts', 'target_path' => 'web:src/admin/modules-old/demo/x.ts'],
            ], $snapshot);
            self::assertSame(str_repeat('b', 64), Db::table('plugin_resource')->where('id', $legacy)->value('sha256'), '只改路径，不改哈希');
        } finally {
            Db::rollBack();
        }
    }

    public function test_core_sql_is_forward_only_and_rewrites_both_path_columns(): void
    {
        $file = base_path() . '/database/core/migrations/' . self::MIGRATION . '.sql';
        self::assertFileExists($file);
        $sql = (string) file_get_contents($file);
        (new \ReflectionMethod(MigrationService::class, 'assertForwardOnly'))->invoke(new MigrationService(), $sql, $file);
        $code = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        self::assertMatchesRegularExpression('/UPDATE `fun_plugin_resource`/', $code);
        foreach (["'web:src/admin/modules/%'", "'web:src/admin/plugins/'", "'web/src/admin/modules/%'", "'web/src/admin/plugins/'"] as $needle) {
            self::assertStringContainsString($needle, $code);
        }
    }
}
