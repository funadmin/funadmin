-- 097 企业 SSO Phase 3 OAuth/OIDC client foundation；只向前、可重复执行。
CREATE TABLE IF NOT EXISTS `fun_oauth_client` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `application_id` bigint unsigned NOT NULL,
  `client_id` varchar(128) NOT NULL, `name` varchar(100) NOT NULL, `client_type` enum('public','confidential','machine') NOT NULL,
  `token_endpoint_auth_method` enum('none','client_secret_basic') NOT NULL, `require_pkce` tinyint NOT NULL DEFAULT 0,
  `status` enum('active','disabled') NOT NULL DEFAULT 'active', `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oauth_client_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_oauth_client_client_id` (`client_id`),
  UNIQUE KEY `uk_oauth_client_application_name` (`tenant_id`,`application_id`,`name`), KEY `idx_oauth_client_application` (`tenant_id`,`application_id`,`status`),
  CONSTRAINT `fk_oauth_client_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_client_application_tenant` FOREIGN KEY (`tenant_id`,`application_id`) REFERENCES `fun_enterprise_application` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `ck_oauth_client_policy` CHECK ((`client_type`='public' AND `token_endpoint_auth_method`='none' AND `require_pkce`=1) OR (`client_type` IN ('confidential','machine') AND `token_endpoint_auth_method`='client_secret_basic'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 客户端';

CREATE TABLE IF NOT EXISTS `fun_client_secret` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL,
  `secret_prefix` varchar(16) NOT NULL, `secret_hash` varchar(255) NOT NULL, `expires_at` datetime NULL, `revoked_at` datetime NULL,
  `last_used_at` datetime NULL, `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_client_secret_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_client_secret_prefix` (`client_id`,`secret_prefix`),
  KEY `idx_client_secret_active` (`tenant_id`,`client_id`,`revoked_at`,`expires_at`),
  CONSTRAINT `fk_client_secret_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_client_secret_client_tenant` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 客户端密钥哈希';

CREATE TABLE IF NOT EXISTS `fun_redirect_uri` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL,
  `uri_type` enum('authorization_callback','post_logout') NOT NULL DEFAULT 'authorization_callback', `redirect_uri` varchar(2048) NOT NULL,
  `uri_hash` char(64) NOT NULL, `status` tinyint NOT NULL DEFAULT 1, `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_redirect_uri_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_redirect_uri_exact` (`tenant_id`,`client_id`,`uri_type`,`uri_hash`),
  KEY `idx_redirect_uri_client` (`tenant_id`,`client_id`,`status`),
  CONSTRAINT `fk_redirect_uri_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_redirect_uri_client_tenant` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 精确重定向 URI';

CREATE TABLE IF NOT EXISTS `fun_scope` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL DEFAULT 1, `name` varchar(128) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '', `is_builtin` tinyint NOT NULL DEFAULT 0, `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_scope_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_scope_name` (`tenant_id`,`name`), KEY `idx_scope_status` (`tenant_id`,`status`),
  CONSTRAINT `fk_scope_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='独立 OAuth/OIDC scope 注册表';

CREATE TABLE IF NOT EXISTS `fun_client_scope` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL, `scope_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_client_scope` (`tenant_id`,`client_id`,`scope_id`), KEY `idx_client_scope_scope` (`tenant_id`,`scope_id`),
  CONSTRAINT `fk_client_scope_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_client_scope_client_tenant` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_client_scope_scope_tenant` FOREIGN KEY (`tenant_id`,`scope_id`) REFERENCES `fun_scope` (`tenant_id`,`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 客户端 scope';

CREATE TABLE IF NOT EXISTS `fun_client_grant` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL,
  `grant_type` enum('authorization_code','refresh_token','client_credentials') NOT NULL, `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_client_grant` (`tenant_id`,`client_id`,`grant_type`), KEY `idx_client_grant_client` (`tenant_id`,`client_id`),
  CONSTRAINT `fk_client_grant_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_client_grant_client_tenant` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 客户端 grant';

CREATE TABLE IF NOT EXISTS `fun_oidc_signing_key` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `kid` varchar(128) NOT NULL, `algorithm` varchar(16) NOT NULL DEFAULT 'RS256',
  `status` enum('pending','active','retiring','retired') NOT NULL DEFAULT 'pending', `private_key_ref` varchar(512) NOT NULL, `public_jwk` json NOT NULL,
  `activated_at` datetime NULL, `retired_at` datetime NULL, `publish_until` datetime NULL, `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oidc_signing_key_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_oidc_signing_key_kid` (`tenant_id`,`kid`),
  KEY `idx_oidc_signing_key_publish` (`tenant_id`,`status`,`publish_until`),
  CONSTRAINT `fk_oidc_signing_key_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_oidc_signing_key_algorithm` CHECK (`algorithm`='RS256')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OIDC RS256 签名密钥元数据';

INSERT IGNORE INTO `fun_scope` (`tenant_id`,`name`,`description`,`is_builtin`,`status`,`created_at`,`updated_at`) VALUES
(1,'openid','请求 OpenID Connect 身份令牌',1,1,NOW(),NOW()),
(1,'profile','读取基础个人资料声明',1,1,NOW(),NOW()),
(1,'email','读取邮箱声明',1,1,NOW(),NOW()),
(1,'phone','读取手机号声明',1,1,NOW(),NOW()),
(1,'offline_access','允许签发 refresh token',1,1,NOW(),NOW());
