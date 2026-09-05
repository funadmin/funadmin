-- 插件中心剩余独立 action 权限；仅向前补齐，不修改已执行 010。
SET @plugin_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'plugin_center' AND `resource_type` = 'group'
  ORDER BY `id` ASC LIMIT 1
);

INSERT IGNORE INTO `fun_permission`
(`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`) VALUES
(@plugin_permission_id,'backend','backend/systemplugin:accountrefresh','backend/systemplugin','accountrefresh','刷新市场令牌','route',1,0,28,'admin_web','plugin_center',NOW(),NOW()),
(@plugin_permission_id,'backend','backend/systemplugin:installdiscovered','backend/systemplugin','installdiscovered','安装发现插件','route',1,0,32,'admin_web','plugin_center',NOW(),NOW()),
(@plugin_permission_id,'backend','backend/systemplugin:updatelocal','backend/systemplugin','updatelocal','本地 ZIP 更新插件','route',1,0,41,'admin_web','plugin_center',NOW(),NOW()),
(@plugin_permission_id,'backend','backend/systemplugin:purge','backend/systemplugin','purge','清除插件数据','route',1,0,91,'admin_web','plugin_center',NOW(),NOW()),
(@plugin_permission_id,'backend','backend/systemplugin:downloadhistory','backend/systemplugin','downloadhistory','下载历史插件包','route',1,0,112,'admin_web','plugin_center',NOW(),NOW()),
(@plugin_permission_id,'backend','backend/systemplugin:redeployhistory','backend/systemplugin','redeployhistory','重部署历史插件包','route',1,0,113,'admin_web','plugin_center',NOW(),NOW()),
(@plugin_permission_id,'backend','backend/systemplugin:recoveryinfo','backend/systemplugin','recoveryinfo','查看插件恢复指引','route',1,0,114,'admin_web','plugin_center',NOW(),NOW());

-- 前端权限：system:plugin:account-refresh、system:plugin:discovered-install、system:plugin:local-update、
-- system:plugin:purge、system:plugin:history-download、system:plugin:history-redeploy、system:plugin:recovery。
