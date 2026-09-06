-- 插件标识与显示名称硬切：code 为标识，name 为显示名称；source_name 是通用来源字段，不改名。
-- 仅向前、可重复执行，支持全旧、部分迁移及全新三种状态。
SET @schema_name = DATABASE();

SET @table_name = 'fun_plugin';
SET @has_code = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='code');
SET @has_name = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='name');
SET @has_title = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='title');
SET @sql = IF(@has_code = 0 AND @has_name = 1, 'ALTER TABLE `fun_plugin` CHANGE COLUMN `name` `code` varchar(100) NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_name = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='name');
SET @sql = IF(@has_name = 0 AND @has_title = 1, 'ALTER TABLE `fun_plugin` CHANGE COLUMN `title` `name` varchar(250) NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_code = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='code');
SET @has_name = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='name');
SET @has_title = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='title');
SET @sql = IF(@has_code = 1 AND @has_name = 1 AND @has_title = 1, 'UPDATE `fun_plugin` SET `code`=`name` WHERE (`code` IS NULL OR `code`='''') AND `name` IS NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_title = 1 AND @has_name = 1, 'UPDATE `fun_plugin` SET `name`=`title` WHERE `title` IS NOT NULL AND `title`<>''''', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_title = 1 AND @has_name = 1, 'ALTER TABLE `fun_plugin` DROP COLUMN `title`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_old_index = EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_plugin' AND INDEX_NAME='uk_plugin_name');
SET @has_new_index = EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_plugin' AND INDEX_NAME='uk_plugin_code');
SET @sql = IF(@has_old_index = 1 AND @has_new_index = 0, 'ALTER TABLE `fun_plugin` DROP INDEX `uk_plugin_name`, ADD UNIQUE KEY `uk_plugin_code` (`code`)', IF(@has_old_index = 1, 'ALTER TABLE `fun_plugin` DROP INDEX `uk_plugin_name`', 'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_new_index = EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_plugin' AND INDEX_NAME='uk_plugin_code');
SET @sql = IF(@has_new_index = 0, 'ALTER TABLE `fun_plugin` ADD UNIQUE KEY `uk_plugin_code` (`code`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name = 'fun_plugin_operation';
SET @has_plugin_name = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='plugin_name');
SET @has_plugin_code = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='plugin_code');
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 0, 'ALTER TABLE `fun_plugin_operation` CHANGE COLUMN `plugin_name` `plugin_code` varchar(100) NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 1, 'UPDATE `fun_plugin_operation` SET `plugin_code`=`plugin_name` WHERE (`plugin_code` IS NULL OR `plugin_code`='''') AND `plugin_name` IS NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 1, 'ALTER TABLE `fun_plugin_operation` DROP COLUMN `plugin_name`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name = 'fun_plugin_version_history';
SET @has_plugin_name = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='plugin_name');
SET @has_plugin_code = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='plugin_code');
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 0, 'ALTER TABLE `fun_plugin_version_history` CHANGE COLUMN `plugin_name` `plugin_code` varchar(100) NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 1, 'UPDATE `fun_plugin_version_history` SET `plugin_code`=`plugin_name` WHERE (`plugin_code` IS NULL OR `plugin_code`='''') AND `plugin_name` IS NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 1, 'ALTER TABLE `fun_plugin_version_history` DROP COLUMN `plugin_name`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @table_name = 'fun_plugin_resource';
SET @has_plugin_name = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='plugin_name');
SET @has_plugin_code = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='plugin_code');
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 0, 'ALTER TABLE `fun_plugin_resource` CHANGE COLUMN `plugin_name` `plugin_code` varchar(100) NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 1, 'UPDATE `fun_plugin_resource` SET `plugin_code`=`plugin_name` WHERE (`plugin_code` IS NULL OR `plugin_code`='''') AND `plugin_name` IS NOT NULL', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@has_plugin_name = 1 AND @has_plugin_code = 1, 'ALTER TABLE `fun_plugin_resource` DROP COLUMN `plugin_name`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
