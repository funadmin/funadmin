-- 阶段一：完整插件生命周期字段、注册表、显式状态与 manifest 快照。
-- 仅向前追加，不恢复或改写工作树中已删除的 001-005 migration 文件。

SET @schema_name = DATABASE();
SET @table_name = CONCAT('fun_', 'plugin');

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'config'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `config` longtext NULL COMMENT ''插件配置'' AFTER `is_hook`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'db_version'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `db_version` varchar(190) NOT NULL DEFAULT '''' COMMENT ''插件数据库版本'' AFTER `version`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'migration_pending'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `migration_pending` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''是否待迁移'' AFTER `db_version`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'last_error'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `last_error` text NULL COMMENT ''最近生命周期错误'' AFTER `migration_pending`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'installed_at'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `installed_at` int unsigned NULL COMMENT ''安装时间'' AFTER `last_error`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'manifest'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `manifest` json DEFAULT NULL COMMENT ''plugin.json 契约快照'' AFTER `website`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'lifecycle_state'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `lifecycle_state` varchar(20) NOT NULL DEFAULT ''discovered'' COMMENT ''显式生命周期状态'' AFTER `status`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'state_changed_at'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `state_changed_at` int DEFAULT NULL COMMENT ''状态最后变更时间'' AFTER `lifecycle_state`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'operation_token'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `operation_token` varchar(64) DEFAULT NULL COMMENT ''生命周期操作令牌'' AFTER `state_changed_at`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'uk_plugin_name'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD UNIQUE KEY `uk_plugin_name` (`name`)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'idx_plugin_lifecycle_state'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD KEY `idx_plugin_lifecycle_state` (`lifecycle_state`)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = CONCAT('UPDATE `', @table_name, '` SET `lifecycle_state` = CASE WHEN `delete_time` IS NOT NULL AND `delete_time` > 0 THEN ''discovered'' WHEN `status` = 1 THEN ''enabled'' ELSE ''disabled'' END, `state_changed_at` = COALESCE(`update_time`, `create_time`, UNIX_TIMESTAMP())');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
