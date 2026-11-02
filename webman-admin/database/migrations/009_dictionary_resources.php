<?php

declare(strict_types=1);

use support\Db;

return static function (): void {
    $now = date('Y-m-d H:i:s');
    $permissions = [
        'types' => '字典类型列表',
        'items' => '字典项列表',
        'options' => '字典选项',
        'batch' => '批量字典选项',
        'createtype' => '创建字典类型',
        'updatetype' => '更新字典类型',
        'deletetype' => '删除字典类型',
        'deletetypes' => '批量删除字典类型',
        'createitem' => '创建字典项',
        'updateitem' => '更新字典项',
        'deleteitem' => '删除字典项',
        'deleteitems' => '批量删除字典项',
    ];

    Db::transaction(static function () use ($now, $permissions): void {
        foreach ($permissions as $action => $name) {
            $code = 'systemdict:' . $action;
            if (Db::table('permission')->where('code', $code)->exists()) {
                continue;
            }
            Db::table('permission')->insert([
                'app_name' => 'admin',
                'name' => $name,
                'code' => $code,
                'obj' => 'admin/systemdict',
                'act' => $action,
                'resource_type' => 'route',
                'source_type' => 'system',
                'source_name' => 'dictionary',
                'status' => 1,
                'is_public' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!Db::table('admin_menu')->where('app_name', 'admin')->where('href', '/admin/system/dict')->exists()) {
            Db::table('admin_menu')->insert([
                'app_name' => 'admin',
                'name' => '字典管理',
                'href' => '/admin/system/dict',
                'permission_id' => (int) Db::table('permission')->where('code', 'systemdict:types')->value('id'),
                'query' => http_build_query([
                    'name' => 'SystemDict',
                    'type' => 'C',
                    'component' => 'system/dict/index',
                    'permission' => 'systemdict:types',
                ]),
                'source_type' => 'admin_web',
                'source_name' => 'dictionary',
                'status' => 1,
                'sort_order' => 30,
                'icon' => 'i-ep-collection',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    });
};
