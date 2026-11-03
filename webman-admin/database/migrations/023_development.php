<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (Db::connection()->getDriverName() !== 'pgsql') return;
    $schema = Db::schema();
    if (!$schema->hasTable('form')) $schema->create('form', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('business_module_id')->nullable(); $t->string('publish_mode', 20)->default('dynamic');
        $t->string('form_key', 64)->unique(); $t->string('name', 100)->default(''); $t->string('table_name', 100)->default('');
        $t->string('connection', 50)->default((string) config('database.default', 'mysql')); $t->string('source_type', 20)->default('created'); $t->boolean('status')->default(true);
        foreach (['list_config','form_config','schema_document','publish_config'] as $name) $t->json($name)->nullable();
        $t->integer('schema_version')->default(1); $t->char('schema_hash', 64)->nullable(); $t->string('schema_origin', 20)->default('designer'); $t->string('publish_status', 20)->default('draft');
        $t->dateTime('published_at')->nullable(); $t->bigInteger('crud_generation_id')->nullable(); $t->char('published_definition_hash', 64)->nullable(); $t->char('published_schema_hash', 64)->nullable();
        $t->string('remark')->default(''); $t->integer('sort_order')->default(0); $t->timestamps(); $t->softDeletes();
        $t->index(['publish_status','published_at']); $t->index('business_module_id'); $t->index('crud_generation_id');
    });
    if (!$schema->hasTable('form_field')) $schema->create('form_field', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('form_id'); $t->string('field_name',64); $t->string('label',100)->default(''); $t->string('type',30)->default('input'); $t->string('column_type',100)->default('');
        $t->boolean('nullable')->default(true); $t->string('default_value')->default(''); $t->string('comment')->default(''); $t->boolean('unsigned')->default(false); $t->string('index_type',10)->default('none'); $t->string('placeholder',100)->default('');
        foreach (['options_source','control_props','validate_rules','link_rules'] as $name) $t->json($name)->nullable();
        $t->string('relation_type',20)->default('none'); $t->string('relation_table',100)->default(''); $t->string('relation_label_field',64)->default(''); $t->string('relation_value_field',64)->default(''); $t->boolean('relation_multiple')->default(false); $t->string('relation_on_delete',20)->default('restrict');
        foreach (['list_show','form_show'] as $name) $t->boolean($name)->default(true);
        foreach (['list_sort','form_required','form_readonly'] as $name) $t->boolean($name)->default(false);
        $t->string('list_filter',20)->default(''); $t->string('list_formatter',30)->default(''); $t->integer('list_width')->default(0); $t->string('form_group',50)->default(''); $t->integer('form_span')->default(24); $t->integer('sort_order')->default(0); $t->timestamps();
        $t->unique(['form_id','field_name']); $t->index(['form_id','sort_order']);
    });
    if (!$schema->hasTable('form_schema_version')) $schema->create('form_schema_version', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('form_id'); $t->integer('version'); $t->integer('schema_version')->default(2); $t->char('schema_hash',64); $t->json('schema_document'); $t->string('origin',20)->default('designer'); $t->bigInteger('parent_version_id')->nullable(); $t->string('change_summary')->default(''); $t->string('created_by',100)->default(''); $t->dateTime('created_at'); $t->unique(['form_id','version']); $t->unique(['form_id','schema_hash']);
    });
    if (!$schema->hasTable('form_publish_attempt')) $schema->create('form_publish_attempt', static function (Blueprint $t): void {
        $t->id(); $t->string('operation_key',128)->unique(); $t->bigInteger('form_id')->nullable();
        foreach (['schema_hash','definition_hash','dependency_hash'] as $name) $t->char($name,64);
        $t->string('stage',32)->default('prepared'); $t->bigInteger('generation_id')->nullable(); $t->string('status',32)->default('running'); $t->char('lease_token',32)->nullable(); $t->dateTime('lease_expires_at')->nullable(); $t->json('result')->nullable(); $t->json('error')->nullable(); $t->timestamps(); $t->index(['form_id','schema_hash','definition_hash']);
    });
    if (!$schema->hasTable('form_list_action_execution')) $schema->create('form_list_action_execution', static function (Blueprint $t): void { $t->string('scope',64)->primary(); $t->string('digest',64); $t->string('state',16); $t->text('result')->nullable(); });
    if (!$schema->hasTable('business_module')) $schema->create('business_module', static function (Blueprint $t): void {
        $t->id(); $t->string('code',64)->unique(); $t->string('name',100)->default(''); $t->bigInteger('form_id')->nullable()->unique(); $t->enum('origin',['visual','database'])->default('visual'); $t->string('connection_name',64)->default((string) config('database.default','mysql')); $t->string('table_name',190)->default(''); $t->string('runtime_route')->default(''); $t->string('module_route')->default(''); $t->string('lifecycle_status',32)->default('draft'); $t->char('published_schema_hash',64)->nullable(); $t->integer('published_schema_version')->nullable();
        foreach (['current_generation_id','last_success_generation_id','created_by','updated_by'] as $name) $t->bigInteger($name)->nullable();
        $t->string('generation_status',32)->default('idle'); $t->json('metadata')->nullable(); $t->timestamps(); $t->softDeletes(); $t->index(['origin','lifecycle_status']); $t->index(['connection_name','table_name']);
    });
    if (!$schema->hasTable('crud_generation')) $schema->create('crud_generation', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('business_module_id')->nullable(); $t->bigInteger('form_id')->nullable(); $t->string('binding_status',20)->default('unbound'); $t->string('generation_mode',32)->default('full'); $t->string('transaction_id',64)->nullable(); $t->char('plan_digest',64)->nullable(); $t->string('recovery_status',32)->default('none'); $t->string('operation',20)->default('generate'); $t->string('operation_key',191)->nullable()->unique(); $t->bigInteger('superseded_by_id')->nullable(); $t->string('status',32); $t->string('connection_name',64)->default(''); $t->string('table_name',190)->default(''); $t->char('definition_hash',64);
        foreach (['definition','manifest','result','error'] as $name) $t->json($name)->nullable();
        $t->string('failure_code',64)->nullable(); $t->string('actor',100)->nullable(); foreach (['started_at','completed_at','failed_at','recovered_at'] as $name) $t->dateTime($name)->nullable(); $t->timestamps(); $t->softDeletes(); $t->index(['business_module_id','binding_status']); $t->index(['status','recovery_status']);
    });
    if (!$schema->hasTable('generated_file_baseline')) $schema->create('generated_file_baseline', static function (Blueprint $t): void {
        $t->id(); $t->bigInteger('business_module_id'); $t->string('relative_path',512); $t->string('artifact_type',64)->default(''); $t->char('base_hash',64); $t->string('base_storage_path',512); $t->char('target_hash',64)->nullable(); $t->string('template_version',64)->default(''); $t->char('definition_hash',64); $t->bigInteger('generation_id')->nullable(); $t->string('content_kind',32)->default('text'); $t->string('status',32)->default('active'); $t->timestamps(); $t->softDeletes(); $t->unique(['business_module_id','relative_path']);
    });
};
