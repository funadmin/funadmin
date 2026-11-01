-- 095 企业 SSO Phase 1 identity foundation；只向前、可重复执行。
CREATE TABLE IF NOT EXISTS `fun_identity_tenant` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(36) NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identity_tenant_public_id` (`public_id`),
  UNIQUE KEY `uk_identity_tenant_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='身份租户';

INSERT IGNORE INTO `fun_identity_tenant` (`id`,`public_id`,`code`,`name`,`status`,`created_at`,`updated_at`)
VALUES (1,'00000000-0000-4000-8000-000000000001','default','默认租户',1,NOW(),NOW());

CREATE TABLE IF NOT EXISTS `fun_identity_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `public_id` char(36) NOT NULL,
  `realm` varchar(32) NOT NULL,
  `source_id` bigint unsigned NOT NULL,
  `username` varchar(100) NOT NULL,
  `display_name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(190) NULL,
  `mobile` varchar(32) NULL,
  `avatar` varchar(255) NULL,
  `locale` varchar(16) NOT NULL DEFAULT 'zh-CN',
  `status` tinyint NOT NULL DEFAULT 1,
  `password_version` bigint unsigned NOT NULL DEFAULT 1,
  `session_version` bigint unsigned NOT NULL DEFAULT 1,
  `last_login_at` datetime NULL,
  `metadata` json NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identity_user_public_id` (`tenant_id`,`public_id`),
  UNIQUE KEY `uk_identity_user_source` (`tenant_id`,`realm`,`source_id`),
  UNIQUE KEY `uk_identity_user_username` (`tenant_id`,`realm`,`username`),
  UNIQUE KEY `uk_identity_user_email` (`tenant_id`,`realm`,`email`),
  UNIQUE KEY `uk_identity_user_mobile` (`tenant_id`,`realm`,`mobile`),
  CONSTRAINT `fk_identity_user_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统一身份用户';

CREATE TABLE IF NOT EXISTS `fun_identity_credential` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(32) NOT NULL DEFAULT 'password',
  `secret_hash` varchar(255) NOT NULL,
  `status` tinyint NOT NULL DEFAULT 1,
  `last_verified_at` datetime NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identity_credential_user_type` (`tenant_id`,`user_id`,`type`),
  KEY `idx_identity_credential_user` (`user_id`),
  CONSTRAINT `fk_identity_credential_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_credential_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='身份凭证';

CREATE TABLE IF NOT EXISTS `fun_identity_admin_link` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `admin_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identity_admin_link_source` (`tenant_id`,`admin_id`),
  UNIQUE KEY `uk_identity_admin_link_user` (`tenant_id`,`user_id`),
  KEY `idx_identity_admin_link_admin` (`admin_id`),
  CONSTRAINT `fk_identity_admin_link_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_admin_link_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_admin_link_admin` FOREIGN KEY (`admin_id`) REFERENCES `fun_admin` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员身份关联';

CREATE TABLE IF NOT EXISTS `fun_identity_member_link` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identity_member_link_source` (`tenant_id`,`member_id`),
  UNIQUE KEY `uk_identity_member_link_user` (`tenant_id`,`user_id`),
  KEY `idx_identity_member_link_member` (`member_id`),
  CONSTRAINT `fk_identity_member_link_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_member_link_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_member_link_member` FOREIGN KEY (`member_id`) REFERENCES `fun_member` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员身份关联';

CREATE TABLE IF NOT EXISTS `fun_identity_user_department` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `department_id` bigint unsigned NOT NULL,
  `is_primary` tinyint NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identity_user_department` (`tenant_id`,`user_id`,`department_id`),
  KEY `idx_identity_user_department_department` (`department_id`),
  CONSTRAINT `fk_identity_user_department_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_user_department_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_identity_user_department_department` FOREIGN KEY (`department_id`) REFERENCES `fun_department` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='身份任职部门';

