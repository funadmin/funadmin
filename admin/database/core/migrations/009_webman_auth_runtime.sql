-- Webman 原生认证补充：核心业务结构仍由 001_baseline 提供，不注入原型菜单。
CREATE TABLE IF NOT EXISTS `fun_login_attempts` (
  `key` varchar(64) NOT NULL,
  `attempts` int unsigned NOT NULL,
  `window_start` bigint unsigned NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fun_browser_sessions` (
  `session_hash` varchar(64) NOT NULL,
  `csrf_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`session_hash`),
  KEY `idx_browser_sessions_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fun_admin_auth_tokens` (
  `token_hash` varchar(64) NOT NULL,
  `admin_id` bigint unsigned NOT NULL,
  `purpose` varchar(16) NOT NULL,
  `credential_version` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`token_hash`),
  KEY `idx_admin_auth_tokens_admin` (`admin_id`),
  KEY `idx_admin_auth_tokens_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
