-- 108 企业 SSO Phase 6 OIDC claims 与资源身份；只向前、可重复执行。
SET @schema_name=DATABASE();
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_token' AND COLUMN_NAME='password_version'),'DO 0','ALTER TABLE `fun_oauth_token` ADD COLUMN `password_version` bigint unsigned NULL AFTER `session_id`'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_token' AND COLUMN_NAME='session_version'),'DO 0','ALTER TABLE `fun_oauth_token` ADD COLUMN `session_version` bigint unsigned NULL AFTER `password_version`'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_token' AND INDEX_NAME='idx_oauth_token_bearer'),'DO 0','ALTER TABLE `fun_oauth_token` ADD INDEX `idx_oauth_token_bearer` (`token_hash`,`token_type`,`revoked_at`,`expires_at`,`tenant_id`,`client_id`)'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_client' AND COLUMN_NAME='subject_type'),'DO 0','ALTER TABLE `fun_oauth_client` ADD COLUMN `subject_type` enum(''public'',''pairwise'') NOT NULL DEFAULT ''public'' AFTER `require_pkce`'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_client' AND COLUMN_NAME='sector_identifier'),'DO 0','ALTER TABLE `fun_oauth_client` ADD COLUMN `sector_identifier` varchar(253) NULL AFTER `subject_type`'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_client' AND COLUMN_NAME='allowed_claims'),'DO 0','ALTER TABLE `fun_oauth_client` ADD COLUMN `allowed_claims` json NULL AFTER `sector_identifier`'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_enterprise_application' AND COLUMN_NAME='claim_policy'),'DO 0','ALTER TABLE `fun_enterprise_application` ADD COLUMN `claim_policy` json NULL AFTER `oauth_config`'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_identity_user' AND INDEX_NAME='uk_identity_user_tenant_id'),'DO 0','ALTER TABLE `fun_identity_user` ADD UNIQUE KEY `uk_identity_user_tenant_id` (`tenant_id`,`id`)'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
INSERT IGNORE INTO `fun_scope` (`tenant_id`,`name`,`description`,`is_builtin`,`status`,`created_at`,`updated_at`) VALUES
(1,'organization','读取组织声明',1,1,NOW(),NOW()),
(1,'roles','读取当前应用角色声明',1,1,NOW(),NOW()),
(1,'permissions','读取当前应用权限声明',1,1,NOW(),NOW()),
(1,'identity.read','读取资源身份上下文',0,1,NOW(),NOW());

SET @application_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@application_group_id,'console','console/identity.enterpriseapplication:saveclaimpolicy','console/identity.enterpriseapplication','saveclaimpolicy','保存 Claim 策略','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),121,NULL),
(@application_group_id,'console','console/identity.enterpriseapplication:saveclientclaimpolicy','console/identity.enterpriseapplication','saveclientclaimpolicy','保存 Client Claim 策略','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),122,NULL);

CREATE TABLE IF NOT EXISTS `fun_application_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL,
  `code` varchar(100) NOT NULL, `name` varchar(100) NOT NULL, `status` tinyint NOT NULL DEFAULT 1, `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_application_role_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_application_role_tenant_app_id` (`tenant_id`,`application_id`,`id`), UNIQUE KEY `uk_application_role_code` (`tenant_id`,`application_id`,`code`),
  KEY `idx_application_role_lookup` (`tenant_id`,`application_id`,`status`),
  CONSTRAINT `fk_application_role_app` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应用专属角色';

CREATE TABLE IF NOT EXISTS `fun_application_user_role` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NOT NULL, `role_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_application_user_role` (`tenant_id`,`application_id`,`user_id`,`role_id`), KEY `idx_application_user_role_claim` (`tenant_id`,`application_id`,`user_id`),
  CONSTRAINT `fk_application_user_role_app` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_user_role_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `fun_identity_user` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_user_role_role` FOREIGN KEY (`tenant_id`,`application_id`,`role_id`) REFERENCES `fun_application_role` (`tenant_id`,`application_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应用用户角色';

CREATE TABLE IF NOT EXISTS `fun_application_permission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL,
  `code` varchar(190) NOT NULL, `name` varchar(100) NOT NULL, `status` tinyint NOT NULL DEFAULT 1, `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_application_permission_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_application_permission_tenant_app_id` (`tenant_id`,`application_id`,`id`), UNIQUE KEY `uk_application_permission_code` (`tenant_id`,`application_id`,`code`),
  KEY `idx_application_permission_lookup` (`tenant_id`,`application_id`,`status`),
  CONSTRAINT `fk_application_permission_app` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应用专属权限';

CREATE TABLE IF NOT EXISTS `fun_application_role_permission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL, `role_id` bigint unsigned NOT NULL, `permission_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_application_role_permission` (`tenant_id`,`application_id`,`role_id`,`permission_id`), KEY `idx_application_role_permission_role` (`tenant_id`,`role_id`),
  CONSTRAINT `fk_application_role_permission_app` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_role_permission_role` FOREIGN KEY (`tenant_id`,`application_id`,`role_id`) REFERENCES `fun_application_role` (`tenant_id`,`application_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_role_permission_permission` FOREIGN KEY (`tenant_id`,`application_id`,`permission_id`) REFERENCES `fun_application_permission` (`tenant_id`,`application_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应用角色权限';

CREATE TABLE IF NOT EXISTS `fun_application_user_permission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NOT NULL, `permission_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_application_user_permission` (`tenant_id`,`application_id`,`user_id`,`permission_id`), KEY `idx_application_user_permission_claim` (`tenant_id`,`application_id`,`user_id`),
  CONSTRAINT `fk_application_user_permission_app` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_user_permission_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `fun_identity_user` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_application_user_permission_permission` FOREIGN KEY (`tenant_id`,`application_id`,`permission_id`) REFERENCES `fun_application_permission` (`tenant_id`,`application_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='应用用户直授权限';
