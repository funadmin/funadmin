-- 插件中心实际控制器 action 权限补偿；只向前补齐，不修改已执行的 010。
SET @plugin_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'plugin_center' AND `resource_type` = 'group'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:installed','console/systemplugin','installed','查看已安装插件','route',1,0,10,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:installed');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:discovered','console/systemplugin','discovered','查看本地插件','route',1,0,11,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:discovered');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:localdetail','console/systemplugin','localdetail','查看插件详情','route',1,0,12,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:localdetail');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:enabledmodules','console/systemplugin','enabledmodules','查看插件模块','route',1,0,13,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:enabledmodules');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:accountlogin','console/systemplugin','accountlogin','登录市场账号','route',1,0,20,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:accountlogin');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:accountlogout','console/systemplugin','accountlogout','退出市场账号','route',1,0,21,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:accountlogout');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:currentaccount','console/systemplugin','currentaccount','查看市场账号','route',1,0,22,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:currentaccount');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:marketcategories','console/systemplugin','marketcategories','查看市场分类','route',1,0,23,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:marketcategories');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:marketsearch','console/systemplugin','marketsearch','搜索插件市场','route',1,0,24,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:marketsearch');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:marketdetail','console/systemplugin','marketdetail','查看市场插件','route',1,0,25,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:marketdetail');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:marketversions','console/systemplugin','marketversions','查看市场版本','route',1,0,26,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:marketversions');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:checkupdates','console/systemplugin','checkupdates','检查插件更新','route',1,0,27,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:checkupdates');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:accountrefresh','console/systemplugin','accountrefresh','刷新市场令牌','route',1,0,28,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:accountrefresh');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:installlocal','console/systemplugin','installlocal','本地安装插件','route',1,0,30,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:installlocal');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:installcloud','console/systemplugin','installcloud','云端安装插件','route',1,0,31,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:installcloud');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:installdiscovered','console/systemplugin','installdiscovered','安装发现插件','route',1,0,32,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:installdiscovered');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:update','console/systemplugin','update','更新插件','route',1,0,40,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:update');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:updatelocal','console/systemplugin','updatelocal','本地 ZIP 更新插件','route',1,0,41,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:updatelocal');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:migrate','console/systemplugin','migrate','迁移插件','route',1,0,50,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:migrate');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:enable','console/systemplugin','enable','启用插件','route',1,0,60,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:enable');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:disable','console/systemplugin','disable','禁用插件','route',1,0,70,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:disable');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:getconfig','console/systemplugin','getconfig','查看插件配置','route',1,0,80,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:getconfig');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:saveconfig','console/systemplugin','saveconfig','保存插件配置','route',1,0,81,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:saveconfig');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:uninstall','console/systemplugin','uninstall','卸载插件','route',1,0,90,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:uninstall');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:purge','console/systemplugin','purge','清除插件数据','route',1,0,91,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:purge');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:deletepackage','console/systemplugin','deletepackage','删除本地包','route',1,0,100,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:deletepackage');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:history','console/systemplugin','history','查看版本历史','route',1,0,110,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:history');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:operations','console/systemplugin','operations','查看操作历史','route',1,0,111,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:operations');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:downloadhistory','console/systemplugin','downloadhistory','下载历史插件包','route',1,0,112,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:downloadhistory');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:redeployhistory','console/systemplugin','redeployhistory','重部署历史插件包','route',1,0,113,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:redeployhistory');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'console','console/systemplugin:recoveryinfo','console/systemplugin','recoveryinfo','查看插件恢复指引','route',1,0,114,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemplugin:recoveryinfo');
