-- 114 企业 SSO Phase 8 管理控制台与应用门户；只向前、可重复执行。
SET @schema_name=DATABASE();
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_scope' AND COLUMN_NAME='claims'),'DO 0','ALTER TABLE `fun_scope` ADD COLUMN `claims` json NULL AFTER `description`'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
CREATE TABLE IF NOT EXISTS `fun_identity_sso_config` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `provider_mode` varchar(32) NOT NULL DEFAULT 'identity_provider',
  `issuer` varchar(2048) NOT NULL DEFAULT '',
  `external_identity_enabled` tinyint NOT NULL DEFAULT 0,
  `backchannel_logout_enabled` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_identity_sso_config_tenant` (`tenant_id`),
  CONSTRAINT `fk_identity_sso_config_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant SSO configuration';

-- 复用 Phase 2 应用中心权限组，避免创建第二个同名目录。
SET @group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@group_id,'console','console/identity.ssoconfiguration:config','console/identity.ssoconfiguration','config',CONVERT(X'53534F20E9858DE7BDAE' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),10,NULL),
(@group_id,'console','console/identity.ssoconfiguration:save','console/identity.ssoconfiguration','save',CONVERT(X'E4BF9DE5AD982053534F20E9858DE7BDAE' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),11,NULL),
(@group_id,'console','console/identity.ssoconfiguration:check','console/identity.ssoconfiguration','check',CONVERT(X'53534F20E9858DE7BDAEE887AAE6A380' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),12,NULL),
(@group_id,'console','console/identity.scopeclaim:index','console/identity.scopeclaim','index',CONVERT(X'53636F706520E4B88E20436C61696D20E58897E8A1A8' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),20,NULL),
(@group_id,'console','console/identity.scopeclaim:save','console/identity.scopeclaim','save',CONVERT(X'E4BF9DE5AD982053636F706520E4B88E20436C61696D' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),21,NULL),
(@group_id,'console','console/identity.scopeclaim:update','console/identity.scopeclaim','update',CONVERT(X'E69BB4E696B02053636F706520E4B88E20436C61696D' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),22,NULL),
(@group_id,'console','console/identity.scopeclaim:delete','console/identity.scopeclaim','delete',CONVERT(X'E588A0E999A42053636F706520E4B88E20436C61696D' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),23,NULL),
(@group_id,'console','console/identity.identityuser:index','console/identity.identityuser','index',CONVERT(X'E8BAABE4BBBDE794A8E688B7E58897E8A1A8' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),30,NULL),
(@group_id,'console','console/identity.identityuser:detail','console/identity.identityuser','detail',CONVERT(X'E8BAABE4BBBDE794A8E688B7E8AFA6E68385' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),31,NULL),
(@group_id,'console','console/identity.identityuser:links','console/identity.identityuser','links',CONVERT(X'E8BAABE4BBBDE585B3E88194' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),32,NULL),
(@group_id,'console','console/identity.identityuser:sessions','console/identity.identityuser','sessions',CONVERT(X'E8BAABE4BBBDE794A8E688B7E4BC9AE8AF9D' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),33,NULL),
(@group_id,'console','console/identity.identityuser:authorizations','console/identity.identityuser','authorizations',CONVERT(X'E8BAABE4BBBDE794A8E688B7E68E88E69D83' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),34,NULL),
(@group_id,'console','console/identity.identityuser:revokesessions','console/identity.identityuser','revokesessions',CONVERT(X'E692A4E99480E794A8E688B7E4BC9AE8AF9D' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),35,NULL),
(@group_id,'console','console/identity.identityuser:revokeauthorizations','console/identity.identityuser','revokeauthorizations',CONVERT(X'E692A4E99480E794A8E688B7E68E88E69D83' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),36,NULL),
(@group_id,'console','console/identity.identityaudit:index','console/identity.identityaudit','index',CONVERT(X'E799BBE5BD95E5AEA1E8AEA1' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),40,NULL),
(@group_id,'console','console/identity.enterpriseapplication:portal','console/identity.enterpriseapplication','portal',CONVERT(X'E5BA94E794A8E997A8E688B7' USING utf8mb4),'route',1,0,'admin_web','sso_phase8_application_center',NOW(),NOW(),41,NULL);

