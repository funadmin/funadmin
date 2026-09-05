-- 插件生命周期能力扩展：仅新增字段，兼容已经执行的早期插件迁移。
SET @schema_name = DATABASE();
SET @plugin_table = CONCAT('fun_', 'plugin');
SET @operation_table = CONCAT('fun_', 'plugin_operation');
SET @version_table = CONCAT('fun_', 'plugin_version_history');

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='package_hash'), 'SELECT 1', CONCAT('ALTER TABLE `', @plugin_table, '` ADD COLUMN `package_hash` char(64) DEFAULT NULL COMMENT ''当前包 SHA-256'' AFTER `version`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='code_version'), 'SELECT 1', CONCAT('ALTER TABLE `', @plugin_table, '` ADD COLUMN `code_version` varchar(64) NOT NULL DEFAULT '''' COMMENT ''已部署代码版本'' AFTER `package_hash`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='source'), 'SELECT 1', CONCAT('ALTER TABLE `', @plugin_table, '` ADD COLUMN `source` varchar(20) NOT NULL DEFAULT ''local'' COMMENT ''包来源'' AFTER `code_version`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='error_stage'), 'SELECT 1', CONCAT('ALTER TABLE `', @plugin_table, '` ADD COLUMN `error_stage` varchar(32) DEFAULT NULL COMMENT ''失败阶段'' AFTER `last_error`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='recovery_path'), 'SELECT 1', CONCAT('ALTER TABLE `', @plugin_table, '` ADD COLUMN `recovery_path` varchar(500) DEFAULT NULL COMMENT ''人工恢复路径'' AFTER `error_stage`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='needs_reinstall'), 'SELECT 1', CONCAT('ALTER TABLE `', @plugin_table, '` ADD COLUMN `needs_reinstall` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''必须重新安装'' AFTER `recovery_path`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='stage'), 'SELECT 1', CONCAT('ALTER TABLE `', @operation_table, '` ADD COLUMN `stage` varchar(32) NOT NULL DEFAULT ''complete'' COMMENT ''生命周期阶段'' AFTER `operation`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='progress'), 'SELECT 1', CONCAT('ALTER TABLE `', @operation_table, '` ADD COLUMN `progress` tinyint unsigned NOT NULL DEFAULT 0 COMMENT ''阶段进度'' AFTER `stage`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='recovery_path'), 'SELECT 1', CONCAT('ALTER TABLE `', @operation_table, '` ADD COLUMN `recovery_path` varchar(500) DEFAULT NULL COMMENT ''人工恢复路径'' AFTER `error_message`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@version_table AND COLUMN_NAME='max_db_version'), 'SELECT 1', CONCAT('ALTER TABLE `', @version_table, '` ADD COLUMN `max_db_version` varchar(190) NOT NULL DEFAULT '''' COMMENT ''此代码已知最高数据库版本'' AFTER `version`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
