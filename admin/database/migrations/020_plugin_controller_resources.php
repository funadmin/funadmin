<?php

declare(strict_types=1);

use app\admin\authorization\service\PermissionResource;
use support\Db;

return static function (): void {
    $now = date('Y-m-d H:i:s');
    Db::transaction(static function () use ($now): void {
        foreach (['installed', 'discovered', 'localdetail', 'enabledmodules'] as $action) {
            $resource = PermissionResource::fromParts('admin', 'plugin.SystemPlugin', $action);
            if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
            Db::table('permission')->insert(['app_name' => 'admin', 'name' => '插件中心 / ' . $action, 'code' => $resource['code'], 'obj' => $resource['obj'], 'act' => $resource['act'], 'resource_type' => 'route', 'source_type' => 'system', 'source_name' => 'plugin_center', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    });
};
