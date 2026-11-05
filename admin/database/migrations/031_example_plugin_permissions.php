<?php

declare(strict_types=1);

use support\Db;

return static function (): void {
    $resources = ['entry' => ['index'], 'test' => ['list', 'detail', 'create', 'update', 'delete', 'restore', 'destroy', 'batch-delete', 'batch-restore', 'batch-destroy', 'import', 'export']];
    foreach ($resources as $object => $actions) {
        foreach ($actions as $action) {
            $code = 'example:' . $object . ':' . $action;
            if (Db::table('permission')->where('app_name', 'admin')->where('code', $code)->exists()) continue;
            Db::table('permission')->insert([
                'app_name' => 'admin', 'code' => $code, 'name' => '示例插件 / ' . $object . ':' . $action,
                'obj' => 'example/' . $object, 'act' => $action, 'resource_type' => 'route',
                'source_type' => 'plugin', 'source_name' => 'example', 'status' => 1, 'is_public' => 0,
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
};
