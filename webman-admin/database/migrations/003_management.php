<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    $schema = Db::schema();
    if ($schema->hasTable('admin')) {
        return;
    }
    foreach (['nickname', 'email', 'mobile'] as $column) {
        if (!$schema->hasColumn('admins', $column)) {
            $schema->table('admins', static fn (Blueprint $table) => $table->string($column)->default(''));
        }
    }
    if (!$schema->hasColumn('admins', 'is_super_admin')) {
        $schema->table('admins', static fn (Blueprint $table) => $table->boolean('is_super_admin')->default(false));
    }
    foreach (['roles', 'permissions'] as $name) {
        if (!$schema->hasTable($name)) {
            $schema->create($name, static function (Blueprint $table): void {
                $table->id();
                $table->string('name', 100);
                $table->string('code', 100)->unique();
                $table->boolean('status')->default(true);
                $table->timestamps();
            });
        }
    }
    foreach (['admin_roles' => ['admins', 'roles'], 'role_permissions' => ['roles', 'permissions']] as $name => [$left, $right]) {
        if (!$schema->hasTable($name)) {
            $schema->create($name, static function (Blueprint $table) use ($left, $right): void {
                $first = rtrim($left, 's') . '_id';
                $second = rtrim($right, 's') . '_id';
                $table->foreignId($first)->constrained($left)->cascadeOnDelete();
                $table->foreignId($second)->constrained($right)->cascadeOnDelete();
                $table->primary([$first, $second]);
            });
        }
    }
    if (!$schema->hasTable('menus')) {
        $schema->create('menus', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('path', 200)->unique();
            $table->string('route_name', 100)->unique();
            $table->string('permission', 100);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }
    $now = date('Y-m-d H:i:s');
    foreach (['user', 'role', 'permission', 'menu'] as $resource) {
        foreach (['list', 'add', 'edit', 'delete'] as $action) {
            $code = "system:$resource:$action";
            Db::table('permissions')->insertOrIgnore(['name' => $code, 'code' => $code, 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
    Db::table('permissions')->insertOrIgnore(['name' => 'system:management:view', 'code' => 'system:management:view', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
    Db::table('menus')->insertOrIgnore([
        'name' => '后台管理', 'path' => '/management', 'route_name' => 'WebmanManagement',
        'permission' => 'system:management:view', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);
};
