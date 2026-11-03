<?php

declare(strict_types=1);

use support\Db;

return static function (): void {
    $now = date('Y-m-d H:i:s');

    Db::transaction(static function () use ($now): void {
        $modules = [
            'system_management' => [
                'name' => '系统管理',
                'href' => '/system',
                'sort_order' => 10,
                'icon' => 'i-ep-setting',
                'children' => [
                    'user', 'role', 'dept', 'menu', 'permission', 'operation_log', 'blacklist', 'language', 'attachment', 'config', 'system_upgrade', 'dictionary',
                ],
            ],
            'user_management' => [
                'name' => '用户管理',
                'href' => '/users',
                'sort_order' => 20,
                'icon' => 'i-ep-user',
                'children' => ['member', 'member_group', 'member_level'],
            ],
            'plugin_management' => [
                'name' => '插件管理',
                'href' => '/plugins',
                'sort_order' => 30,
                'icon' => 'i-ep-box',
                'children' => [
                    ['href' => '/plugin', 'target_href' => '/plugin', 'source_type' => 'admin_web', 'source_name' => 'plugin_center'],
                    ['href' => '/admin/system/plugin', 'target_href' => '/plugin', 'source_type' => 'admin_web', 'source_name' => 'plugin_center'],
                    ['href' => '/plugin/etst/items', 'target_href' => '/plugin/etst/items', 'source_type' => 'plugin', 'source_name' => 'etst'],
                ],
            ],
            'development_tools' => [
                'name' => '开发工具',
                'href' => '/development',
                'sort_order' => 40,
                'icon' => 'i-ep-tools',
                'children' => ['development_crud', 'form_list', 'form_designer', 'business_development', 'ai_development'],
            ],
            'identity_management' => [
                'name' => '身份与应用',
                'href' => '/identity',
                'sort_order' => 50,
                'icon' => 'i-ep-connection',
                'children' => [
                    ['href' => '/applications', 'source_type' => 'admin_web', 'source_name' => 'enterprise_application_center'],
                ],
            ],
        ];

        foreach ($modules as $sourceName => $module) {
            $parent = Db::table('admin_menu')
                ->where('app_name', 'admin')
                ->where('source_type', 'admin_web')
                ->where('source_name', $sourceName)
                ->first();
            $createdParent = false;
            if ($parent === null) {
                $parentId = (int) Db::table('admin_menu')->insertGetId([
                    'pid' => 0,
                    'permission_id' => 0,
                    'app_name' => 'admin',
                    'name' => $module['name'],
                    'href' => $module['href'],
                    'query' => http_build_query(['name' => ucfirst($sourceName), 'type' => 'M', 'component' => 'Layout']),
                    'target' => '_self',
                    'icon' => $module['icon'],
                    'status' => 1,
                    'sort_order' => $module['sort_order'],
                    'source_type' => 'admin_web',
                    'source_name' => $sourceName,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $createdParent = true;
            } else {
                $parentId = (int) $parent->id;
                Db::table('admin_menu')->where('id', $parentId)->update([
                    'pid' => 0,
                    'name' => $module['name'],
                    'href' => $module['href'],
                    'sort_order' => $module['sort_order'],
                    'icon' => $module['icon'],
                    'updated_at' => $now,
                ]);
            }

            foreach ($module['children'] as $child) {
                if (is_string($child)) {
                    Db::table('admin_menu')
                        ->where('app_name', 'admin')
                        ->where('source_type', 'admin_web')
                        ->where('source_name', $child)
                        ->update(['pid' => $parentId, 'updated_at' => $now]);
                    continue;
                }
                Db::table('admin_menu')
                    ->where('app_name', 'admin')
                    ->where('source_type', $child['source_type'])
                    ->where('source_name', $child['source_name'])
                    ->update(['pid' => $parentId, 'href' => $child['target_href'] ?? $child['href'], 'updated_at' => $now]);
            }
            if ($createdParent && Db::table('admin_menu')->where('pid', $parentId)->count() === 0) {
                Db::table('admin_menu')->where('id', $parentId)->delete();
            }
        }

        $moveByHref = static function (int $parentId, array $hrefs, array $names = []) use ($now): void {
            $query = Db::table('admin_menu')->where('app_name', 'admin')->where('pid', 0)->where('status', 1);
            $query->where(function ($builder) use ($hrefs, $names): void {
                foreach ($hrefs as $href) $builder->orWhere('href', $href);
                foreach ($names as $name) $builder->orWhere('name', $name);
            });
            foreach ($query->get() as $menu) {
                Db::table('admin_menu')->where('id', $menu->id)->update([
                    'pid' => $parentId,
                    'updated_at' => $now,
                ]);
            }
        };
        $systemId = (int) Db::table('admin_menu')->where('app_name', 'admin')->where('pid', 0)->where('name', '系统管理')->value('id');
        $userId = (int) Db::table('admin_menu')->where('app_name', 'admin')->where('pid', 0)->where('name', '用户管理')->value('id');
        $developmentId = (int) Db::table('admin_menu')->where('app_name', 'admin')->where('pid', 0)->where('name', '开发工具')->value('id');
        $pluginId = (int) Db::table('admin_menu')->where('app_name', 'admin')->where('pid', 0)->where('name', '插件管理')->value('id');
        $identityId = (int) Db::table('admin_menu')->where('app_name', 'admin')->where('pid', 0)->where('name', '身份与应用')->value('id');
        $moveByHref($systemId, ['/admin/system/user', '/admin/system/role', '/admin/system/dept', '/admin/system/menu', '/admin/system/permission', '/admin/system/log/operation', '/admin/system/blacklist', '/admin/system/language', '/admin/system/attachment', '/admin/system/config', '/admin/system/upgrade', '/admin/system/dict'], ['管理员管理', '角色管理', '部门管理', '菜单管理', '权限资源', '操作日志', '黑名单', '多语言', '附件库', '配置管理', '系统升级', '字典管理']);
        $moveByHref($userId, ['/admin/system/member', '/admin/system/member-group', '/admin/system/member-level'], ['会员管理', '会员组', '会员等级']);
        $moveByHref($pluginId, ['/admin/system/plugin', '/plugin', '/plugin/etst/items'], ['插件中心', 'etst']);
        $moveByHref($identityId, ['/admin/identity/applications', '/applications'], ['应用中心', '企业应用中心']);
        $moveByHref($developmentId, ['/generated/i18n-demo'], ['i18n验收演示']);

        $development = Db::table('admin_menu')
            ->where('app_name', 'admin')
            ->where('source_type', 'admin_web')
            ->where('source_name', 'development_tools')
            ->value('id');
        if ($development !== null) {
            Db::table('admin_menu')
                ->where('app_name', 'admin')
                ->where('source_type', 'generated')
                ->where('source_name', 'i18n-demo')
                ->update(['pid' => (int) $development, 'updated_at' => $now]);
        }
    });
};