SET @menu_id=(SELECT `id` FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `href`='/applications' ORDER BY `id` LIMIT 1);
SET @application_view_id=(SELECT `id` FROM `fun_permission` WHERE `code`='identity:application:view' ORDER BY `id` LIMIT 1);
SET @application_manage_id=(SELECT `id` FROM `fun_permission` WHERE `code`='identity:application:manage' ORDER BY `id` LIMIT 1);
SET @oauth_client_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.oauthclient:index' ORDER BY `id` LIMIT 1);
SET @sso_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.ssoconfiguration:config' ORDER BY `id` LIMIT 1);
SET @scope_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.scopeclaim:index' ORDER BY `id` LIMIT 1);
SET @identity_user_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.identityuser:index' ORDER BY `id` LIMIT 1);
SET @session_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.oidcsession:index' ORDER BY `id` LIMIT 1);
SET @signing_key_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.oidcsigningkey:index' ORDER BY `id` LIMIT 1);
SET @audit_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.identityaudit:index' ORDER BY `id` LIMIT 1);
SET @portal_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/identity.enterpriseapplication:portal' ORDER BY `id` LIMIT 1);
UPDATE `fun_admin_menu` SET `name`=CONVERT(X'E5BA94E794A8E58897E8A1A8' USING utf8mb4),`query`='component=applications/index&name=ApplicationList&type=C&permission=identity:application:view' WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `href`='center';
-- Existing Phase 2 application list route: component=applications/index&name=ApplicationList
-- Existing Phase 3 OAuth route is updated below: component=applications/oauth/index&name=OAuthClientManagement
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @menu_id,@application_manage_id,'console',CONVERT(X'E59F9FE5908DE7AEA1E79086' USING utf8mb4),'domains','component=applications/identity-management&name=DomainManagement&type=C&permission=identity:application:manage','_self','i-ep-link',1,'admin_web','sso_phase8_application_center',NOW(),NOW(),20,NULL WHERE @menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `pid`=@menu_id AND `href`='domains');
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @menu_id,@sso_id,'console',CONVERT(X'E58D95E782B9E799BBE5BD95' USING utf8mb4),'sso','component=applications/sso/index&name=SsoConfiguration&type=C&permission=console/identity.ssoconfiguration:config','_self','i-ep-setting',1,'admin_web','sso_phase8_application_center',NOW(),NOW(),30,NULL WHERE @menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `pid`=@menu_id AND `href`='sso');
UPDATE `fun_admin_menu` SET `name`=CONVERT(X'4F4175746820E5AEA2E688B7E7ABAF' USING utf8mb4),`query`='component=applications/oauth/index&name=OAuthClientManagement&type=C&permission=console/identity.oauthclient:index',`permission_id`=@oauth_client_id WHERE `pid`=@menu_id AND `href`='oauth-client';
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @menu_id,@scope_id,'console',CONVERT(X'53636F706520E4B88E20436C61696D' USING utf8mb4),'scopes','component=applications/identity-management&name=ScopeClaimManagement&type=C&permission=console/identity.scopeclaim:index','_self','i-ep-collection',1,'admin_web','sso_phase8_application_center',NOW(),NOW(),50,NULL WHERE @menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `pid`=@menu_id AND `href`='scopes');
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @menu_id,@identity_user_id,'console',CONVERT(X'E8BAABE4BBBDE794A8E688B7' USING utf8mb4),'users','component=applications/identity-management&name=IdentityUsers&type=C&permission=console/identity.identityuser:index','_self','i-ep-user',1,'admin_web','sso_phase8_application_center',NOW(),NOW(),60,NULL WHERE @menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `pid`=@menu_id AND `href`='users');
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @menu_id,@session_id,'console',CONVERT(X'E4BC9AE8AF9DE4B88EE68E88E69D83' USING utf8mb4),'sessions','component=applications/identity-management&name=IdentitySessions&type=C&permission=console/identity.oidcsession:index','_self','i-ep-connection',1,'admin_web','sso_phase8_application_center',NOW(),NOW(),70,NULL WHERE @menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `pid`=@menu_id AND `href`='sessions');
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @menu_id,@signing_key_id,'console',CONVERT(X'E7ADBEE5908DE5AF86E992A5' USING utf8mb4),'signing-keys','component=applications/identity-management&name=SigningKeys&type=C&permission=console/identity.oidcsigningkey:index','_self','i-ep-key',1,'admin_web','sso_phase8_application_center',NOW(),NOW(),80,NULL WHERE @menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `pid`=@menu_id AND `href`='signing-keys');
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @menu_id,@audit_id,'console',CONVERT(X'E799BBE5BD95E5AEA1E8AEA1' USING utf8mb4),'audit','component=applications/identity-management&name=IdentityAudit&type=C&permission=console/identity.identityaudit:index','_self','i-ep-document',1,'admin_web','sso_phase8_application_center',NOW(),NOW(),90,NULL WHERE @menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `pid`=@menu_id AND `href`='audit');
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @menu_id,@portal_id,'console',CONVERT(X'E5BA94E794A8E997A8E688B7' USING utf8mb4),'portal','component=applications/portal&name=ApplicationPortal&type=C&permission=console/identity.enterpriseapplication:portal','_self','i-ep-grid',1,'admin_web','sso_phase8_application_center',NOW(),NOW(),100,NULL WHERE @menu_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `pid`=@menu_id AND `href`='portal');
