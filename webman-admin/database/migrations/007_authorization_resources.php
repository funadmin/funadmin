<?php

declare(strict_types=1);

use support\Db;

return static function (): void {
    $now = date('Y-m-d H:i:s');
    $modules = [
        'role' => ['角色管理', 'SystemRole', ['index', 'all', 'parentOptions', 'permissionTree', 'detail', 'create', 'update', 'delete', 'deleteById', 'authorization', 'saveAuthorization', 'copyAuthorization', 'permissions']],
        'user' => ['管理员', 'SystemAdmin', ['index', 'detail', 'create', 'update', 'delete', 'deleteById', 'resetPassword', 'status']],
        'dept' => ['部门管理', 'SystemDepartment', ['tree', 'detail', 'create', 'update', 'delete', 'deleteById']],
        'menu' => ['菜单管理', 'SystemMenu', ['tree', 'permissionOptions', 'detail', 'create', 'update', 'delete', 'deleteById']],
        'permission' => ['权限资源', 'SystemPermission', ['tree', 'detail', 'create', 'update', 'delete', 'deleteById']],
    ];
    Db::transaction(static function () use ($modules, $now): void {
        foreach ($modules as $module => [$label, $controller, $actions]) {
            foreach ($actions as $action) {
                $resource = \app\admin\authorization\service\PermissionResource::fromParts('admin', $controller, $action);
                if (Db::table('permission')->where('code', $resource['code'])->exists()) continue;
                Db::table('permission')->insert([
                    'app_name' => 'admin', 'name' => $label . ' / ' . $action,
                    'code' => $resource['code'], 'obj' => $resource['obj'], 'act' => $resource['act'],
                    'resource_type' => 'route', 'source_type' => 'system', 'source_name' => 'authorization',
                    'status' => 1, 'is_public' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            $path = '/admin/system/' . $module;
            if (Db::table('admin_menu')->where('app_name', 'admin')->where('href', $path)->exists()) continue;
            $action = in_array($module, ['menu', 'permission', 'dept'], true) ? 'tree' : 'index';
            $resource = \app\admin\authorization\service\PermissionResource::fromParts('admin', $controller, $action);
            $permissionId = Db::table('permission')->where('code', $resource['code'])->value('id');
            Db::table('admin_menu')->insert([
                'app_name' => 'admin', 'name' => $label, 'href' => $path, 'permission_id' => $permissionId,
                'query' => http_build_query(['name' => $controller, 'type' => 'C', 'component' => 'system/' . $module . '/index', 'permission' => $resource['code']]),
                'source_type' => 'admin_web', 'source_name' => 'authorization', 'status' => 1,
                'sort_order' => 10 + array_search($module, array_keys($modules), true),
                'icon' => ['role' => 'i-ep-key', 'user' => 'i-ep-user', 'dept' => 'i-ep-office-building', 'menu' => 'i-ep-menu', 'permission' => 'i-ep-lock'][$module],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    });
};
