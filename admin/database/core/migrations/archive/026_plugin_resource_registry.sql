-- 插件发布资源归属注册表，只向前创建。
CREATE TABLE IF NOT EXISTS `fun_plugin_resource` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin` varchar(100) NOT NULL,
  `version` varchar(64) NOT NULL,
  `source_path` varchar(500) NOT NULL,
  `target_path` varchar(500) NOT NULL,
  `sha256` char(64) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_resource_target` (`target_path`),
  KEY `idx_plugin_resource_owner` (`plugin`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='插件发布资源归属';
