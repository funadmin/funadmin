<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\CoreSchemaPlan;
use PHPUnit\Framework\TestCase;

final class CoreSchemaPlanTest extends TestCase
{
    private function reports(): array
    {
        $column = ['COLUMN_TYPE' => 'bigint unsigned', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLLATION_NAME' => null];
        $schema = ['columns' => ['id' => $column], 'indexes' => ['PRIMARY' => [['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'id', 'SUB_PART' => null]]], 'constraints' => [], 'create_sql' => 'CREATE TABLE `fun_demo` (`id` bigint unsigned NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB'];
        $hashes = ['001_baseline.sql' => str_repeat('a', 64)];
        return [
            ['database' => 'reference', 'prefix' => 'fun_', 'source_checksums' => $hashes, 'schema' => ['fun_demo' => $schema]],
            ['database' => 'target', 'prefix' => 'custom_', 'server_identity' => ['uuid' => 'local-server'], 'source_checksums' => $hashes, 'schema' => [], 'migration_history' => [], 'counts' => [], 'menus' => []],
        ];
    }

    public function test_missing_table_plan_has_target_prefix_and_never_contains_seed_writes(): void
    {
        self::assertTrue(class_exists(CoreSchemaPlan::class), '缺少存量结构接管计划器');
        [$reference, $actual] = $this->reports();
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('target', $plan['database']);
        self::assertSame('custom_', $plan['prefix']);
        self::assertSame(['demo'], $plan['missing_tables']);
        self::assertCount(1, $plan['operations']);
        self::assertStringContainsString('`custom_demo`', $plan['operations'][0]['sql']);
        self::assertStringNotContainsString('INSERT', $plan['operations'][0]['sql']);
        self::assertFalse($plan['apply_allowed'], '结构计划不能冒充已完成备份和种子预检');
    }

    public function test_existing_column_conflict_is_blocked_without_producing_modify_or_drop(): void
    {
        self::assertTrue(class_exists(CoreSchemaPlan::class), '缺少存量结构接管计划器');
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $actual['schema']['custom_demo']['columns']['id']['COLUMN_TYPE'] = 'varchar(64)';
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertNotEmpty($plan['blockers']);
        self::assertSame([], $plan['operations']);
        self::assertFalse($plan['apply_allowed']);
    }

    public function test_snapshot_binding_rejects_source_database_server_schema_or_data_drift(): void
    {
        self::assertTrue(class_exists(CoreSchemaPlan::class), '缺少存量结构接管计划器');
        [$reference, $actual] = $this->reports();
        $plan = CoreSchemaPlan::build($reference, $actual);
        CoreSchemaPlan::assertCurrent($plan, $reference, $actual);
        foreach (['database', 'server_identity', 'source_checksums', 'schema', 'counts', 'menus', 'migration_history', 'protected_row_hashes'] as $field) {
            $changed = $actual;
            $changed[$field] = $field === 'database' ? 'another' : ['changed' => true];
            try {
                CoreSchemaPlan::assertCurrent($plan, $reference, $changed);
                self::fail('未拒绝目标漂移：' . $field);
            } catch (\RuntimeException $error) {
                self::assertStringContainsString('漂移', $error->getMessage());
            }
        }
        $tampered = $plan;
        $tampered['operations'][0]['sql'] = 'DROP TABLE custom_demo';
        $this->expectException(\RuntimeException::class);
        CoreSchemaPlan::assertCurrent($tampered, $reference, $actual);
    }

    public function test_nullable_missing_column_produces_additive_sql_but_required_column_is_blocked(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $column = ['COLUMN_TYPE' => 'varchar(64)', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema']['fun_demo']['columns']['label'] = $column;
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  `label` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('add_column', $plan['operations'][0]['kind']);
        self::assertStringContainsString('ALTER TABLE `custom_demo` ADD COLUMN `label` varchar(64)', $plan['operations'][0]['sql']);
        $reference['schema']['fun_demo']['columns']['label']['IS_NULLABLE'] = 'NO';
        $reference['schema']['fun_demo']['create_sql'] = str_replace('DEFAULT NULL', 'NOT NULL', $reference['schema']['fun_demo']['create_sql']);
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['operations']);
        self::assertNotEmpty($plan['blockers']);
    }

    public function test_empty_prefix_does_not_rename_column_matching_another_table_name(): void
    {
        [$reference, $actual] = $this->reports();
        $reference['prefix'] = '';
        $reference['schema'] = ['demo' => ['columns' => [], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `demo` (\n  `scope` varchar(32) DEFAULT NULL\n) ENGINE=InnoDB"], 'scope' => ['columns' => [], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `scope` (\n  `id` int NOT NULL\n) ENGINE=InnoDB"]];
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertStringContainsString('CREATE TABLE `custom_demo`', $plan['operations'][0]['sql']);
        self::assertStringContainsString('`scope` varchar(32)', $plan['operations'][0]['sql']);
        self::assertStringNotContainsString('`custom_scope` varchar', $plan['operations'][0]['sql']);
    }

    public function test_ordinary_index_is_additive_but_unique_index_requires_duplicate_preflight(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $reference['schema']['fun_demo']['indexes']['idx_demo'] = [['NON_UNIQUE' => 1, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'id', 'SUB_PART' => null]];
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  PRIMARY KEY (`id`),\n  KEY `idx_demo` (`id`)\n) ENGINE=InnoDB";
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('add_index', $plan['operations'][0]['kind']);
        self::assertStringContainsString('ADD KEY `idx_demo` (`id`)', $plan['operations'][0]['sql']);
        $reference['schema']['fun_demo']['indexes']['idx_demo'][0]['NON_UNIQUE'] = 0;
        $reference['schema']['fun_demo']['create_sql'] = str_replace('KEY `idx_demo`', 'UNIQUE KEY `idx_demo`', $reference['schema']['fun_demo']['create_sql']);
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('add_unique_index', $plan['operations'][0]['kind']);
        self::assertNotEmpty($plan['preflight']);
        self::assertFalse($plan['apply_allowed']);
    }

    public function test_historical_checksum_drift_blocks_plan_even_when_schema_matches(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['migration_history'] = ['system_migration' => [['scope' => 'core', 'version' => '001_baseline', 'checksum' => str_repeat('b', 64)]]];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('checksum');
        CoreSchemaPlan::build($reference, $actual);
    }

    public function test_unsafe_create_and_unreviewed_required_column_never_reach_operations(): void
    {
        [$reference, $actual] = $this->reports();
        $reference['schema']['fun_demo']['create_sql'] .= '; DROP TABLE `custom_admin`';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('不安全');
        CoreSchemaPlan::build($reference, $actual);
    }

    public function test_deferred_foreign_keys_follow_all_creates_without_disabling_checks(): void
    {
        [$reference, $actual] = $this->reports();
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  PRIMARY KEY (`id`),\n  CONSTRAINT `fk_demo` FOREIGN KEY (`id`) REFERENCES `fun_owner` (`id`) ON DELETE CASCADE\n) ENGINE=InnoDB";
        $reference['schema']['fun_owner'] = ['columns' => [], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `fun_owner` (\n  `id` bigint unsigned NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB"];
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame(['create_table', 'create_table', 'add_foreign_key'], array_column($plan['operations'], 'kind'));
        self::assertStringNotContainsString('CONSTRAINT', $plan['operations'][0]['sql']);
        self::assertStringContainsString('REFERENCES `custom_owner`', $plan['operations'][2]['sql']);
        self::assertStringNotContainsString('FOREIGN_KEY_CHECKS', json_encode($plan['operations']));
    }

    public function test_index_alias_is_reviewed_only_when_unique_column_order_and_prefix_are_identical(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $unique = [['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'id', 'SUB_PART' => null]];
        $reference['schema']['fun_demo']['indexes']['uk_demo_id'] = $unique;
        $actual['schema']['custom_demo']['indexes']['demo_id_unique'] = $unique;
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame([], $plan['operations'], '等价索引不得重复创建');
        self::assertNotEmpty($plan['reviewed_differences']);
        $actual['schema']['custom_demo']['indexes']['demo_id_unique'][0]['NON_UNIQUE'] = 1;
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '普通索引不能代替唯一约束');
        $actual['schema']['custom_demo']['indexes']['demo_id_unique'] = $unique;
        $actual['schema']['custom_demo']['indexes']['demo_id_unique'][0]['SUB_PART'] = 10;
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '前缀索引不能代替完整唯一约束');
    }

    public function test_primary_key_difference_is_not_silently_treated_as_an_alias(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $actual['schema']['custom_demo']['indexes']['PRIMARY'][0]['COLUMN_NAME'] = 'surrogate_id';
        $actual['schema']['custom_demo']['indexes']['id_unique'] = $reference['schema']['fun_demo']['indexes']['PRIMARY'];
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_missing_unique_index_is_planned_only_with_explicit_duplicate_check(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $reference['schema']['fun_demo']['indexes']['uk_id'] = [['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'id', 'SUB_PART' => null]];
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  PRIMARY KEY (`id`),\n  UNIQUE KEY `uk_id` (`id`)\n) ENGINE=InnoDB";
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('add_unique_index', $plan['operations'][0]['kind']);
        self::assertSame('unique_rows', $plan['preflight'][0]['kind']);
        self::assertSame(['id'], $plan['preflight'][0]['columns']);
        self::assertFalse($plan['apply_allowed']);
    }

    public function test_only_lossless_text_capacity_expansion_is_planned_without_changing_nullability_or_default(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $expected = ['COLUMN_TYPE' => 'varchar(250)', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '', 'EXTRA' => '', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema']['fun_demo']['columns']['label'] = $expected;
        $actual['schema']['custom_demo']['columns']['label'] = array_replace($expected, ['COLUMN_TYPE' => 'varchar(120)']);
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  `label` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('widen_column', $plan['operations'][0]['kind']);
        self::assertStringContainsString('MODIFY COLUMN `label` varchar(250)', $plan['operations'][0]['sql']);
        $actual['schema']['custom_demo']['columns']['label']['COLUMN_TYPE'] = 'varchar(500)';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '禁止缩窄已有字符串');
        $actual['schema']['custom_demo']['columns']['label'] = array_replace($expected, ['COLUMN_TYPE' => 'varchar(120)', 'COLLATION_NAME' => 'ascii_bin']);
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '不能同时猜测字符集变更');
    }

    public function test_generated_namespace_column_is_planned_from_exact_show_create_expression(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $reference['schema']['fun_demo']['columns']['ns'] = ['COLUMN_TYPE' => 'varchar(190)', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null, 'EXTRA' => 'STORED GENERATED', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  `ns` varchar(190) GENERATED ALWAYS AS (substring_index(`id`,'.',1)) STORED NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertStringContainsString('GENERATED ALWAYS AS', $plan['operations'][0]['sql']);
    }

    public function test_changed_check_constraint_blocks_plan_instead_of_silently_disappearing(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $reference['schema']['fun_demo']['checks'] = ['ck_positive' => ['CHECK_CLAUSE' => '(`id` > 0)', 'ENFORCED' => 'YES']];
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame('CHECKS_REQUIRES_REVIEW', $plan['blockers'][0]['code'] ?? null);
        self::assertSame([], $plan['operations']);
    }

    public function test_obsolete_reference_audit_format_requires_fresh_replay(): void
    {
        [$reference, $actual] = $this->reports();
        $reference['audit_version'] = 1;
        $actual['audit_version'] = 2;
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('审计格式');
        CoreSchemaPlan::build($reference, $actual);
    }

    public function test_missing_foreign_key_requires_orphan_check_and_exact_column_compatibility(): void
    {
        [$reference, $actual] = $this->reports();
        $table = $reference['schema']['fun_demo'];
        $table['constraints']['fk_owner'] = [['COLUMN_NAME' => 'id', 'REFERENCED_TABLE_NAME' => 'fun_owner', 'REFERENCED_COLUMN_NAME' => 'id', 'ORDINAL_POSITION' => 1, 'DELETE_RULE' => 'RESTRICT', 'UPDATE_RULE' => 'CASCADE']];
        $table['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  PRIMARY KEY (`id`),\n  CONSTRAINT `fk_owner` FOREIGN KEY (`id`) REFERENCES `fun_owner` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE\n) ENGINE=InnoDB";
        $reference['schema']['fun_demo'] = $table;
        $reference['schema']['fun_owner'] = $this->reports()[0]['schema']['fun_demo'];
        $actual['schema']['custom_demo'] = $table;
        $actual['schema']['custom_demo']['constraints'] = [];
        $actual['schema']['custom_owner'] = $reference['schema']['fun_owner'];
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('add_foreign_key', $plan['operations'][0]['kind']);
        self::assertStringContainsString('REFERENCES `custom_owner`', $plan['operations'][0]['sql']);
        self::assertSame('foreign_key_rows', $plan['preflight'][0]['kind']);
        $actual['schema']['custom_owner']['columns']['id']['COLUMN_TYPE'] = 'int unsigned';
        $conflict = CoreSchemaPlan::build($reference, $actual);
        self::assertNotEmpty($conflict['blockers']);
        self::assertSame([], array_filter($conflict['operations'], static fn (array $op): bool => $op['kind'] === 'add_foreign_key'));
    }

    public function test_nullable_relaxation_uses_root_definition_without_rewriting_existing_values(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $expected = ['COLUMN_TYPE' => 'varchar(250)', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema']['fun_demo']['columns']['label'] = $expected;
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  `label` varchar(250) COLLATE utf8mb4_unicode_ci DEFAULT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $actual['schema']['custom_demo']['columns']['label'] = array_replace($expected, ['IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '']);
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('relax_nullable_column', $plan['operations'][0]['kind']);
        self::assertStringContainsString('DEFAULT NULL', $plan['operations'][0]['sql']);
        self::assertStringNotContainsString('UPDATE', $plan['operations'][0]['sql']);
        $actual['schema']['custom_demo']['columns']['label']['COLUMN_TYPE'] = 'varchar(500)';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], 'nullable 放宽不能夹带字段缩窄');
        $actual['schema']['custom_demo']['columns']['label'] = $expected;
        $reference['schema']['fun_demo']['columns']['label']['IS_NULLABLE'] = 'NO';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '禁止将可空字段直接收紧');
    }

    public function test_required_missing_column_is_allowed_only_for_observed_empty_table_with_recheck(): void
    {
        [$reference, $actual] = $this->reports();
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $reference['schema']['fun_demo']['columns']['required_name'] = ['COLUMN_TYPE' => 'varchar(64)', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  `required_name` varchar(64) NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '没有行数证据不可假定空表');
        $actual['table_rows']['demo'] = 0;
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('add_column', $plan['operations'][0]['kind']);
        self::assertSame('empty_table', $plan['preflight'][0]['kind']);
        $actual['table_rows']['demo'] = 1;
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '非空表不得隐式回填空字符串');
    }

    public function test_unsigned_reference_widening_preserves_default_and_never_accepts_sign_or_capacity_loss(): void
    {
        [$reference, $actual] = $this->reports();
        $expected = $reference['schema']['fun_demo']['columns']['id'];
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $actual['schema']['custom_demo']['columns']['id']['COLUMN_TYPE'] = 'int unsigned';
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('widen_column', $plan['operations'][0]['kind']);
        foreach (['int', 'bigint', 'decimal(20,0)', 'varchar(64)'] as $type) {
            $actual['schema']['custom_demo']['columns']['id']['COLUMN_TYPE'] = $type;
            self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
        }
        $actual['schema']['custom_demo']['columns']['id'] = $expected;
        $reference['schema']['fun_demo']['columns']['id']['COLUMN_TYPE'] = 'int unsigned';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_timestamp_conversion_requires_utc_evidence_and_preserves_other_metadata(): void
    {
        [$reference, $actual] = $this->reports();
        $column = ['COLUMN_TYPE' => 'datetime', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLLATION_NAME' => null];
        $reference['schema']['fun_demo']['columns']['created_at'] = $column;
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  `created_at` datetime DEFAULT NULL,\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $actual['schema']['custom_demo']['columns']['created_at']['COLUMN_TYPE'] = 'timestamp';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
        $actual['session_timezone'] = '+00:00';
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('timestamp_to_datetime_utc', $plan['operations'][0]['kind']);
        self::assertSame('utc_session', $plan['preflight'][0]['kind']);
        $actual['session_timezone'] = '+08:00';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
        $actual['session_timezone'] = '+00:00';
        $actual['schema']['custom_demo']['columns']['created_at']['EXTRA'] = 'on update CURRENT_TIMESTAMP';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_reviewed_pivot_surrogate_is_retained_only_with_exact_business_unique_key(): void
    {
        [$reference, $actual] = $this->reports();
        $id = $reference['schema']['fun_demo']['columns']['id'];
        $business = [['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'admin_id', 'SUB_PART' => null], ['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 2, 'COLUMN_NAME' => 'dept_id', 'SUB_PART' => null]];
        $table = ['columns' => ['admin_id' => $id, 'dept_id' => $id], 'indexes' => ['PRIMARY' => $business], 'constraints' => []];
        $reference['schema'] = ['fun_admin_department' => $table];
        $table['columns']['id'] = array_replace($id, ['EXTRA' => 'auto_increment']);
        $table['indexes'] = ['PRIMARY' => [['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'id', 'SUB_PART' => null]], 'admin_department_admin_id_dept_id_unique' => $business];
        $actual['schema'] = ['custom_admin_department' => $table];
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame([], $plan['operations'], '不能删除既有 surrogate ID 或重建主键');
        self::assertCount(3, $plan['reviewed_differences']);
        $actual['schema']['custom_admin_department']['indexes']['admin_department_admin_id_dept_id_unique'][0]['NON_UNIQUE'] = 1;
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
        $actual['schema']['custom_admin_department'] = $table;
        $actual['schema']['custom_admin_department']['columns']['id']['COLUMN_TYPE'] = 'varchar(64)';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_reviewed_wider_columns_and_additional_indexes_are_retained_but_unknown_objects_block(): void
    {
        [$reference, $actual] = $this->reports();
        $column = ['COLUMN_TYPE' => 'varchar(500)', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '', 'EXTRA' => '', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema'] = ['fun_admin_log' => ['columns' => ['url' => $column], 'indexes' => [], 'constraints' => []]];
        $actual['schema'] = ['custom_admin_log' => $reference['schema']['fun_admin_log']];
        $actual['schema']['custom_admin_log']['columns']['url']['COLUMN_TYPE'] = 'varchar(2048)';
        $actual['schema']['custom_admin_log']['indexes']['admin_log_status_index'] = [['NON_UNIQUE' => 1, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'status', 'SUB_PART' => null]];
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame([], $plan['operations']);
        self::assertCount(2, $plan['reviewed_differences']);
        $actual['schema']['custom_admin_log']['indexes']['admin_log_status_index'][0]['COLUMN_NAME'] = 'secret';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '索引同名不同定义不可忽略');
        $actual['schema']['custom_admin_log']['indexes'] = [];
        $actual['schema']['custom_admin_log']['columns']['url']['COLLATION_NAME'] = 'ascii_bin';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
        $reference['schema']['fun_unknown'] = $reference['schema']['fun_admin_log'];
        $actual['schema']['custom_unknown'] = $actual['schema']['custom_admin_log'];
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_same_type_default_alignment_and_nullable_relaxation_do_not_rewrite_rows(): void
    {
        [$reference, $actual] = $this->reports();
        $expected = ['COLUMN_TYPE' => 'varchar(255)', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => '', 'EXTRA' => '', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema']['fun_demo']['columns']['avatar'] = $expected;
        $reference['schema']['fun_demo']['create_sql'] = "CREATE TABLE `fun_demo` (\n  `id` bigint unsigned NOT NULL,\n  `avatar` varchar(255) DEFAULT '',\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB";
        $actual['schema']['custom_demo'] = $reference['schema']['fun_demo'];
        $actual['schema']['custom_demo']['columns']['avatar']['IS_NULLABLE'] = 'NO';
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('relax_nullable_column', $plan['operations'][0]['kind']);
        $actual['schema']['custom_demo']['columns']['avatar'] = $expected;
        $actual['schema']['custom_demo']['columns']['avatar']['COLUMN_DEFAULT'] = null;
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('align_column_default', $plan['operations'][0]['kind']);
        $actual['schema']['custom_demo']['columns']['avatar']['EXTRA'] = 'DEFAULT_GENERATED';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_empty_runtime_semantic_conversion_is_explicit_and_requires_empty_recheck(): void
    {
        [$reference, $actual] = $this->reports();
        $column = ['COLUMN_TYPE' => 'tinyint(1)', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '1', 'EXTRA' => '', 'COLLATION_NAME' => null];
        $reference['schema'] = ['fun_plugin_operation' => ['columns' => ['status' => $column], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `fun_plugin_operation` (\n  `status` tinyint(1) NOT NULL DEFAULT '1'\n) ENGINE=InnoDB"]];
        $actual['schema'] = ['custom_plugin_operation' => $reference['schema']['fun_plugin_operation']];
        $actual['schema']['custom_plugin_operation']['columns']['status'] = array_replace($column, ['COLUMN_TYPE' => 'varchar(40)', 'COLUMN_DEFAULT' => 'running', 'COLLATION_NAME' => 'utf8mb4_unicode_ci']);
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
        $actual['table_rows']['plugin_operation'] = 0;
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('empty_runtime_column_alignment', $plan['operations'][0]['kind']);
        self::assertSame('empty_table', $plan['preflight'][0]['kind']);
        $actual['table_rows']['plugin_operation'] = 1;
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '已有历史状态记录必须显式转换，不能直接强转');
        $actual['table_rows']['plugin_operation'] = 0;
        $actual['schema']['custom_plugin_operation']['columns']['status']['COLUMN_TYPE'] = 'text';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], '未审计来源类型仍阻断');
    }

    public function test_ascii_alignment_requires_value_preflight_without_capacity_or_type_changes(): void
    {
        [$reference, $actual] = $this->reports();
        $column = ['COLUMN_TYPE' => 'varchar(45)', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLLATION_NAME' => 'ascii_bin'];
        $reference['schema'] = ['fun_admin' => ['columns' => ['ip' => $column], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `fun_admin` (\n  `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL\n) ENGINE=InnoDB"]];
        $actual['schema'] = ['custom_admin' => $reference['schema']['fun_admin']];
        $actual['schema']['custom_admin']['columns']['ip']['COLLATION_NAME'] = 'utf8mb4_unicode_ci';
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('ascii_column_alignment', $plan['operations'][0]['kind']);
        self::assertSame('ascii_values', $plan['preflight'][0]['kind']);
        $actual['schema']['custom_admin']['columns']['ip']['COLUMN_TYPE'] = 'varchar(255)';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], 'ASCII 对齐不能夹带容量缩窄');
        $actual['schema']['custom_admin']['columns']['ip']['COLUMN_TYPE'] = 'char(45)';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_enum_alignment_has_explicit_value_guard_and_never_accepts_unknown_type(): void
    {
        [$reference, $actual] = $this->reports();
        $column = ['COLUMN_TYPE' => "enum('group','route','capability')", 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => 'route', 'EXTRA' => '', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema'] = ['fun_permission' => ['columns' => ['resource_type' => $column], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `fun_permission` (\n  `resource_type` enum('group','route','capability') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'route'\n) ENGINE=InnoDB"]];
        $actual['schema'] = ['custom_permission' => $reference['schema']['fun_permission']];
        $actual['schema']['custom_permission']['columns']['resource_type']['COLUMN_TYPE'] = 'varchar(20)';
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('enum_column_alignment', $plan['operations'][0]['kind']);
        self::assertSame(['group', 'route', 'capability'], $plan['preflight'][0]['allowed']);
        $actual['schema']['custom_permission']['columns']['resource_type']['COLUMN_TYPE'] = 'text';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_numeric_range_narrowing_remains_blocked_even_when_table_is_empty(): void
    {
        [$reference, $actual] = $this->reports();
        $column = ['COLUMN_TYPE' => 'int', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '999', 'EXTRA' => '', 'COLLATION_NAME' => null];
        $reference['schema'] = ['fun_admin_menu' => ['columns' => ['sort_order' => $column], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `fun_admin_menu` (\n  `sort_order` int NOT NULL DEFAULT '999'\n) ENGINE=InnoDB"]];
        $actual['schema'] = ['custom_admin_menu' => $reference['schema']['fun_admin_menu']];
        foreach (['int unsigned', 'bigint unsigned', 'varchar(20)'] as $type) {
            $actual['schema']['custom_admin_menu']['columns']['sort_order']['COLUMN_TYPE'] = $type;
            $actual['table_rows']['admin_menu'] = 0;
            $plan = CoreSchemaPlan::build($reference, $actual);
            self::assertNotEmpty($plan['blockers'], '空表不能成为自动缩窄数值范围的理由');
            self::assertSame([], $plan['operations']);
        }
    }

    public function test_numeric_domain_expansion_is_lossless_for_signed_and_unsigned_int(): void
    {
        [$reference, $actual] = $this->reports();
        $column = ['COLUMN_TYPE' => 'bigint', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => '0', 'EXTRA' => '', 'COLLATION_NAME' => null];
        $reference['schema'] = ['fun_member' => ['columns' => ['login_num' => $column], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `fun_member` (\n  `login_num` bigint DEFAULT '0'\n) ENGINE=InnoDB"]];
        $actual['schema'] = ['custom_member' => $reference['schema']['fun_member']];
        foreach (['int', 'int unsigned'] as $type) {
            $actual['schema']['custom_member']['columns']['login_num']['COLUMN_TYPE'] = $type;
            $actual['schema']['custom_member']['columns']['login_num']['IS_NULLABLE'] = 'NO';
            $plan = CoreSchemaPlan::build($reference, $actual);
            self::assertSame([], $plan['blockers']);
            self::assertSame('widen_numeric_domain', $plan['operations'][0]['kind']);
            self::assertStringContainsString('bigint', $plan['operations'][0]['sql']);
        }
        $actual['schema']['custom_member']['columns']['login_num']['COLUMN_TYPE'] = 'bigint unsigned';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers'], 'bigint unsigned 不能改有符号 bigint');
    }

    public function test_exact_runtime_extensions_require_full_metadata_match_not_just_a_known_column(): void
    {
        [$reference, $actual] = $this->reports();
        $column = ['COLUMN_TYPE' => 'int', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '0', 'EXTRA' => '', 'COLLATION_NAME' => null];
        $reference['schema'] = ['fun_attach' => ['columns' => ['admin_id' => $column], 'indexes' => [], 'constraints' => []]];
        $actual['schema'] = ['custom_attach' => $reference['schema']['fun_attach']];
        $actual['schema']['custom_attach']['columns']['admin_id']['COLUMN_TYPE'] = 'bigint unsigned';
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('PRESERVED_WEBMAN_COLUMN', $plan['reviewed_differences'][0]['code']);
        $actual['schema']['custom_attach']['columns']['admin_id']['COLUMN_DEFAULT'] = '-1';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
        $actual['schema']['custom_attach']['columns']['admin_id']['COLUMN_DEFAULT'] = '0';
        $actual['schema']['custom_attach']['columns']['admin_id']['COLUMN_TYPE'] = 'varchar(64)';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_varchar_to_text_expansion_preserves_collation_and_only_relaxes_nullable(): void
    {
        [$reference, $actual] = $this->reports();
        $expected = ['COLUMN_TYPE' => 'mediumtext', 'IS_NULLABLE' => 'YES', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLLATION_NAME' => 'utf8mb4_unicode_ci'];
        $reference['schema'] = ['fun_admin_log' => ['columns' => ['agent' => $expected], 'indexes' => [], 'constraints' => [], 'create_sql' => "CREATE TABLE `fun_admin_log` (\n  `agent` mediumtext COLLATE utf8mb4_unicode_ci\n) ENGINE=InnoDB"]];
        $actual['schema'] = ['custom_admin_log' => $reference['schema']['fun_admin_log']];
        $actual['schema']['custom_admin_log']['columns']['agent'] = array_replace($expected, ['COLUMN_TYPE' => 'varchar(2048)', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => '']);
        $plan = CoreSchemaPlan::build($reference, $actual);
        self::assertSame([], $plan['blockers']);
        self::assertSame('widen_text_domain', $plan['operations'][0]['kind']);
        $actual['schema']['custom_admin_log']['columns']['agent']['COLUMN_TYPE'] = 'longtext';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
        $actual['schema']['custom_admin_log']['columns']['agent']['COLUMN_TYPE'] = 'varchar(2048)';
        $actual['schema']['custom_admin_log']['columns']['agent']['COLLATION_NAME'] = 'ascii_bin';
        self::assertNotEmpty(CoreSchemaPlan::build($reference, $actual)['blockers']);
    }

    public function test_wrong_source_or_unsafe_schema_declaration_is_rejected(): void
    {
        self::assertTrue(class_exists(CoreSchemaPlan::class), '缺少存量结构接管计划器');
        [$reference, $actual] = $this->reports();
        $actual['source_checksums']['001_baseline.sql'] = str_repeat('b', 64);
        $this->expectException(\RuntimeException::class);
        CoreSchemaPlan::build($reference, $actual);
    }
}
