CREATE TABLE IF NOT EXISTS `market_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `sort` int NOT NULL DEFAULT 0,
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_category_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场分类';

CREATE TABLE IF NOT EXISTS `market_plugin` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(1000) NOT NULL DEFAULT '',
  `author` varchar(100) NOT NULL DEFAULT '',
  `category_id` int unsigned NOT NULL DEFAULT 0,
  `license_type` varchar(16) NOT NULL DEFAULT 'free' COMMENT 'free 免费；grant 需授权',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1 上架；0 下架',
  `sort` int NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_plugin_code` (`code`),
  KEY `idx_market_plugin_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场插件';

CREATE TABLE IF NOT EXISTS `market_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` int unsigned NOT NULL,
  `code_version` varchar(64) NOT NULL,
  `changelog` text NULL,
  `requires` text NULL COMMENT 'plugin.json requires 快照',
  `sha256` char(64) NOT NULL,
  `size` bigint unsigned NOT NULL,
  `tree_hash` char(64) NOT NULL,
  `database_capability` varchar(100) NOT NULL DEFAULT '',
  `app_enabled` tinyint NOT NULL DEFAULT 0,
  `admin_enabled` tinyint NOT NULL DEFAULT 0,
  `signature` varchar(128) NOT NULL,
  `package_path` varchar(255) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'draft' COMMENT 'draft 草稿；published 已发布；withdrawn 已撤回',
  `download_count` int unsigned NOT NULL DEFAULT 0,
  `published_at` datetime NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_version` (`plugin_id`, `code_version`),
  KEY `idx_market_version_status` (`plugin_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场版本';

CREATE TABLE IF NOT EXISTS `market_grant` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` int unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  `expires_at` datetime NULL COMMENT '为空表示永久',
  `status` tinyint NOT NULL DEFAULT 1,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_grant` (`plugin_id`, `member_id`),
  KEY `idx_market_grant_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场授权';

CREATE TABLE IF NOT EXISTS `market_token` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `member_id` int unsigned NOT NULL,
  `access_hash` char(64) NOT NULL,
  `refresh_hash` char(64) NOT NULL,
  `access_expires_at` int unsigned NOT NULL,
  `refresh_expires_at` int unsigned NOT NULL,
  `revoked_at` datetime NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_market_token_access` (`access_hash`),
  UNIQUE KEY `uk_market_token_refresh` (`refresh_hash`),
  KEY `idx_market_token_member` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场访问令牌';

CREATE TABLE IF NOT EXISTS `market_download` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `version_id` bigint unsigned NOT NULL,
  `member_id` int unsigned NOT NULL,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `created_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_market_download_version` (`version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='插件市场下载记录';
