<?php

declare(strict_types=1);

namespace Tests;

use app\admin\development\service\BusinessDevelopmentService;
use app\admin\development\service\BusinessTargetService;
use app\admin\form\model\Form;
use app\admin\form\service\FormDesignerService;
use app\common\service\database\MigrationService;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BusinessCreationIntegrationTest extends TestCase
{
    public function test_target_candidates_read_persisted_plugin_lifecycle_and_operation_lock(): void
    {
        (new MigrationService())->run();
        Db::table('plugin')->insert([
            'code' => 'createfixture', 'name' => '创建测试插件', 'version' => '1.0.0',
            'lifecycle_state' => 'enabled', 'status' => 1, 'operation_token' => 'in-progress',
        ]);
        $service = new BusinessTargetService(base_path(), 'mysql',
            authorized: static fn (): bool => true,
            options: static fn (): array => [[
                'code' => 'createfixture', 'name' => '创建测试插件',
                'scopes' => ['admin'], 'businessWritable' => true,
            ]]
        );
        $candidates = $service->candidates();
        self::assertTrue($candidates['list'][0]['available']);
        self::assertSame('createfixture', $candidates['list'][1]['pluginCode']);
        self::assertFalse($candidates['list'][1]['available']);
        self::assertSame('BUSINESS_TARGET_OPERATION_LOCKED', $candidates['list'][1]['reason']['code']);
        Db::table('plugin')->where('code', 'createfixture')->update(['operation_token' => null]);
        self::assertTrue($service->candidates()['list'][1]['available']);
    }

    public function test_visual_creation_persists_form_and_schema_before_linking_business_module(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->create('form', static function (Blueprint $table): void {
            $table->id(); $table->string('form_key')->unique(); $table->string('name');
            $table->string('table_name'); $table->string('connection'); $table->string('source_type');
            $table->integer('status')->default(1); $table->integer('schema_version')->default(2);
            foreach (['list_config', 'form_config', 'schema_document', 'publish_config'] as $field) $table->json($field)->nullable();
            foreach (['schema_hash', 'schema_origin', 'publish_status', 'remark'] as $field) $table->string($field)->nullable();
            $table->integer('sort_order')->default(0); $table->unsignedBigInteger('business_module_id')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('form_field', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->string('field_name'); $table->string('label');
            foreach (['type', 'column_type', 'index_type', 'relation_type', 'relation_table', 'relation_label_field', 'relation_value_field', 'relation_foreign_key', 'relation_on_delete', 'default_value', 'placeholder', 'help_text', 'remote_api', 'comment', 'list_filter', 'list_formatter', 'form_group'] as $field) $table->string($field)->nullable();
            foreach (['nullable', 'list_show', 'list_sort', 'list_search', 'form_show', 'form_required', 'form_readonly', 'form_disabled', 'sort_order', 'unsigned', 'relation_multiple', 'list_width', 'form_span'] as $field) $table->integer($field)->default(0);
            foreach (['options_source', 'control_props', 'validate_rules', 'link_rules'] as $field) $table->json($field)->nullable();
            $table->timestamps();
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

        $service = BusinessDevelopmentService::production(base_path(), ['sqlite']);
        $detail = $service->createVisual([
            'code' => 'creation_fixture', 'name' => '客户订单', 'table' => 'creation_fixture',
            'connection' => 'sqlite', 'target' => ['type' => 'core'], 'remark' => '草稿说明',
        ], 'test:7');

        $module = $detail['module'];
        $form = Form::findOrFail($module['form_id']);
        self::assertSame('creation_fixture', $form->form_key);
        self::assertSame('客户订单', $form->name);
        self::assertSame('sqlite', $form->connection);
        self::assertSame('created', $form->source_type);
        self::assertSame('草稿说明', $form->remark);
        self::assertSame('客户订单', $form->schema_document['title']);
        self::assertSame((int) $module['id'], (int) $form->business_module_id);
        self::assertSame(1, Db::table('form_schema_version')->where('form_id', $form->id)->count());
        self::assertFalse(Db::schema()->hasTable('creation_fixture'), '创建草稿不提前创建物理业务表');

        (new FormDesignerService(base_path()))->save([
            'id' => $form->id, 'form_key' => $form->form_key, 'name' => '客户订单编辑',
            'table_name' => $form->table_name, 'connection' => $form->connection,
            'source_type' => $form->source_type, 'expected_schema_hash' => $form->schema_hash,
            'fields' => [['field_name' => 'title', 'label' => '订单标题', 'type' => 'input', 'column_type' => 'varchar(255)']],
        ]);
        self::assertSame('客户订单编辑', $form->fresh()->name);
        self::assertSame('title', Db::table('form_field')->where('form_id', $form->id)->value('field_name'));
        self::assertSame('订单标题', Db::table('form_field')->where('form_id', $form->id)->value('label'));
        self::assertSame(2, Db::table('form_schema_version')->where('form_id', $form->id)->count());
    }
}
