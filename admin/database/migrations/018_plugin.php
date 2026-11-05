<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('plugin')) {
        $schema->create('plugin', static function (Blueprint $table): void {
            $table->id(); $table->string('code', 64)->unique(); $table->string('name', 120)->default(''); $table->string('version', 64)->default(''); $table->boolean('status')->default(false);
            $table->string('lifecycle_state', 40)->default('discovered'); $table->boolean('needs_reinstall')->default(false); $table->string('operation_token', 128)->nullable(); $table->text('manifest')->nullable(); $table->text('config')->nullable(); $table->string('source', 20)->default('local'); $table->string('code_version', 64)->default(''); $table->string('requires', 255)->default(''); $table->string('last_error', 500)->default(''); $table->string('error_stage', 80)->default(''); $table->string('recovery_path', 1024)->default(''); $table->dateTime('installed_at')->nullable(); $table->dateTime('state_changed_at')->nullable(); $table->timestamps(); $table->softDeletes();
            $table->index(['lifecycle_state', 'status']);
        });
    }
    if (!$schema->hasTable('plugin_operation')) {
        $schema->create('plugin_operation', static function (Blueprint $table): void {
            $table->id(); $table->string('plugin_code', 64); $table->string('operation', 40); $table->string('status', 40)->default('running'); $table->string('stage', 80)->default(''); $table->unsignedTinyInteger('progress')->default(0); $table->string('operation_token', 128)->unique(); $table->string('from_version', 64)->default(''); $table->string('to_version', 64)->default(''); $table->text('error_message')->nullable(); $table->json('metadata')->nullable(); $table->timestamps(); $table->index(['plugin_code', 'status']);
        });
    }
    if (!$schema->hasTable('plugin_resource')) {
        $schema->create('plugin_resource', static function (Blueprint $table): void {
            $table->id(); $table->string('plugin_code', 64); $table->string('version', 64)->default(''); $table->string('resource_type', 40); $table->string('publication_unit', 80)->nullable(); $table->string('operation_token', 128)->default(''); $table->string('source_path', 1024)->default(''); $table->string('target_path', 1024)->default(''); $table->string('sha256', 64)->default(''); $table->string('tree_hash', 64)->nullable(); $table->timestamps(); $table->index(['plugin_code', 'version']);
        });
    }
    if (!$schema->hasTable('plugin_version_history')) {
        $schema->create('plugin_version_history', static function (Blueprint $table): void {
            $table->id(); $table->string('plugin_code', 64); $table->string('version', 64); $table->string('package_path', 1024)->default(''); $table->string('package_hash', 64)->default(''); $table->unsignedBigInteger('package_size')->default(0); $table->text('manifest')->nullable(); $table->dateTime('created_at')->nullable(); $table->dateTime('updated_at')->nullable(); $table->index(['plugin_code', 'version']);
        });
    }
};
