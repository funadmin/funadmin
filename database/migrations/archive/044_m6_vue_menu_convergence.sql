-- M6 插件中心与系统升级 Vue 菜单权限收敛；仅更新归属和状态，不删除旧入口。
SET @system_menu_id = (
  SELECT `id` FROM `fun_admin_menu`
  WHERE `source_type`='admin_web' AND `source_name`='system_management'
  ORDER BY `id` LIMIT 1
);
SET @plugin_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `code`='system:plugin:list'
  ORDER BY `id` LIMIT 1
);
SET @upgrade_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `code`='system:upgrade:list'
  ORDER BY `id` LIMIT 1
);

UPDATE `fun_admin_menu`
SET `pid`=COALESCE(@system_menu_id,0),
    `permission_id`=COALESCE(@plugin_permission_id,0),
    `href`='plugin',
    `query`='component=system/plugin/index&name=SystemPlugin&type=C&permission=system:plugin:list',
    `status`=1,
    `updated_at`=NOW()
WHERE `source_type`='admin_web' AND `source_name`='plugin_center';

UPDATE `fun_admin_menu`
SET `pid`=COALESCE(@system_menu_id,0),
    `permission_id`=COALESCE(@upgrade_permission_id,0),
    `href`='upgrade',
    `query`='component=system/upgrade/index&name=SystemUpgrade&type=C&permission=system:upgrade:list',
    `status`=1,
    `updated_at`=NOW()
WHERE `source_type`='admin_web' AND `source_name`='system_upgrade';

UPDATE `fun_permission`
SET `status`=0, `updated_at`=NOW()
WHERE `code` LIKE 'backend/plugin:%' OR `code` LIKE 'backend/upgrade:%';

UPDATE `fun_admin_menu`
SET `status`=0, `updated_at`=NOW()
WHERE NOT (`source_type`='admin_web' AND `source_name` IN ('plugin_center','system_upgrade'))
  AND (`href`='backend/plugin' OR `href` LIKE 'backend/plugin/%' OR `href`='backend/upgrade' OR `href` LIKE 'backend/upgrade/%');

UPDATE `fun_permission`
SET `status`=1, `updated_at`=NOW()
WHERE `code` IN ('system:plugin:list','system:upgrade:list')
  AND `source_type`='admin_web';