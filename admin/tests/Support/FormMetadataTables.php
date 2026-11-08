<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use support\Db;

/** 表单/业务模块元数据表的最小 sqlite 结构，供需要真实 Eloquent 落库的测试复用。 */
trait FormMetadataTables
{
    private function createMetadataTables(): void
    {
        Db::schema()->create('form', static function (Blueprint $table): void {
            $table->id(); $table->string('form_key')->unique(); $table->string('name');
            $table->string('table_name'); $table->string('connection'); $table->string('source_type');
            $table->integer('status')->default(1); $table->integer('schema_version')->default(2);
            foreach (['list_config', 'form_config', 'schema_document', 'publish_config'] as $field) $table->json($field)->nullable();
            foreach (['schema_hash', 'published_schema_hash', 'schema_origin', 'publish_status', 'remark', 'publish_mode', 'published_definition_hash'] as $field) $table->string($field)->nullable();
            $table->integer('sort_order')->default(0); $table->unsignedBigInteger('business_module_id')->nullable();
            $table->dateTime('published_at')->nullable(); $table->unsignedBigInteger('crud_generation_id')->nullable();
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
            $table->string('published_schema_hash')->nullable(); $table->integer('published_schema_version')->nullable();
            $table->unsignedBigInteger('current_generation_id')->nullable(); $table->unsignedBigInteger('last_success_generation_id')->nullable();
        });
    }
}
