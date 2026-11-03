CREATE TABLE IF NOT EXISTS `fun_market_category` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `name` varchar(50) NOT NULL, `sort` int NOT NULL DEFAULT 0, `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_market_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_plugin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `code` varchar(64) NOT NULL, `name` varchar(100) NOT NULL, `description` varchar(1000) NOT NULL DEFAULT '', `author` varchar(100) NOT NULL DEFAULT '',
  `category_id` bigint unsigned NOT NULL DEFAULT 0, `license_type` varchar(16) NOT NULL DEFAULT 'free', `price_perpetual` int NOT NULL DEFAULT 0, `price_yearly` int NOT NULL DEFAULT 0,
  `cover` varchar(500) NOT NULL DEFAULT '', `homepage` varchar(500) NOT NULL DEFAULT '', `status` tinyint NOT NULL DEFAULT 1, `sort` int NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_market_plugin_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `plugin_id` bigint unsigned NOT NULL, `code_version` varchar(64) NOT NULL, `changelog` text NOT NULL, `requires` text NOT NULL,
  `sha256` char(64) NOT NULL, `size` bigint NOT NULL, `tree_hash` char(64) NOT NULL, `database_capability` varchar(190) NOT NULL DEFAULT '',
  `app_enabled` tinyint NOT NULL DEFAULT 0, `admin_enabled` tinyint NOT NULL DEFAULT 0, `signature` varchar(128) NOT NULL, `package_path` varchar(512) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'draft', `download_count` bigint NOT NULL DEFAULT 0, `published_at` datetime DEFAULT NULL, `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_market_version` (`plugin_id`,`code_version`), KEY `idx_market_version_status` (`plugin_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_grant` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `plugin_id` bigint unsigned NOT NULL, `member_id` bigint unsigned NOT NULL, `expires_at` datetime DEFAULT NULL, `status` tinyint NOT NULL DEFAULT 1,
  `source` varchar(16) NOT NULL DEFAULT 'admin', `plan` varchar(16) NOT NULL DEFAULT '', `order_id` bigint unsigned DEFAULT NULL, `remark` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_market_grant_member` (`plugin_id`,`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_token` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `member_id` bigint unsigned NOT NULL, `access_hash` char(64) NOT NULL, `refresh_hash` char(64) NOT NULL,
  `access_expires_at` bigint NOT NULL, `refresh_expires_at` bigint NOT NULL, `ip` varchar(45) NOT NULL DEFAULT '', `created_at` datetime NOT NULL, `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_market_access` (`access_hash`), UNIQUE KEY `uk_market_refresh` (`refresh_hash`), KEY `idx_market_token_member` (`member_id`,`revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `order_no` varchar(32) NOT NULL, `member_id` bigint unsigned NOT NULL, `plugin_id` bigint unsigned NOT NULL,
  `plan` varchar(16) NOT NULL, `amount` int NOT NULL, `paid_amount` int NOT NULL DEFAULT 0, `subject` varchar(120) NOT NULL, `status` varchar(16) NOT NULL DEFAULT 'pending', `channel` varchar(16) NOT NULL,
  `expire_at` datetime NOT NULL, `trade_no` varchar(64) DEFAULT NULL, `paid_at` datetime DEFAULT NULL, `grant_id` bigint unsigned DEFAULT NULL, `remark` varchar(255) NOT NULL DEFAULT '', `client_ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL, `updated_at` datetime DEFAULT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_market_order_no` (`order_no`), KEY `idx_market_order_member` (`member_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_payment_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `order_no` varchar(32) NOT NULL DEFAULT '', `channel` varchar(16) NOT NULL, `event` varchar(32) NOT NULL, `verified` tinyint NOT NULL DEFAULT 0,
  `result` varchar(255) NOT NULL, `payload` text DEFAULT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `idx_market_payment_order` (`order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_site` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` char(36) NOT NULL, `member_id` bigint unsigned NOT NULL DEFAULT 0, `platform_version` varchar(32) NOT NULL DEFAULT '', `php_version` varchar(32) NOT NULL DEFAULT '',
  `ip` varchar(45) NOT NULL DEFAULT '', `first_seen_at` datetime NOT NULL, `last_seen_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_market_site` (`site_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_site_plugin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` char(36) NOT NULL, `plugin_id` bigint unsigned NOT NULL, `state` varchar(16) NOT NULL, `version` varchar(64) NOT NULL DEFAULT '',
  `installed_at` datetime NOT NULL, `updated_at` datetime NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_market_site_plugin` (`site_id`,`plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_install_event` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `site_id` char(36) NOT NULL, `plugin_id` bigint unsigned NOT NULL, `event` varchar(16) NOT NULL, `version` varchar(64) NOT NULL DEFAULT '', `from_version` varchar(64) NOT NULL DEFAULT '',
  `member_id` bigint unsigned NOT NULL DEFAULT 0, `occurred_at` datetime NOT NULL, `created_at` datetime NOT NULL, PRIMARY KEY (`id`), KEY `idx_market_event_time` (`plugin_id`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `fun_market_download` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `version_id` bigint unsigned NOT NULL, `member_id` bigint unsigned NOT NULL, `ip` varchar(45) NOT NULL DEFAULT '', `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_market_download_time` (`version_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
