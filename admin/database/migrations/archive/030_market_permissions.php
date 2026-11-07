<?php

declare(strict_types=1);

use support\Db;

return static function (): void {
    foreach (['plugin:view', 'plugin:manage', 'category:manage', 'grant:manage', 'order:view', 'order:manage', 'setting:manage', 'stat:view'] as $resource) {
        [$object, $action] = explode(':', $resource);
        $code = 'market:' . $resource;
        if (Db::table('permission')->where('app_name', 'admin')->where('code', $code)->exists()) continue;
        Db::table('permission')->insert([
            'app_name' => 'admin', 'code' => $code, 'name' => '插件市场 / ' . $resource,
            'obj' => 'market/' . $object, 'act' => $action, 'resource_type' => 'route',
            'source_type' => 'plugin', 'source_name' => 'market', 'status' => 1, 'is_public' => 0,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
};
