<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasTable('auth_group')) {
        $schema->create('auth_group', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->unsignedInteger('level')->default(100);
            $table->string('data_scope', 30)->default('self');
            $table->string('remark', 255)->default('');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    if (!$schema->hasTable('permission')) {
        $schema->create('permission', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->string('app_name', 50)->default('admin');
            $table->string('code', 255)->nullable()->unique();
            $table->string('obj', 190)->default('');
            $table->string('act', 100)->default('');
            $table->string('name', 100)->default('');
            $table->string('resource_type', 20)->default('route');
            $table->boolean('status')->default(true);
            $table->boolean('is_public')->default(false);
            $table->string('source_type', 20)->default('system');
            $table->string('source_name', 100)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
    }
    if (!$schema->hasTable('admin_menu')) {
        $schema->create('admin_menu', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->unsignedBigInteger('permission_id')->default(0);
            $table->string('app_name', 50)->default('admin');
            $table->string('name', 100)->default('');
            $table->string('href', 255)->default('');
            $table->string('query', 250)->default('');
            $table->string('target', 20)->default('_self');
            $table->string('icon', 100)->default('');
            $table->boolean('status')->default(true);
            $table->string('source_type', 20)->default('admin_web');
            $table->string('source_name', 100)->default('');
            $table->unsignedInteger('sort_order')->default(999);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    if (!$schema->hasTable('casbin_rule')) {
        $schema->create('casbin_rule', static function (Blueprint $table): void {
            $table->id();
            $table->string('ptype', 10);
            foreach (range(0, 5) as $index) $table->string('v' . $index, 190)->default('');
            $table->string('rule_hash', 64)->unique();
        });
    }
    if (!$schema->hasTable('admin_department')) {
        $schema->create('admin_department', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->unsignedBigInteger('dept_id');
            $table->timestamps();
            $table->unique(['admin_id', 'dept_id']);
        });
    }
};
