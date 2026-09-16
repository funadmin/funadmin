-- 将插件中心从系统管理子菜单迁移为独立一级菜单。
UPDATE `fun_admin_menu`
SET `pid`=0,
    `href`='/plugin',
    `query`='component=system/plugin/index&name=SystemPlugin&type=C&permission=system:plugin:list',
    `icon`='i-ep-grid',
    `status`=1,
    `sort`=30,
    `sort_order`=30,
    `updated_at`=NOW()
WHERE `source_type`='admin_web'
  AND `source_name`='plugin_center'
  AND `deleted_at` IS NULL;
