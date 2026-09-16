-- 104 企业 SSO Phase 5 独立登录、授权确认与重放防护；只向前、可重复执行。
CREATE TABLE IF NOT EXISTS `fun_identity_login_attempt` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NULL,
  `identifier_hash` char(64) NOT NULL, `ip_hash` char(64) NOT NULL, `succeeded` tinyint NOT NULL DEFAULT 0,
  `failure_reason` varchar(32) NULL, `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), KEY `idx_identity_login_attempt_limit` (`tenant_id`,`identifier_hash`,`ip_hash`,`created_at`),
  CONSTRAINT `fk_identity_login_attempt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_login_attempt_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Identity 登录安全审计';

CREATE TABLE IF NOT EXISTS `fun_identity_consent` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL,
  `granted_scope_hash` char(64) NOT NULL, `scope_names` text NOT NULL, `granted_at` datetime NOT NULL, `revoked_at` datetime NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_identity_consent_grant` (`tenant_id`,`user_id`,`client_id`,`granted_scope_hash`),
  KEY `idx_identity_consent_active` (`tenant_id`,`user_id`,`client_id`,`revoked_at`),
  CONSTRAINT `fk_identity_consent_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_consent_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_consent_client` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Identity 用户授权确认';

SET @schema_name=DATABASE();
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_authorization' AND COLUMN_NAME='csrf_token_hash'),'ALTER TABLE `fun_oauth_authorization` ADD COLUMN `csrf_token_hash` char(64) NULL AFTER `transaction_hash`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_oauth_authorization' AND COLUMN_NAME='interaction_consumed_at'),'ALTER TABLE `fun_oauth_authorization` ADD COLUMN `interaction_consumed_at` datetime NULL AFTER `decided_at`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- brand_config 已由 096 在 fun_enterprise_application 建立，登录页只读取并严格转义。
