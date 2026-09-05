-- 阶段三：Admin Web 插件中心菜单与控制器 action 权限。
-- 前端权限分组：system:plugin:list、system:plugin:account、system:plugin:install、system:plugin:update、
-- system:plugin:migrate、system:plugin:enable、system:plugin:disable、system:plugin:config、
-- system:plugin:uninstall、system:plugin:package-delete、system:plugin:history。
-- 每个实际 action 使用唯一 backend/systemplugin:* code，AdminAuth 负责映射到上述前端权限。

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
(@plugin_permission_id,'backend','backend/systemplugin:installed','backend/systemplugin','installed','查看已安装插件','route',1,0,10,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:discovered','backend/systemplugin','discovered','查看本地插件','route',1,0,11,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:localdetail','backend/systemplugin','localdetail','查看插件详情','route',1,0,12,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:enabledmodules','backend/systemplugin','enabledmodules','查看插件模块','route',1,0,13,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:accountlogin','backend/systemplugin','accountlogin','登录市场账号','route',1,0,20,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:accountlogout','backend/systemplugin','accountlogout','退出市场账号','route',1,0,21,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:currentaccount','backend/systemplugin','currentaccount','查看市场账号','route',1,0,22,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:marketcategories','backend/systemplugin','marketcategories','查看市场分类','route',1,0,23,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:marketsearch','backend/systemplugin','marketsearch','搜索插件市场','route',1,0,24,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:marketdetail','backend/systemplugin','marketdetail','查看市场插件','route',1,0,25,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:marketversions','backend/systemplugin','marketversions','查看市场版本','route',1,0,26,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:checkupdates','backend/systemplugin','checkupdates','检查插件更新','route',1,0,27,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:installlocal','backend/systemplugin','installlocal','本地安装插件','route',1,0,30,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:installcloud','backend/systemplugin','installcloud','云端安装插件','route',1,0,31,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:update','backend/systemplugin','update','更新插件','route',1,0,40,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:migrate','backend/systemplugin','migrate','迁移插件','route',1,0,50,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:enable','backend/systemplugin','enable','启用插件','route',1,0,60,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:disable','backend/systemplugin','disable','禁用插件','route',1,0,70,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:getconfig','backend/systemplugin','getconfig','查看插件配置','route',1,0,80,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:saveconfig','backend/systemplugin','saveconfig','保存插件配置','route',1,0,81,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:uninstall','backend/systemplugin','uninstall','卸载插件','route',1,0,90,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:deletepackage','backend/systemplugin','deletepackage','删除本地包','route',1,0,100,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:history','backend/systemplugin','history','查看版本历史','route',1,0,110,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP()),
(@plugin_permission_id,'backend','backend/systemplugin:operations','backend/systemplugin','operations','查看操作历史','route',1,0,111,'admin_web','plugin_center',UNIX_TIMESTAMP(),UNIX_TIMESTAMP());

INSERT INTO `fun_admin_menu`
(`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`)
SELECT COALESCE(@system_menu_id, 0), @plugin_permission_id, 'backend', '插件中心', 'plugin',
       'component=system/plugin/index&name=SystemPlugin&type=C&permission=system:plugin:list',
       '_self', 'i-ep-box', 1, 340, 'admin_web', 'plugin_center', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type` = 'admin_web' AND `source_name` = 'plugin_center');
