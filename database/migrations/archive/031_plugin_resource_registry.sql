-- 插件公开资源注册表收敛：兼容 026 已创建的表并使用正式契约字段。
CREATE TABLE IF NOT EXISTS `fun_plugin_resource` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plugin_name` varchar(100) NOT NULL,
  `version` varchar(64) NOT NULL,
  `source_path` varchar(500) NOT NULL,
  `target_path` varchar(500) NOT NULL,
  `sha256` char(64) NOT NULL,
  `create_time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plugin_resource_target_path` (`target_path`),
  KEY `idx_plugin_resource_owner` (`plugin_name`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='插件公开资源归属';

SET @schema_name = DATABASE();
SET @table_name = 'fun_plugin_resource';

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='plugin')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='plugin_name'),
  'ALTER TABLE `fun_plugin_resource` CHANGE COLUMN `plugin` `plugin_name` varchar(100) NOT NULL',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='created_at')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='create_time'),
  'ALTER TABLE `fun_plugin_resource` CHANGE COLUMN `created_at` `create_time` datetime NOT NULL',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND INDEX_NAME='uk_plugin_resource_target_path'),
  'ALTER TABLE `fun_plugin_resource` ADD UNIQUE KEY `uk_plugin_resource_target_path` (`target_path`)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

