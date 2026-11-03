<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\CoreDatabaseAudit;
use app\common\service\database\MigrationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CoreDatabaseAuditTest extends TestCase
{
    public function test_comparison_uses_replayed_schema_including_defaults_indexes_and_foreign_keys(): void
    {
        self::assertTrue(method_exists(CoreDatabaseAudit::class, 'compare'), '缺少真实规范库结构比较');
        $column = ['COLUMN_TYPE' => 'bigint unsigned', 'IS_NULLABLE' => 'NO', 'COLUMN_DEFAULT' => null, 'EXTRA' => '', 'COLLATION_NAME' => null];
        $table = [
            'columns' => ['id' => $column, 'owner_id' => $column],
            'indexes' => ['PRIMARY' => [['NON_UNIQUE' => 0, 'SEQ_IN_INDEX' => 1, 'COLUMN_NAME' => 'id', 'SUB_PART' => null]]],
            'constraints' => ['fk_owner' => [['COLUMN_NAME' => 'owner_id', 'REFERENCED_TABLE_NAME' => 'fun_admin', 'REFERENCED_COLUMN_NAME' => 'id', 'ORDINAL_POSITION' => 1, 'DELETE_RULE' => 'RESTRICT', 'UPDATE_RULE' => 'CASCADE']]],
        ];
        $reference = ['prefix' => 'fun_', 'schema' => ['fun_item' => $table, 'fun_missing' => $table]];
        $actual = ['prefix' => 'custom_', 'schema' => ['custom_item' => $table]];
        $actual['schema']['custom_item']['constraints']['fk_owner'][0]['REFERENCED_TABLE_NAME'] = 'custom_admin';
        $actual['schema']['custom_item']['columns']['owner_id']['COLUMN_DEFAULT'] = '0';
        $actual['schema']['custom_item']['indexes']['PRIMARY'][0]['COLUMN_NAME'] = 'owner_id';
        $actual['schema']['custom_item']['constraints']['fk_owner'][0]['DELETE_RULE'] = 'CASCADE';
        $difference = CoreDatabaseAudit::compare($reference, $actual);
        self::assertSame(['missing'], $difference['missing_tables']);
        self::assertArrayHasKey('owner_id', $difference['columns']['item']);
        self::assertArrayHasKey('PRIMARY', $difference['indexes']['item']);
        self::assertArrayHasKey('fk_owner', $difference['constraints']['item']);
        self::assertFalse($difference['aligned']);
        self::assertTrue(CoreDatabaseAudit::compare($reference, $reference)['aligned']);
        $otherPrefix = ['prefix' => 'custom_', 'schema' => ['custom_item' => $table, 'custom_missing' => $table]];
        foreach ($otherPrefix['schema'] as &$item) $item['constraints']['fk_owner'][0]['REFERENCED_TABLE_NAME'] = 'custom_admin';
        unset($item);
        self::assertTrue(CoreDatabaseAudit::compare($reference, $otherPrefix)['aligned'], '外键目标必须按连接前缀归一');
    }

    public function test_generated_expression_and_check_constraint_drift_are_not_reported_as_aligned(): void
    {
        $table = [
            'columns' => ['ns' => ['COLUMN_TYPE' => 'varchar(190)', 'GENERATION_EXPRESSION' => "substring_index(`key`,'.',1)"]],
            'indexes' => [], 'constraints' => [],
            'checks' => ['ck_positive' => ['CHECK_CLAUSE' => '(`id` > 0)', 'ENFORCED' => 'YES']],
        ];
        $reference = ['prefix' => 'fun_', 'schema' => ['fun_demo' => $table]];
        $actual = $reference;
        $actual['schema']['fun_demo']['columns']['ns']['GENERATION_EXPRESSION'] = "substring_index(`key`,'.',2)";
        self::assertFalse(CoreDatabaseAudit::compare($reference, $actual)['aligned']);
        $actual = $reference;
        $actual['schema']['fun_demo']['checks']['ck_positive']['ENFORCED'] = 'NO';
        $difference = CoreDatabaseAudit::compare($reference, $actual);
        self::assertFalse($difference['aligned'], 'CHECK 约束被禁用必须阻断对齐结论');
        self::assertArrayHasKey('ck_positive', $difference['checks']['demo']);
        $actual['schema']['fun_demo']['checks'] = [];
        self::assertFalse(CoreDatabaseAudit::compare($reference, $actual)['aligned'], '缺失 CHECK 约束必须被发现');
    }

    public function test_manifest_reads_only_root_baseline_declarations_without_executing_seeds(): void
    {
        self::assertTrue(class_exists(CoreDatabaseAudit::class), '缺少只读核心差异审计');
        $directory = MigrationService::coreDirectory();
        $audit = new CoreDatabaseAudit($directory);
        $manifest = $audit->manifest();
        self::assertArrayHasKey('admin', $manifest['tables']);
        self::assertArrayHasKey('ai_task', $manifest['tables']);
        self::assertContains('password', array_keys($manifest['tables']['admin']['columns']));
        self::assertArrayHasKey('PRIMARY', $manifest['tables']['admin']['indexes']);
        self::assertCount(count(glob($directory . '/*.sql')), $manifest['checksums']);
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        self::assertFalse(Db::schema()->hasTable('admin'));
    }
}
