-- 插件中心实际控制器 action 权限补偿；只向前补齐，不修改已执行的 010。
SET @plugin_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'plugin_center' AND `resource_type` = 'group'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:installed','backend/systemplugin','installed','查看已安装插件','route',1,0,10,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:installed');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:discovered','backend/systemplugin','discovered','查看本地插件','route',1,0,11,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:discovered');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:localdetail','backend/systemplugin','localdetail','查看插件详情','route',1,0,12,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:localdetail');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:enabledmodules','backend/systemplugin','enabledmodules','查看插件模块','route',1,0,13,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:enabledmodules');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:accountlogin','backend/systemplugin','accountlogin','登录市场账号','route',1,0,20,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:accountlogin');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:accountlogout','backend/systemplugin','accountlogout','退出市场账号','route',1,0,21,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:accountlogout');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:currentaccount','backend/systemplugin','currentaccount','查看市场账号','route',1,0,22,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:currentaccount');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:marketcategories','backend/systemplugin','marketcategories','查看市场分类','route',1,0,23,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:marketcategories');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:marketsearch','backend/systemplugin','marketsearch','搜索插件市场','route',1,0,24,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:marketsearch');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:marketdetail','backend/systemplugin','marketdetail','查看市场插件','route',1,0,25,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:marketdetail');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:marketversions','backend/systemplugin','marketversions','查看市场版本','route',1,0,26,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:marketversions');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:checkupdates','backend/systemplugin','checkupdates','检查插件更新','route',1,0,27,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:checkupdates');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:accountrefresh','backend/systemplugin','accountrefresh','刷新市场令牌','route',1,0,28,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:accountrefresh');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:installlocal','backend/systemplugin','installlocal','本地安装插件','route',1,0,30,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:installlocal');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:installcloud','backend/systemplugin','installcloud','云端安装插件','route',1,0,31,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:installcloud');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:installdiscovered','backend/systemplugin','installdiscovered','安装发现插件','route',1,0,32,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:installdiscovered');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:update','backend/systemplugin','update','更新插件','route',1,0,40,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:update');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:updatelocal','backend/systemplugin','updatelocal','本地 ZIP 更新插件','route',1,0,41,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:updatelocal');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:migrate','backend/systemplugin','migrate','迁移插件','route',1,0,50,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:migrate');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:enable','backend/systemplugin','enable','启用插件','route',1,0,60,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:enable');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:disable','backend/systemplugin','disable','禁用插件','route',1,0,70,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:disable');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:getconfig','backend/systemplugin','getconfig','查看插件配置','route',1,0,80,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:getconfig');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:saveconfig','backend/systemplugin','saveconfig','保存插件配置','route',1,0,81,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:saveconfig');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:uninstall','backend/systemplugin','uninstall','卸载插件','route',1,0,90,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:uninstall');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:purge','backend/systemplugin','purge','清除插件数据','route',1,0,91,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:purge');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:deletepackage','backend/systemplugin','deletepackage','删除本地包','route',1,0,100,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:deletepackage');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:history','backend/systemplugin','history','查看版本历史','route',1,0,110,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:history');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:operations','backend/systemplugin','operations','查看操作历史','route',1,0,111,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:operations');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:downloadhistory','backend/systemplugin','downloadhistory','下载历史插件包','route',1,0,112,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:downloadhistory');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:redeployhistory','backend/systemplugin','redeployhistory','重部署历史插件包','route',1,0,113,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:redeployhistory');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @plugin_permission_id,'backend','backend/systemplugin:recoveryinfo','backend/systemplugin','recoveryinfo','查看插件恢复指引','route',1,0,114,'admin_web','plugin_center',NOW(),NOW()
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='backend/systemplugin:recoveryinfo');
