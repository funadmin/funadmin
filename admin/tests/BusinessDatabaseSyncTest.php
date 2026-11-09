<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authorization\service\PermissionCode;
use app\admin\development\controller\Business;
use app\admin\development\service\BusinessDevelopmentService;
use app\admin\form\model\Form;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BusinessDatabaseSyncTest extends TestCase
{
    private const ORIGINAL = 'CREATE TABLE %s (id integer primary key autoincrement not null, title varchar(100) not null, remark varchar(255), created_at datetime, updated_at datetime)';
    private const ALTERED = 'CREATE TABLE %s (id integer primary key autoincrement not null, title varchar(200) not null, stock int, created_at datetime, updated_at datetime)';

    public function test_permission_code_is_derived_from_action(): void
    {
        self::assertSame('development:business:database-sync', PermissionCode::derive(Business::class, 'databaseSync'));
    }

    public function test_sync_reports_added_removed_and_changed_columns_without_writing(): void
    {
        $this->createMetadataTables();
        $service = BusinessDevelopmentService::production(base_path(), ['sqlite']);
        Db::statement(sprintf(self::ORIGINAL, 'sync_fixture'));
        $module = $this->adopt($service, 'sync_fixture');
        $formId = (int) $module['module']['form_id'];
        $versions = Db::table('form_schema_version')->where('form_id', $formId)->count();
        $schemaHash = (string) Form::findOrFail($formId)->schema_hash;

        Db::statement('DROP TABLE sync_fixture');
        Db::statement(sprintf(self::ALTERED, 'sync_fixture'));
        $diff = $service->databaseSync((int) $module['module']['id']);

        self::assertSame($service->inspectDatabase('sqlite', 'sync_fixture')['snapshotHash'], $diff['inspectionHash']);
        self::assertSame(['remark'], $diff['removed']);
        self::assertCount(1, $diff['added']);
        self::assertSame('stock', $diff['added'][0]['field']);
        self::assertSame([
            ['field' => 'title', 'from' => ['columnType' => 'varchar(100)'], 'to' => ['columnType' => 'varchar(200)']],
        ], $diff['changed']);

        // 新列节点必须与「从数据库创建」对同一表生成的节点完全一致
        Db::statement(sprintf(self::ALTERED, 'sync_expected'));
        $expected = $this->adopt($service, 'sync_expected');
        $document = Form::findOrFail((int) $expected['module']['form_id'])->schema_document;
        $stock = array_values(array_filter($document['nodes'], static fn (array $node): bool => ($node['field'] ?? null) === 'stock'));
        self::assertSame($stock[0], $diff['added'][0]);

        self::assertSame($versions, Db::table('form_schema_version')->where('form_id', $formId)->count());
        self::assertSame($schemaHash, (string) Form::findOrFail($formId)->schema_hash);
    }

    public function test_sync_detects_nullable_change_and_ignores_layout_and_containers(): void
    {
        $this->createMetadataTables();
        $service = BusinessDevelopmentService::production(base_path(), ['sqlite']);
        Db::statement(sprintf(self::ORIGINAL, 'sync_nested'));
        $module = $this->adopt($service, 'sync_nested');
        $formId = (int) $module['module']['form_id'];
        $form = Form::findOrFail($formId);
        $document = $form->schema_document;
        $title = array_values(array_filter($document['nodes'], static fn (array $node): bool => $node['field'] === 'title'))[0];
        $others = array_values(array_filter($document['nodes'], static fn (array $node): bool => $node['field'] !== 'title'));
        $group = ['id' => 'node_group', 'kind' => 'layout', 'type' => 'group', 'field' => null, 'title' => '分组', 'children' => [$title]];
        $child = ['id' => 'node_sku', 'kind' => 'field', 'type' => 'input', 'field' => 'sku', 'title' => '编码', 'database' => ['columnType' => 'varchar(50)']];
        $container = [
            'id' => 'node_items', 'kind' => 'field', 'type' => 'subform', 'field' => 'items', 'title' => '明细', 'children' => [$child],
            'database' => ['relation' => ['type' => 'has_many', 'table' => 'sync_nested_item', 'valueField' => 'nested_id']],
        ];
        $document['nodes'] = array_merge([$group], $others, [$container]);
        $saved = $service->saveSchema((int) $module['module']['id'], $document, (string) $form->schema_hash, 'test:1', '嵌套');
        self::assertNotEmpty($saved['schemaHash']);

        Db::statement('DROP TABLE sync_nested');
        Db::statement('CREATE TABLE sync_nested (id integer primary key autoincrement not null, title varchar(100), remark varchar(255), created_at datetime, updated_at datetime)');
        $diff = $service->databaseSync((int) $module['module']['id']);

        self::assertSame([], $diff['added']);
        self::assertSame([], $diff['removed']);
        self::assertSame([['field' => 'title', 'from' => ['nullable' => false], 'to' => ['nullable' => true]]], $diff['changed']);
    }

    public function test_visual_module_cannot_sync(): void
    {
        $this->createMetadataTables();
        $service = BusinessDevelopmentService::production(base_path(), ['sqlite']);
        $detail = $service->createVisual([
            'code' => 'sync_visual', 'name' => '可视化', 'table' => 'sync_visual',
            'connection' => 'sqlite', 'target' => ['type' => 'core'],
        ], 'test:1');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('仅数据库装载的模块可同步');
        $service->databaseSync((int) $detail['module']['id']);
    }

    private function adopt(BusinessDevelopmentService $service, string $table): array
    {
        $inspection = $service->inspectDatabase('sqlite', $table);
        return $service->createFromDatabase([
            'code' => $table, 'name' => '同步测试', 'table' => $table, 'connection' => 'sqlite',
            'target' => ['type' => 'core'], 'expectedInspectionHash' => $inspection['snapshotHash'],
        ], 'test:1');
    }

    private function createMetadataTables(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('form', static function (Blueprint $table): void {
            $table->id(); $table->string('form_key')->unique(); $table->string('name');
            $table->string('table_name'); $table->string('connection'); $table->string('source_type');
            $table->integer('status')->default(1); $table->integer('schema_version')->default(2);
            foreach (['schema_document', 'publish_config'] as $field) $table->json($field)->nullable();
            foreach (['schema_hash', 'schema_origin', 'publish_status', 'remark'] as $field) $table->string($field)->nullable();
            $table->integer('sort_order')->default(0); $table->unsignedBigInteger('business_module_id')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->integer('schema_version');
            $table->string('schema_hash'); $table->json('schema_document');
            $table->string('origin'); $table->unsignedBigInteger('parent_version_id')->nullable();
            $table->string('change_summary'); $table->string('created_by'); $table->timestamps();
        });
        Db::schema()->create('business_module', static function (Blueprint $table): void {
            $table->id(); $table->string('code')->unique(); $table->string('name'); $table->unsignedBigInteger('form_id');
            foreach (['origin', 'connection_name', 'table_name', 'runtime_route', 'module_route', 'lifecycle_status', 'generation_status'] as $field) $table->string($field);
            $table->json('metadata'); $table->timestamps(); $table->softDeletes();
        });
    }
}
