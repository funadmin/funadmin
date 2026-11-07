<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (!Db::schema()->hasTable('blacklist')) {
        Db::schema()->create('blacklist', static function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 50)->index();
            $table->string('remark', 200)->default('');
            $table->boolean('status')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['ip', 'status', 'deleted_at']);
        });
    }
    $now = date('Y-m-d H:i:s');
    Db::transaction(static function () use ($now): void {
        foreach (['index', 'detail', 'create', 'update', 'status', 'delete', 'restore', 'destroy', 'import', 'export'] as $action) {
            $resource = PermissionResource::fromParts('admin', 'SystemBlacklist', $action);
            if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
            Db::table('permission')->insert([
                'app_name' => 'admin', 'name' => '黑名单 / ' . $action, 'code' => $resource['code'],
                'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route',
                'source_type' => 'system', 'source_name' => 'blacklist', 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (!Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/admin/system/blacklist')->exists()) {
            $resource = PermissionResource::fromParts('admin', 'SystemBlacklist', 'index');
            Db::table('admin_menu')->insert([
                'app_name' => 'admin', 'name' => '黑名单', 'href' => '/admin/system/blacklist',
                'permission_id' => Db::table('permission')->where('code', $resource['code'])->value('id'),
                'query' => http_build_query(['name' => 'SystemBlacklist', 'type' => 'C', 'component' => 'system/blacklist/index', 'permission' => $resource['code']]),
                'source_type' => 'admin_web', 'source_name' => 'blacklist', 'status' => 1, 'sort_order' => 55,
                'icon' => 'i-ep-circle-close', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    });
};
