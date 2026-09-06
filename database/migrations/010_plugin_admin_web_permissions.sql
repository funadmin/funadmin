-- 阶段三：Admin Web 插件中心菜单与 system:plugin:* 权限。

SET @system_menu_id = (
  SELECT `id` FROM `fun_admin_menu`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'system_management'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `fun_permission`
(`pid`,`module`,`code`,`obj`,`act`,`title`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`)
SELECT 0, 'backend', NULL, '', '', '插件中心', 'group', 1, 0, 340, 'admin_web', 'plugin_center', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type` = 'admin_web' AND `source_name` = 'plugin_center' AND `resource_type` = 'group');

SET @plugin_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'plugin_center' AND `resource_type` = 'group'
  ORDER BY `id` ASC LIMIT 1
);

INSERT IGNORE INTO `fun_permission`
(`pid`,`module`,`code`,`obj`,`act`,`title`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`) VALUES
(@plugin_permission_id,'backend','system:plugin:list','backend/systemplugin','installed','查看插件','route',1,0,10,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:account','backend/systemplugin','accountLogin','市场账号','route',1,0,20,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:install','backend/systemplugin','installLocal','安装插件','route',1,0,30,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:update','backend/systemplugin','update','更新插件','route',1,0,40,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:migrate','backend/systemplugin','migrate','迁移插件','route',1,0,50,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:enable','backend/systemplugin','enable','启用插件','route',1,0,60,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:disable','backend/systemplugin','disable','禁用插件','route',1,0,70,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:config','backend/systemplugin','saveConfig','插件配置','route',1,0,80,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:uninstall','backend/systemplugin','uninstall','卸载插件','route',1,0,90,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:package-delete','backend/systemplugin','deletePackage','删除本地包','route',1,0,100,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','system:plugin:history','backend/systemplugin','history','操作历史','route',1,0,110,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

INSERT INTO `fun_admin_menu`
(`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`)
SELECT COALESCE(@system_menu_id, 0), @plugin_permission_id, 'backend', '插件中心', 'plugin',
       'component=system/plugin/index&name=SystemPlugin&type=C&permission=system:plugin:list',
       '_self', 'i-ep-box', 1, 340, 'admin_web', 'plugin_center', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type` = 'admin_web' AND `source_name` = 'plugin_center');
