<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if (!$schema->hasColumn('permission', 'sort_order')) {
        $schema->table('permission', static fn (Blueprint $table) => $table->unsignedInteger('sort_order')->default(999));
    }
    if (!$schema->hasTable('department')) {
        $schema->create('department', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pid')->default(0);
            $table->string('name', 100);
            $table->string('leader', 100)->default('');
            $table->string('phone', 30)->default('');
            $table->string('email', 100)->default('');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }
    foreach (['auth_group_inherit' => 'parent_role_id', 'auth_group_department' => 'dept_id'] as $name => $target) {
        if (!$schema->hasTable($name)) {
            $schema->create($name, static function (Blueprint $table) use ($target): void {
                $table->id();
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger($target);
                $table->timestamps();
                $table->unique(['role_id', $target]);
            });
        }
    }
    if (!$schema->hasTable('permission_field')) {
        $schema->create('permission_field', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('permission_id')->index();
            $table->string('resource', 190);
            $table->string('field', 100);
            $table->string('name', 100);
            $table->boolean('status')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['permission_id', 'field']);
        });
    }
    if (!$schema->hasTable('auth_group_field_permission')) {
        $schema->create('auth_group_field_permission', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('field_id');
            $table->boolean('can_view')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->timestamps();
            $table->unique(['role_id', 'field_id']);
        });
    }
};
