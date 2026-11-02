<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

return static function (): void {
    if (!Db::schema()->hasTable('admin_log')) {
        Db::schema()->create('admin_log', static function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('admin_id')->default(0)->index();
            foreach (['name', 'username', 'url', 'app_name', 'source_type', 'source_name', 'controller', 'action', 'agent', 'ip', 'method', 'request_id', 'error_message'] as $column) {
                $table->string($column, $column === 'error_message' ? 500 : ($column === 'url' || $column === 'agent' ? 2048 : 255))->default('');
            }
            foreach (['get_data', 'post_data', 'header_data'] as $column) $table->longText($column)->nullable();
            $table->boolean('status')->default(false)->index();
            $table->unsignedSmallInteger('response_code')->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index('created_at');
        });
    }
    $now = date('Y-m-d H:i:s');
    Db::transaction(static function () use ($now): void {
        foreach (['index', 'detail', 'delete', 'deleteById'] as $action) {
            $resource = PermissionResource::fromParts('admin', 'SystemOperationLog', $action);
            if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
            Db::table('permission')->insert([
                'app_name' => 'admin', 'name' => '操作日志 / ' . $action, 'code' => $resource['code'],
                'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route',
                'source_type' => 'system', 'source_name' => 'operation_log', 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        if (!Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/admin/system/log/operation')->exists()) {
            Db::table('admin_menu')->insert([
                'app_name' => 'admin', 'name' => '操作日志', 'href' => '/admin/system/log/operation',
                'permission_id' => Db::table('permission')->where('code', 'systemoperationlog:index')->value('id'),
                'query' => http_build_query(['name' => 'SystemLogOperation', 'type' => 'C', 'component' => 'system/log/operation', 'permission' => 'systemoperationlog:index']),
                'source_type' => 'admin_web', 'source_name' => 'operation_log', 'status' => 1, 'sort_order' => 40,
                'icon' => 'i-ep-document', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    });
};
