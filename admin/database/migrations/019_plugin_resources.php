<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use support\Db;

return static function (): void {
    $now = date('Y-m-d H:i:s');
    Db::transaction(static function () use ($now): void {
        foreach (['installed', 'discovered', 'localdetail', 'enabledmodules'] as $action) {
            $resource = PermissionResource::fromParts('admin', 'SystemPlugin', $action);
            if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
            Db::table('permission')->insert(['app_name' => 'admin', 'name' => '插件中心 / ' . $action, 'code' => $resource['code'], 'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route', 'source_type' => 'system', 'source_name' => 'plugin_center', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        if (!Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/admin/system/plugin')->exists()) {
            $resource = PermissionResource::fromParts('admin', 'SystemPlugin', 'installed');
            Db::table('admin_menu')->insert(['app_name' => 'admin', 'name' => '插件中心', 'href' => '/admin/system/plugin', 'permission_id' => Db::table('permission')->where('code', $resource['code'])->value('id'), 'query' => http_build_query(['name' => 'SystemPlugin', 'type' => 'C', 'component' => 'system/plugin/index', 'permission' => $resource['code']]), 'source_type' => 'admin_web', 'source_name' => 'plugin_center', 'status' => 1, 'sort_order' => 13, 'icon' => 'i-ep-box', 'created_at' => $now, 'updated_at' => $now]);
        }
    });
};
