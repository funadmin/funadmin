<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\CasbinService;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\TestCase;
use support\Db;

/** 002 代码生成器迁移：菜单改名、登记两个新接口权限并按原有权限授予；核心 SQL 与 PHP 两条链路终态一致。 */
final class CodeGeneratorMigrationTest extends TestCase
{
    private const SIBLING = 'development:business:preview-formal-generation';
    private const SYNC = 'development:business:database-sync';
    private const ARCHIVE = 'development:business:formal-generation-archive';
    private const OBJ = 'development/business';

    public function test_php_migration_is_recorded_by_the_migration_runner(): void
    {
        (new MigrationService())->run();
        self::assertTrue(Db::table('migrations')->where('name', '002_code_generator.php')->exists());
        foreach ([self::SYNC, self::ARCHIVE] as $code) {
            self::assertSame(1, Db::table('permission')->where('code', $code)->count(), $code);
        }
    }

    public function test_php_migration_renames_menu_registers_permissions_and_grants_holders_idempotently(): void
    {
        (new MigrationService())->run();
        Db::beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            // 模拟 MySQL 基线形态：业务开发权限挂在 business_development 分组下。
            $groupId = (int) Db::table('permission')->insertGetId([
                'pid' => 0, 'app_name' => 'admin', 'code' => null, 'obj' => '', 'act' => '', 'name' => '业务开发', 'resource_type' => 'group',
                'status' => 1, 'is_public' => 0, 'source_type' => 'admin_web', 'source_name' => 'business_development', 'sort_order' => 20,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            Db::table('permission')->where('code', self::SIBLING)->update(['pid' => $groupId, 'source_type' => 'admin_web', 'source_name' => 'business_development']);
            // database-sync 已由代码同步登记（pid=0、system、停用）；archive 尚未登记。
            Db::table('permission')->where('code', self::SYNC)->update(['pid' => 0, 'source_type' => 'system', 'source_name' => '', 'status' => 0]);
            Db::table('permission')->where('code', self::ARCHIVE)->delete();

            Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/development/business/mine')->delete();
            $menu = [
                'pid' => 0, 'permission_id' => 0, 'app_name' => 'admin', 'name' => '业务开发', 'href' => '/development/business/mine',
                'query' => 'component=development/business/mine&name=BusinessDevelopment&type=C&keepAlive=1&permission=development:business:modules',
                'target' => '_self', 'icon' => 'i-ep-briefcase', 'status' => 1, 'source_type' => 'admin_web', 'source_name' => 'business_development',
                'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now,
            ];
            if (!Db::table('admin_menu')->where('id', 42)->exists()) $menu['id'] = 42;
            $menuId = (int) Db::table('admin_menu')->insertGetId($menu);
            $decoyId = (int) Db::table('admin_menu')->insertGetId(['href' => '/plugin-demo/business/mine', 'source_type' => 'plugin', 'source_name' => 'demo'] + array_diff_key($menu, ['id' => true]));

            $grant = static function (int $role, string $act): void {
                Db::table('casbin_rule')->insert(CasbinService::ruleRow('p', ['role:' . $role, 'default', self::OBJ, $act]));
            };
            $grant(9101, 'inspect-database');
            $grant(9102, 'generate');
            $grant(9103, 'inspect-database');
            $grant(9103, 'generate');
            $grant(9104, 'preview-formal-generation');
            $casbinBefore = Db::table('casbin_rule')->count();

            $migration = require base_path() . '/database/migrations/002_code_generator.php';
            $migration();
            $afterFirst = [Db::table('permission')->count(), Db::table('casbin_rule')->count(), Db::table('admin_menu')->count()];
            $migration();
            self::assertSame($afterFirst, [Db::table('permission')->count(), Db::table('casbin_rule')->count(), Db::table('admin_menu')->count()], '重复执行不得新增行');

            self::assertSame('代码生成', Db::table('admin_menu')->where('id', $menuId)->value('name'));
            self::assertSame('业务开发', Db::table('admin_menu')->where('id', $decoyId)->value('name'), '只改核心业务开发菜单');

            $sibling = Db::table('permission')->where('code', self::SIBLING)->first();
            foreach ([self::SYNC => ['database-sync', '同步表结构'], self::ARCHIVE => ['formal-generation-archive', '下载生成代码']] as $code => [$act, $name]) {
                $rows = Db::table('permission')->where('code', $code)->get();
                self::assertCount(1, $rows, $code);
                $row = $rows[0];
                self::assertSame([1, 'route', 'admin', self::OBJ, $act, $name, (int) $sibling->pid, 'admin_web', 'business_development', null],
                    [(int) $row->status, $row->resource_type, $row->app_name, $row->obj, $row->act, $row->name, (int) $row->pid, $row->source_type, $row->source_name, $row->deleted_at], $code);
            }

            $acts = static fn (int $role): array => Db::table('casbin_rule')->where('ptype', 'p')->where('v0', 'role:' . $role)
                ->orderBy('v3')->pluck('v3')->all();
            self::assertSame(['database-sync', 'inspect-database'], $acts(9101));
            self::assertSame(['formal-generation-archive', 'generate'], $acts(9102));
            self::assertSame(['database-sync', 'formal-generation-archive', 'generate', 'inspect-database'], $acts(9103));
            self::assertSame(['preview-formal-generation'], $acts(9104), '其他角色不变');
            self::assertSame($casbinBefore + 4, Db::table('casbin_rule')->count());
            $expected = CasbinService::ruleRow('p', ['role:9103', 'default', self::OBJ, 'formal-generation-archive']);
            self::assertTrue(Db::table('casbin_rule')->where($expected)->exists(), '授权行与 syncRolePermissions 的 rule_hash 一致');
        } finally {
            Db::rollBack();
        }
    }

    public function test_core_sql_is_forward_only_idempotent_and_matches_php_names(): void
    {
        $file = base_path() . '/database/core/migrations/002_code_generator.sql';
        self::assertFileExists($file);
        $sql = (string) file_get_contents($file);
        $method = new \ReflectionMethod(MigrationService::class, 'assertForwardOnly');
        $method->invoke(new MigrationService(), $sql, $file);
        $code = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        self::assertDoesNotMatchRegularExpression('/[^\x00-\x7F]/', $code, '语句中的中文必须使用 UTF-8 十六进制字面量');
        self::assertStringContainsString("HEX(`name`)='" . strtoupper(bin2hex('业务开发')) . "'", $code, '只改名称仍为「业务开发」的菜单');
        foreach (['代码生成', '同步表结构', '下载生成代码'] as $text) {
            self::assertStringContainsString("CONVERT(X'" . strtoupper(bin2hex($text)) . "' USING utf8mb4)", $code, $text);
        }
        foreach ([self::SYNC, self::ARCHIVE, self::SIBLING, "'inspect-database'", "'generate'", 'business_development', '/development/business/mine'] as $needle) {
            self::assertStringContainsString($needle, $code);
        }
        self::assertDoesNotMatchRegularExpression('/INSERT\s+IGNORE/i', $code);
        foreach (preg_split('/;\s*$/m', $code) ?: [] as $statement) {
            if (preg_match('/^\s*INSERT\b/i', $statement) === 1) self::assertMatchesRegularExpression('/WHERE\s+NOT\s+EXISTS|AND\s+NOT\s+EXISTS/i', $statement, '插入必须幂等');
        }
    }
}
