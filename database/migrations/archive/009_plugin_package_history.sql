-- 阶段二：插件包版本与生命周期操作历史，只向前新增。
-- MigrationService 会将 fun_ 替换为实际表前缀。

CREATE TABLE IF NOT EXISTS `fun_plugin_version_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(100) NOT NULL,
  `version` varchar(64) NOT NULL,
  `source` varchar(20) NOT NULL COMMENT 'local/cloud',
  `package_hash` char(64) NOT NULL COMMENT '实际插件包 SHA-256',
  `signature_algorithm` varchar(32) DEFAULT NULL,
  `signature_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `create_time` int unsigned NOT NULL,
  `update_time` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_version_name_time` (`plugin_name`, `create_time`),
  KEY `idx_plugin_version_hash` (`package_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='插件包版本历史';

CREATE TABLE IF NOT EXISTS `fun_plugin_operation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(100) NOT NULL,
  `operation` varchar(20) NOT NULL COMMENT 'install/update',
  `from_version` varchar(64) NOT NULL DEFAULT '',
  `to_version` varchar(64) NOT NULL DEFAULT '',
  `source` varchar(20) NOT NULL COMMENT 'local/cloud',
  `package_hash` char(64) NOT NULL,
  `result` varchar(20) NOT NULL COMMENT 'success/failed',
  `error_message` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `create_time` int unsigned NOT NULL,
  `update_time` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_plugin_operation_name_time` (`plugin_name`, `create_time`),
  KEY `idx_plugin_operation_result` (`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='插件生命周期操作历史';