CREATE TABLE IF NOT EXISTS `fun_identity_migration_audit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `conflict_type` varchar(32) NOT NULL,
  `identifier_hash` char(64) NOT NULL,
  `admin_id` bigint unsigned NULL,
  `member_id` bigint unsigned NULL,
  `details` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_identity_migration_conflict` (`tenant_id`,`conflict_type`,`identifier_hash`,`admin_id`,`member_id`),
  KEY `idx_identity_migration_audit_admin` (`admin_id`),
  KEY `idx_identity_migration_audit_member` (`member_id`),
  CONSTRAINT `fk_identity_migration_audit_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='身份迁移冲突审计';

INSERT IGNORE INTO `fun_identity_user` (`tenant_id`,`public_id`,`realm`,`source_id`,`username`,`display_name`,`email`,`mobile`,`avatar`,`status`,`created_at`,`updated_at`)
SELECT 1,UUID(),'admin',a.`id`,a.`username`,COALESCE(NULLIF(a.`real_name`,''),a.`username`),NULLIF(TRIM(a.`email`),''),NULLIF(TRIM(a.`mobile`),''),NULLIF(TRIM(a.`avatar`),''),a.`status`,NOW(),NOW()
FROM `fun_admin` a LEFT JOIN `fun_identity_admin_link` l ON l.`tenant_id`=1 AND l.`admin_id`=a.`id` WHERE l.`id` IS NULL;
INSERT IGNORE INTO `fun_identity_admin_link` (`tenant_id`,`user_id`,`admin_id`,`created_at`,`updated_at`)
SELECT 1,u.`id`,a.`id`,NOW(),NOW() FROM `fun_admin` a JOIN `fun_identity_user` u ON u.`tenant_id`=1 AND u.`realm`='admin' AND u.`source_id`=a.`id`
LEFT JOIN `fun_identity_admin_link` l ON l.`tenant_id`=1 AND l.`admin_id`=a.`id` WHERE l.`id` IS NULL;

INSERT IGNORE INTO `fun_identity_user` (`tenant_id`,`public_id`,`realm`,`source_id`,`username`,`display_name`,`email`,`mobile`,`avatar`,`status`,`last_login_at`,`created_at`,`updated_at`)
SELECT 1,UUID(),'member',m.`id`,m.`username`,COALESCE(NULLIF(m.`nickname`,''),m.`username`),NULLIF(TRIM(m.`email`),''),NULLIF(TRIM(m.`mobile`),''),NULLIF(TRIM(m.`avatar`),''),m.`status`,IF(m.`last_login`>0,FROM_UNIXTIME(m.`last_login`),NULL),NOW(),NOW()
FROM `fun_member` m LEFT JOIN `fun_identity_member_link` l ON l.`tenant_id`=1 AND l.`member_id`=m.`id` WHERE l.`id` IS NULL;
INSERT IGNORE INTO `fun_identity_member_link` (`tenant_id`,`user_id`,`member_id`,`created_at`,`updated_at`)
SELECT 1,u.`id`,m.`id`,NOW(),NOW() FROM `fun_member` m JOIN `fun_identity_user` u ON u.`tenant_id`=1 AND u.`realm`='member' AND u.`source_id`=m.`id`
LEFT JOIN `fun_identity_member_link` l ON l.`tenant_id`=1 AND l.`member_id`=m.`id` WHERE l.`id` IS NULL;

INSERT IGNORE INTO `fun_identity_credential` (`tenant_id`,`user_id`,`type`,`secret_hash`,`status`,`created_at`,`updated_at`)
SELECT l.`tenant_id`,l.`user_id`,'password',a.`password`,a.`status`,NOW(),NOW() FROM `fun_identity_admin_link` l JOIN `fun_admin` a ON a.`id`=l.`admin_id` WHERE a.`password` REGEXP '^\\$(2[aby]\\$|argon2(id|i)\\$)';
INSERT IGNORE INTO `fun_identity_credential` (`tenant_id`,`user_id`,`type`,`secret_hash`,`status`,`created_at`,`updated_at`)
SELECT l.`tenant_id`,l.`user_id`,'password',m.`password`,m.`status`,NOW(),NOW() FROM `fun_identity_member_link` l JOIN `fun_member` m ON m.`id`=l.`member_id` WHERE m.`password` REGEXP '^\\$(2[aby]\\$|argon2(id|i)\\$)';

INSERT IGNORE INTO `fun_identity_user_department` (`tenant_id`,`user_id`,`department_id`,`is_primary`,`created_at`,`updated_at`)
SELECT l.`tenant_id`,l.`user_id`,ad.`dept_id`,IF(ad.`dept_id`=a.`dept_id`,1,0),NOW(),NOW() FROM `fun_identity_admin_link` l JOIN `fun_admin` a ON a.`id`=l.`admin_id` JOIN `fun_admin_department` ad ON ad.`admin_id`=a.`id` WHERE ad.`dept_id`>0;

INSERT IGNORE INTO `fun_identity_migration_audit` (`tenant_id`,`conflict_type`,`identifier_hash`,`admin_id`,`member_id`,`details`,`created_at`,`updated_at`)
SELECT 1,'username',SHA2(LOWER(TRIM(a.`username`)),256),a.`id`,m.`id`,'admin/member identifier kept as separate identities',NOW(),NOW() FROM `fun_admin` a JOIN `fun_member` m ON LOWER(TRIM(a.`username`))=LOWER(TRIM(m.`username`)) AND TRIM(a.`username`)<>'';
INSERT IGNORE INTO `fun_identity_migration_audit` (`tenant_id`,`conflict_type`,`identifier_hash`,`admin_id`,`member_id`,`details`,`created_at`,`updated_at`)
SELECT 1,'email',SHA2(LOWER(TRIM(a.`email`)),256),a.`id`,m.`id`,'admin/member identifier kept as separate identities',NOW(),NOW() FROM `fun_admin` a JOIN `fun_member` m ON LOWER(TRIM(a.`email`))=LOWER(TRIM(m.`email`)) AND TRIM(COALESCE(a.`email`,''))<>'';
INSERT IGNORE INTO `fun_identity_migration_audit` (`tenant_id`,`conflict_type`,`identifier_hash`,`admin_id`,`member_id`,`details`,`created_at`,`updated_at`)
SELECT 1,'mobile',SHA2(TRIM(a.`mobile`),256),a.`id`,m.`id`,'admin/member identifier kept as separate identities',NOW(),NOW() FROM `fun_admin` a JOIN `fun_member` m ON TRIM(a.`mobile`)=TRIM(m.`mobile`) AND TRIM(COALESCE(a.`mobile`,''))<>'';
