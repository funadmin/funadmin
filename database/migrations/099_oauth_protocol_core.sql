-- 099 企业 SSO Phase 4 OAuth/OIDC 协议核心；只向前、可重复执行。
CREATE TABLE IF NOT EXISTS `fun_oauth_authorization` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NULL,
  `transaction_hash` char(64) NOT NULL, `redirect_uri` varchar(2048) NOT NULL, `redirect_uri_hash` char(64) NOT NULL,
  `state` varchar(2048) NULL, `nonce` varchar(255) NULL, `code_challenge` varchar(128) NOT NULL, `code_challenge_method` varchar(16) NOT NULL DEFAULT 'S256',
  `subject_type` enum('user') NOT NULL DEFAULT 'user', `status` enum('pending','approved','denied','completed','expired') NOT NULL DEFAULT 'pending',
  `auth_time` datetime NULL, `session_id` varchar(128) NULL, `expires_at` datetime NOT NULL, `decided_at` datetime NULL, `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oauth_authorization_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_oauth_authorization_transaction` (`transaction_hash`),
  KEY `idx_oauth_authorization_client_status` (`tenant_id`,`client_id`,`status`,`expires_at`), KEY `idx_oauth_authorization_user` (`tenant_id`,`user_id`),
  CONSTRAINT `fk_oauth_authorization_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_authorization_client_tenant` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_authorization_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `ck_oauth_authorization_pkce` CHECK (`code_challenge_method`='S256')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 授权事务';

CREATE TABLE IF NOT EXISTS `fun_oauth_authorization_scope` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `authorization_id` bigint unsigned NOT NULL, `scope_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oauth_authorization_scope` (`tenant_id`,`authorization_id`,`scope_id`), KEY `idx_oauth_authorization_scope_scope` (`tenant_id`,`scope_id`),
  CONSTRAINT `fk_oauth_authorization_scope_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_authorization_scope_authorization` FOREIGN KEY (`tenant_id`,`authorization_id`) REFERENCES `fun_oauth_authorization` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_authorization_scope_scope` FOREIGN KEY (`tenant_id`,`scope_id`) REFERENCES `fun_scope` (`tenant_id`,`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth 授权 scope';

CREATE TABLE IF NOT EXISTS `fun_oauth_authorization_code` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `authorization_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NOT NULL,
  `code_prefix` varchar(16) NOT NULL, `code_hash` char(64) NOT NULL, `redirect_uri_hash` char(64) NOT NULL, `code_challenge` varchar(128) NOT NULL,
  `nonce` varchar(255) NULL, `subject_type` enum('user') NOT NULL DEFAULT 'user', `expires_at` datetime NOT NULL, `consumed_at` datetime NULL, `revoked_at` datetime NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oauth_authorization_code_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_oauth_authorization_code_hash` (`code_hash`),
  KEY `idx_oauth_authorization_code_consume` (`tenant_id`,`client_id`,`code_prefix`,`consumed_at`,`revoked_at`,`expires_at`), KEY `idx_oauth_authorization_code_user` (`tenant_id`,`user_id`),
  CONSTRAINT `fk_oauth_authorization_code_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_authorization_code_authorization` FOREIGN KEY (`tenant_id`,`authorization_id`) REFERENCES `fun_oauth_authorization` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_authorization_code_client` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_authorization_code_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth authorization code 哈希';

CREATE TABLE IF NOT EXISTS `fun_oauth_token` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `client_id` bigint unsigned NOT NULL, `user_id` bigint unsigned NULL,
  `token_type` enum('access','refresh') NOT NULL, `token_prefix` varchar(16) NOT NULL, `token_hash` char(64) NOT NULL,
  `family_id` char(36) NULL, `parent_id` bigint unsigned NULL, `generation` int unsigned NOT NULL DEFAULT 0, `subject_type` enum('user','client') NOT NULL,
  `authorization_id` bigint unsigned NULL, `session_id` varchar(128) NULL, `expires_at` datetime NOT NULL, `consumed_at` datetime NULL, `revoked_at` datetime NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oauth_token_tenant_id` (`tenant_id`,`id`), UNIQUE KEY `uk_oauth_token_hash` (`token_hash`),
  KEY `idx_oauth_token_lookup` (`token_prefix`,`token_type`,`revoked_at`,`expires_at`), KEY `idx_oauth_token_client` (`tenant_id`,`client_id`,`token_type`,`revoked_at`),
  KEY `idx_oauth_token_user` (`tenant_id`,`user_id`,`revoked_at`), KEY `idx_oauth_token_family` (`tenant_id`,`family_id`,`generation`,`revoked_at`), KEY `idx_oauth_token_parent` (`parent_id`),
  CONSTRAINT `fk_oauth_token_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_token_client` FOREIGN KEY (`tenant_id`,`client_id`) REFERENCES `fun_oauth_client` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_token_user` FOREIGN KEY (`user_id`) REFERENCES `fun_identity_user` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `fk_oauth_token_authorization` FOREIGN KEY (`tenant_id`,`authorization_id`) REFERENCES `fun_oauth_authorization` (`tenant_id`,`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_token_parent` FOREIGN KEY (`parent_id`) REFERENCES `fun_oauth_token` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ck_oauth_token_subject` CHECK ((`subject_type`='user' AND `user_id` IS NOT NULL) OR (`subject_type`='client' AND `user_id` IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth opaque token 哈希与 rotation family';

CREATE TABLE IF NOT EXISTS `fun_oauth_token_scope` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `tenant_id` bigint unsigned NOT NULL, `token_id` bigint unsigned NOT NULL, `scope_id` bigint unsigned NOT NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_oauth_token_scope` (`tenant_id`,`token_id`,`scope_id`), KEY `idx_oauth_token_scope_scope` (`tenant_id`,`scope_id`),
  CONSTRAINT `fk_oauth_token_scope_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `fun_identity_tenant` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_token_scope_token` FOREIGN KEY (`tenant_id`,`token_id`) REFERENCES `fun_oauth_token` (`tenant_id`,`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_oauth_token_scope_scope` FOREIGN KEY (`tenant_id`,`scope_id`) REFERENCES `fun_scope` (`tenant_id`,`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='OAuth token scope';
