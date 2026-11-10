<?php

declare(strict_types=1);

namespace Tests\Console;

use Illuminate\Database\Schema\Blueprint;
use support\Db;

final class BusinessGenerationFixture
{
    public static function create(): void
    {
        Db::schema()->dropIfExists('form');
        Db::schema()->create('form', static function (Blueprint $table): void {
            $table->id(); $table->timestamps(); $table->softDeletes();
            foreach (['publish_mode', 'publish_status', 'published_definition_hash', 'published_schema_hash'] as $name) $table->string($name)->nullable();
            $table->unsignedBigInteger('crud_generation_id')->nullable(); $table->dateTime('published_at')->nullable();
        });
        Db::schema()->dropIfExists('business_module');
        Db::schema()->create('business_module', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id')->default(0); $table->string('code');
            $table->string('table_name')->default(''); $table->string('connection_name')->default('sqlite');
            $table->json('metadata')->nullable(); $table->string('lifecycle_status')->default('draft');
            $table->string('published_schema_hash')->nullable(); $table->integer('published_schema_version')->nullable();
            $table->string('module_route')->nullable(); $table->unsignedBigInteger('current_generation_id')->nullable();
            $table->unsignedBigInteger('last_success_generation_id')->nullable(); $table->string('generation_status')->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->dropIfExists('crud_generation');
        Db::schema()->create('crud_generation', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('business_module_id'); $table->unsignedBigInteger('form_id')->default(0);
            $table->string('operation_key')->unique(); $table->string('status'); $table->string('recovery_status')->default('none');
            $table->string('definition_hash', 64); $table->json('definition'); $table->json('manifest')->nullable();
            $table->json('result')->nullable(); $table->json('error')->nullable(); $table->string('actor')->nullable();
            $table->string('transaction_id', 32)->nullable(); $table->string('plan_digest', 64)->nullable();
            $table->string('failure_code')->nullable(); $table->unsignedBigInteger('superseded_by_id')->nullable();
            foreach (['started_at', 'failed_at', 'completed_at', 'recovered_at'] as $name) $table->dateTime($name)->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->dropIfExists('generated_file_baseline');
        Db::schema()->create('generated_file_baseline', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('business_module_id'); $table->unsignedBigInteger('generation_id');
            foreach (['relative_path', 'artifact_type', 'base_hash', 'base_storage_path', 'target_hash', 'template_version', 'definition_hash', 'content_kind', 'status'] as $name) $table->string($name)->nullable();
            $table->unique(['business_module_id', 'relative_path']); $table->timestamps(); $table->softDeletes();
        });
    }

    public static function seed(): array
    {
        $module = Db::table('business_module')->insertGetId(['code' => 'console-recovery', 'metadata' => '{}']);
        $generation = Db::table('crud_generation')->insertGetId([
            'business_module_id' => $module, 'operation_key' => 'managed:' . bin2hex(random_bytes(32)),
            'status' => 'planned', 'definition_hash' => str_repeat('a', 64),
            'definition' => json_encode(['routePath' => '/business/console-recovery', 'formSchemaHash' => str_repeat('b', 64)], JSON_THROW_ON_ERROR),
        ]);
        return [$module, $generation];
    }

    public static function drop(): void
    {
        foreach (['generated_file_baseline', 'crud_generation', 'business_module', 'form'] as $table) Db::schema()->dropIfExists($table);
    }
}
