<?php

declare(strict_types=1);

namespace Tests;

use app\common\crud\ProductionTemplateContext;
use app\common\crud\CrudDefinition;
use app\common\service\database\SqlDialect;
use PHPUnit\Framework\TestCase;
use support\Db;
use Tests\Console\CrudDefinitionFixture;

final class PostgresqlDdlTest extends TestCase
{
    public function test_generator_creates_native_postgresql_table_with_identity_and_types(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        $data = CrudDefinitionFixture::make('pg-native', 'pgsql')->toArray();
        $data['table'] = 'pg_native_' . bin2hex(random_bytes(5));
        $data['fields'][] = ['name' => 'enabled', 'label' => '启用', 'dbType' => 'boolean', 'nullable' => false, 'default' => false];
        $data['fields'][] = ['name' => 'price', 'label' => '金额', 'dbType' => 'decimal(12,2)', 'nullable' => false, 'default' => '0.00'];
        $data['fields'][] = ['name' => 'external_id', 'label' => '外部标识', 'dbType' => 'uuid', 'nullable' => true];
        $context = ProductionTemplateContext::build(CrudDefinition::fromArray($data));
        $pdo = Db::connection()->getPdo();
        try {
            $pdo->exec($context['migrationContent']);
            $statement = $pdo->prepare('INSERT INTO "' . $data['table'] . '" (title, metadata) VALUES (?, ?::jsonb) RETURNING id, enabled');
            $statement->execute(['中文标题', '{"ok":true}']);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);
            self::assertSame(1, $row['id']);
            self::assertFalse($row['enabled']);
            self::assertStringNotContainsString('AUTO_INCREMENT', $context['migrationContent']);
            self::assertStringNotContainsString('ENGINE=', $context['migrationContent']);
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS "' . $data['table'] . '"');
        }
    }

    public function test_form_ddl_creates_and_adds_columns_without_mysql_syntax(): void
    {
        if (getenv('TEST_PGSQL') !== '1') $this->markTestSkipped('需要隔离 PostgreSQL');
        $service = new \app\admin\form\service\FormDesignerService(base_path() . '/');
        $table = 'pg_form_' . bin2hex(random_bytes(4));
        $payload = ['connection' => 'pgsql', 'form_key' => $table, 'name' => '中文表单', 'table_name' => $table, 'fields' => [
            ['field_name' => 'title', 'label' => '标题', 'type' => 'input', 'column_type' => 'varchar(80)', 'nullable' => 0, 'default_value' => '', 'index_type' => 'unique'],
        ]];
        $pdo = Db::connection()->getPdo();
        try {
            $initial = $service->previewMigration($payload);
            self::assertTrue($service->applyDynamicDdl($payload + ['dynamic_ddl_hash' => hash('sha256', $initial['sql'])])['applied']);
            $payload['fields'][] = ['field_name' => 'enabled', 'label' => '启用', 'type' => 'switch', 'column_type' => 'tinyint(1)', 'nullable' => 0, 'default_value' => '0', 'index_type' => 'index'];
            $alter = $service->previewMigration($payload);
            self::assertSame('additive', $alter['mode']);
            self::assertTrue($service->applyDynamicDdl($payload + ['dynamic_ddl_hash' => hash('sha256', $alter['sql'])])['applied']);
            $pdo->exec('INSERT INTO "' . $table . '" (title) VALUES (\'中文\')');
            self::assertFalse($pdo->query('SELECT enabled FROM "' . $table . '"')->fetchColumn());
            self::assertSame('', $service->previewMigration($payload)['sql']);
        } finally {
            $pdo->exec('DROP TABLE IF EXISTS "' . $table . '"');
        }
    }

    public function test_postgresql_types_reject_unsupported_types_and_bound_identifier_names(): void
    {
        $dialect = new SqlDialect('pgsql');
        self::assertSame('bigint', $dialect->type('int unsigned'));
        self::assertSame('numeric(20,0)', $dialect->type('bigint unsigned'));
        self::assertLessThanOrEqual(63, strlen($dialect->indexName(str_repeat('a', 90))));
        self::assertNotSame($dialect->indexName(str_repeat('a', 90)), $dialect->indexName(str_repeat('a', 89) . 'b'));
        $this->expectException(\InvalidArgumentException::class);
        $dialect->type('geometry');
    }
}
