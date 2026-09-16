-- 111 企业 SSO Phase 7 OIDC Session 与单点退出；只向前、可重复执行。
SET @schema_name=DATABASE();
SET @sql=IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_client' AND COLUMN_NAME='backchannel_logout_uri'),'DO 0','ALTER TABLE `fun_oauth_client` ADD COLUMN `backchannel_logout_uri` varchar(2048) NULL AFTER `allowed_claims`'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `fun_oidc_session` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NOT NULL,
  `sid` varchar(128) NOT NULL, `session_token_hash` char(64) NOT NULL, `auth_time` datetime NOT NULL, `last_seen_at` datetime NOT NULL, `expires_at` datetime NOT NULL,
  `password_version` bigint unsigned NOT NULL, `session_version` bigint unsigned NOT NULL, `status` enum('active','ended') NOT NULL DEFAULT 'active', `ended_at` datetime NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oidc_session_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_oidc_session_sid` (`sid`), UNIQUE KEY `uk_oidc_session_token_hash` (`session_token_hash`),
  KEY `idx_oidc_session_user_active` (`tenant_id`,`user_id`,`status`,`expires_at`),
  CONSTRAINT `fk_oidc_session_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_oidc_session_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `fun_identity_user` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OIDC OP 登录会话';

CREATE TABLE IF NOT EXISTS `fun_oidc_client_session` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `oidc_session_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL,
  `sid` varchar(128) NOT NULL, `status` enum('active','ended') NOT NULL DEFAULT 'active', `last_authorized_at` datetime NOT NULL, `ended_at` datetime NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oidc_client_session_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_oidc_client_session_sid` (`sid`),
  UNIQUE KEY `uk_oidc_client_session_binding` (`tenant_id`,`oidc_session_id`,`client_id`), KEY `idx_oidc_client_session_user` (`tenant_id`,`user_id`,`status`),
  CONSTRAINT `fk_oidc_client_session_op` FOREIGN KEY (`tenant_id`,`oidc_session_id`) REFERENCES `fun_oidc_session` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oidc_client_session_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `fun_identity_user` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oidc_client_session_client` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OIDC RP 客户端会话';

CREATE TABLE IF NOT EXISTS `fun_backchannel_logout_delivery` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `oidc_session_id` bigint unsigned NOT NULL, `client_session_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL,
  `jti` char(36) NOT NULL, `logout_uri` varchar(2048) NOT NULL, `logout_token` text NOT NULL, `payload_hash` char(64) NOT NULL,
  `status` enum('pending','processing','delivered','dead') NOT NULL DEFAULT 'pending', `attempts` smallint unsigned NOT NULL DEFAULT 0, `next_attempt_at` datetime NOT NULL,
  `locked_at` datetime NULL, `delivered_at` datetime NULL, `dead_at` datetime NULL, `last_error_code` varchar(64) NULL, `response_status` smallint unsigned NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_backchannel_logout_delivery_jti` (`jti`), UNIQUE KEY `uk_backchannel_logout_delivery_session` (`client_session_id`),
  KEY `idx_backchannel_logout_delivery_queue` (`status`,`next_attempt_at`,`attempts`,`id`), KEY `idx_backchannel_logout_delivery_client` (`tenant_id`,`client_id`,`status`),
  CONSTRAINT `fk_backchannel_logout_delivery_op` FOREIGN KEY (`tenant_id`,`oidc_session_id`) REFERENCES `fun_oidc_session` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_backchannel_logout_delivery_client_session` FOREIGN KEY (`tenant_id`,`client_session_id`) REFERENCES `fun_oidc_client_session` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_backchannel_logout_delivery_client` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OIDC Back-Channel Logout 投递队列';

CREATE TABLE IF NOT EXISTS `fun_identity_audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NULL, `client_id` bigint unsigned NULL,
  `event_type` varchar(64) NOT NULL, `outcome` enum('success','failure') NOT NULL, `subject_hash` char(64) NULL, `ip_hash` char(64) NULL, `context` json NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), KEY `idx_identity_audit_tenant_event` (`tenant_id`,`event_type`,`created_at`), KEY `idx_identity_audit_user` (`tenant_id`,`user_id`,`created_at`), KEY `idx_identity_audit_client` (`tenant_id`,`client_id`,`created_at`),
  CONSTRAINT `fk_identity_audit_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_audit_user` FOREIGN KEY (`tenant_id`,`user_id`) REFERENCES `fun_identity_user` (`tenant_id`,`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_audit_client` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Identity 协议脱敏审计日志';

SET @application_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='enterprise_application_center' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@application_group_id,'console','console/identity.oidcsession:index','console/identity.oidcsession','index','OIDC 会话列表','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),130,NULL),
(@application_group_id,'console','console/identity.oidcsession:revoke','console/identity.oidcsession','revoke','撤销 OIDC 会话','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),131,NULL),
(@application_group_id,'console','console/identity.oidcsession:deliveries','console/identity.oidcsession','deliveries','退出投递列表','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),132,NULL),
(@application_group_id,'console','console/identity.oidcsession:retrydelivery','console/identity.oidcsession','retrydelivery','重试退出投递','route',1,0,'admin_web','enterprise_application_center',NOW(),NOW(),133,NULL);
