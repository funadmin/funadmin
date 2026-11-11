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

    // ---- 生成表遵循软删除规则（plugin soft-delete 计划第 5 项） ----

    /** @param list<array<string, mixed>> $extraFields */
    private static function generatorContext(string $entity, array $extraFields = [], array $primary = [], bool $softDeletes = true): array
    {
        $data = \Tests\Console\CrudDefinitionFixture::make($entity)->toArray();
        if ($primary !== []) $data['fields'][0] = array_merge($data['fields'][0], $primary);
        foreach ($extraFields as $field) $data['fields'][] = $field;
        $data['softDeletes'] = $softDeletes;
        return \app\common\crud\ProductionTemplateContext::build(\app\common\crud\CrudDefinition::fromArray($data), ['modelNamespace' => 'Tests\\GeneratedSoftDelete']);
    }

    private const UNIQUE_CODE = ['name' => 'code', 'label' => '编码', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
    private const UUID_PRIMARY = ['dbType' => 'char(36)', 'format' => 'uuid'];

    public function test_generated_create_sql_indexes_deleted_at_and_skips_token_without_unique_fields(): void
    {
        $context = self::generatorContext('gen-plain');
        $sql = $context['migrationContent'];
        self::assertStringContainsString('`deleted_at` datetime NULL', $sql);
        self::assertStringContainsString('KEY `idx_gen_plain_deleted_at` (`deleted_at`)', $sql);
        self::assertStringNotContainsString('deleted_token', $sql);
        self::assertStringNotContainsString('row_no', $sql);
        self::assertStringNotContainsString('deleted_token', $context['modelContent']);
    }

    public function test_generated_create_sql_adds_token_and_token_suffixed_unique_keys_with_unique_fields(): void
    {
        $second = ['name' => 'slug', 'label' => '别名', 'dbType' => 'varchar(40)', 'nullable' => false, 'component' => 'input', 'unique' => true];
        $context = self::generatorContext('gen-unique', [self::UNIQUE_CODE, $second]);
        $sql = $context['migrationContent'];
        self::assertStringContainsString('`deleted_token` bigint unsigned NOT NULL DEFAULT 0', $sql);
        self::assertStringContainsString('UNIQUE KEY `uk_gen_unique_code` (`code`, `deleted_token`)', $sql);
        self::assertStringContainsString('UNIQUE KEY `uk_gen_unique_slug` (`slug`, `deleted_token`)', $sql);
        self::assertStringContainsString('KEY `idx_gen_unique_deleted_at` (`deleted_at`)', $sql);
        self::assertStringNotContainsString('row_no', $sql, '整数主键直接用主键作为令牌来源');
        self::assertStringContainsString("protected \$hidden = ['deleted_token'];", $context['modelContent']);
        self::assertStringNotContainsString('getDeletedTokenSourceColumn', $context['modelContent']);
    }

    public function test_generated_uuid_table_with_unique_field_gets_row_no_token_source(): void
    {
        $context = self::generatorContext('gen-uuid', [self::UNIQUE_CODE], self::UUID_PRIMARY);
        $sql = $context['migrationContent'];
        self::assertStringContainsString('`row_no` bigint unsigned NOT NULL AUTO_INCREMENT', $sql);
        self::assertStringContainsString('KEY `idx_gen_uuid_row_no` (`row_no`)', $sql);
        self::assertStringContainsString('`id` char(36) NOT NULL,', $sql, 'UUID 主键本身不自增');
        self::assertStringContainsString('UNIQUE KEY `uk_gen_uuid_code` (`code`, `deleted_token`)', $sql);
        self::assertStringContainsString("protected \$hidden = ['deleted_token', 'row_no'];", $context['modelContent']);
        self::assertMatchesRegularExpression("/function getDeletedTokenSourceColumn\(\): string\s*\{\s*return 'row_no';/", $context['modelContent']);
    }

    public function test_generated_uuid_table_without_unique_field_has_no_row_no_or_token(): void
    {
        $context = self::generatorContext('gen-uuid-plain', [], self::UUID_PRIMARY);
        self::assertStringNotContainsString('row_no', $context['migrationContent']);
        self::assertStringNotContainsString('deleted_token', $context['migrationContent']);
        self::assertStringNotContainsString('getDeletedTokenSourceColumn', $context['modelContent']);
    }

    public function test_generated_table_without_soft_deletes_keeps_plain_unique_keys(): void
    {
        $context = self::generatorContext('gen-hard', [self::UNIQUE_CODE], [], false);
        $sql = $context['migrationContent'];
        self::assertStringNotContainsString('deleted_', $sql);
        self::assertStringContainsString('UNIQUE KEY `uk_gen_hard_code` (`code`)', $sql);
    }

    public function test_field_inference_treats_soft_delete_columns_as_managed(): void
    {
        $fields = (new \app\common\crud\FieldInference())->infer(['primaryKey' => ['id'], 'columns' => [
            ['name' => 'id', 'type' => 'bigint unsigned', 'nullable' => false],
            ['name' => 'deleted_at', 'type' => 'datetime', 'nullable' => true],
            ['name' => 'deleted_token', 'type' => 'bigint unsigned', 'nullable' => false],
            ['name' => 'row_no', 'type' => 'bigint unsigned', 'nullable' => false],
        ]]);
        $byName = array_column($fields, null, 'name');
        foreach (['deleted_at', 'deleted_token', 'row_no'] as $name) {
            self::assertTrue($byName[$name]['managed'], $name);
            self::assertFalse($byName[$name]['writable'], $name);
            self::assertFalse($byName[$name]['form'], $name);
        }
        foreach (['deleted_token', 'row_no'] as $name) {
            self::assertSame('integer', $byName[$name]['valueType'], $name);
            self::assertFalse($byName[$name]['list'], $name);
            self::assertFalse($byName[$name]['detail'], $name);
        }
    }
}
