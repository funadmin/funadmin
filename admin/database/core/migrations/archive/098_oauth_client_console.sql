-- 098 企业 SSO Phase 3 Console 权限与应用中心菜单；只向前、可重复执行。
SET @application_group_id=(SELECT `id` FROM `fun_permission` WHERE `code`='identity:application' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT COALESCE(@application_group_id,0),'console','identity:oauth-client:view','identity/oauth-client','view','查看 OAuth Client','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),130,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='identity:oauth-client:view');
SET @oauth_view_id=(SELECT `id` FROM `fun_permission` WHERE `code`='identity:oauth-client:view' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@oauth_view_id,'console','console/identity.oauthclient:index','console/identity.oauthclient','index','OAuth Client 列表','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),10,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:detail','console/identity.oauthclient','detail','OAuth Client 详情','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),20,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:save','console/identity.oauthclient','save','创建 OAuth Client','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),30,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:update','console/identity.oauthclient','update','更新 OAuth Client','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),40,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:delete','console/identity.oauthclient','delete','删除 OAuth Client','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),50,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:disable','console/identity.oauthclient','disable','禁用 OAuth Client','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),60,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:secrets','console/identity.oauthclient','secrets','查看 Secret','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),70,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:createsecret','console/identity.oauthclient','createsecret','创建 Secret','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),80,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:revokesecret','console/identity.oauthclient','revokesecret','吊销 Secret','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),90,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:replaceredirecturis','console/identity.oauthclient','replaceredirecturis','替换 Redirect URI','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),100,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:replacescopes','console/identity.oauthclient','replacescopes','替换 Scope','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),110,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:replacegrants','console/identity.oauthclient','replacegrants','替换 Grant','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),120,NULL),
(@oauth_view_id,'console','console/identity.oauthclient:revoketokens','console/identity.oauthclient','revoketokens','吊销 Token','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),130,NULL)
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT COALESCE(@application_group_id,0),'console','identity:signing-key:manage','identity/signing-key','manage','管理签名 Key','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),140,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='identity:signing-key:manage');
SET @signing_key_id=(SELECT `id` FROM `fun_permission` WHERE `code`='identity:signing-key:manage' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@signing_key_id,'console','console/identity.oidcsigningkey:index','console/identity.oidcsigningkey','index','签名 Key 列表','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),10,NULL),
(@signing_key_id,'console','console/identity.oidcsigningkey:rotate','console/identity.oidcsigningkey','rotate','轮换签名 Key','route',1,0,'admin_web','oauth_client_console',NOW(),NOW(),20,NULL)
ON DUPLICATE KEY UPDATE `id`=`id`;

SET @application_menu_id=(SELECT `id` FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `href`='/applications' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @application_menu_id,@oauth_view_id,'console',CONVERT(X'4F4175746820436C69656E74' USING utf8mb4),'oauth-client','component=applications/oauth/index&name=OAuthClientManagement&type=C&permission=identity:oauth-client:view','_self','i-ep-key',1,'admin_web','oauth_client_console',NOW(),NOW(),20,NULL
WHERE @application_menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='oauth_client_console' AND `href`='oauth-client');
