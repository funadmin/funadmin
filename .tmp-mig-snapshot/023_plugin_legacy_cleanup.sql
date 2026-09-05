-- 阶段四：停用旧 Layui 插件入口，保留 Admin Web system:plugin:* 权限与菜单。

UPDATE `fun_permission`
SET `status` = 0, `updated_at` = NOW()
WHERE `code` LIKE 'backend/plugin:%'
   OR `obj` = 'backend/plugin';

UPDATE `fun_admin_menu`
SET `status` = 0, `updated_at` = NOW()
WHERE `module` = 'backend'
  AND (`href` = 'backend/plugin' OR `href` LIKE 'backend/plugin/%');

UPDATE `fun_permission`
SET `status` = 1, `updated_at` = NOW()
WHERE `code` LIKE 'system:plugin:%'
  AND `source_type` = 'admin_web'
  AND `source_name` = 'plugin_center';

UPDATE `fun_admin_menu`
SET `status` = 1, `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'plugin_center';