-- 插件生命周期 v2：幂等补齐能力字段，并安全补偿收养旧 addon。
SET @schema_name = DATABASE();
SET @plugin_table = CONCAT('fun_', 'plugin');
SET @operation_table = CONCAT('fun_', 'plugin_operation');
SET @version_table = CONCAT('fun_', 'plugin_version_history');
SET @addon_table = CONCAT('fun_', 'addon');

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='package_hash'), CONCAT('ALTER TABLE `',@plugin_table,'` ADD COLUMN `package_hash` char(64) DEFAULT NULL COMMENT ''当前包 SHA-256'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='code_version'), CONCAT('ALTER TABLE `',@plugin_table,'` ADD COLUMN `code_version` varchar(64) NOT NULL DEFAULT '''' COMMENT ''已部署代码版本'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='source'), CONCAT('ALTER TABLE `',@plugin_table,'` ADD COLUMN `source` varchar(20) NOT NULL DEFAULT ''local'' COMMENT ''包来源'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='error_stage'), CONCAT('ALTER TABLE `',@plugin_table,'` ADD COLUMN `error_stage` varchar(32) DEFAULT NULL COMMENT ''失败阶段'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='recovery_path'), CONCAT('ALTER TABLE `',@plugin_table,'` ADD COLUMN `recovery_path` varchar(500) DEFAULT NULL COMMENT ''人工恢复路径'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='needs_reinstall'), CONCAT('ALTER TABLE `',@plugin_table,'` ADD COLUMN `needs_reinstall` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''必须重新安装'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='stage'), CONCAT('ALTER TABLE `',@operation_table,'` ADD COLUMN `stage` varchar(32) NOT NULL DEFAULT ''complete'' COMMENT ''生命周期阶段'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='progress'), CONCAT('ALTER TABLE `',@operation_table,'` ADD COLUMN `progress` tinyint unsigned NOT NULL DEFAULT 0 COMMENT ''阶段进度'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='recovery_path'), CONCAT('ALTER TABLE `',@operation_table,'` ADD COLUMN `recovery_path` varchar(500) DEFAULT NULL COMMENT ''人工恢复路径'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='operation_token'), CONCAT('ALTER TABLE `',@operation_table,'` ADD COLUMN `operation_token` varchar(64) DEFAULT NULL COMMENT ''关联生命周期操作令牌'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@version_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@version_table AND COLUMN_NAME='max_db_version'), CONCAT('ALTER TABLE `',@version_table,'` ADD COLUMN `max_db_version` varchar(190) NOT NULL DEFAULT '''' COMMENT ''代码支持的最高数据库版本'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@version_table) AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@version_table AND COLUMN_NAME='package_path'), CONCAT('ALTER TABLE `',@version_table,'` ADD COLUMN `package_path` varchar(500) DEFAULT NULL COMMENT ''runtime 私有历史包路径'''), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @addon_exists = EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@addon_table);
SET @plugin_exists = EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table);
SET @addon_config_exists = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@addon_table AND COLUMN_NAME='config');
SET @sql = IF(@addon_exists AND @plugin_exists, CONCAT(
'INSERT INTO `',@plugin_table,'` (`title`,`name`,`description`,`author`,`version`,`requires`,`website`,`config`,`status`,`lifecycle_state`,`needs_reinstall`,`created_at`,`updated_at`) ',
'SELECT LEFT(a.`title`,250),LEFT(a.`name`,100),a.`description`,LEFT(a.`author`,40),LEFT(a.`version`,20),LEFT(a.`requires`,50),LEFT(a.`website`,200),',IF(@addon_config_exists,'a.`config`','NULL'),',0,''disabled'',1,FROM_UNIXTIME(NULLIF(a.`create_time`,0)),FROM_UNIXTIME(NULLIF(a.`update_time`,0)) FROM `',@addon_table,'` a ',
'INNER JOIN (SELECT `name`,MIN(`id`) AS id FROM `',@addon_table,'` WHERE `name` REGEXP ''^[a-z][a-z0-9]*$'' GROUP BY `name`) dedupe ON dedupe.id=a.id ',
'WHERE NOT EXISTS (SELECT 1 FROM `',@plugin_table,'` p WHERE p.`name`=a.`name`)'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(@plugin_exists, CONCAT('UPDATE `',@plugin_table,'` SET `status`=0,`lifecycle_state`=''disabled'',`needs_reinstall`=1,`is_hook`=0,`thumb`='''',`group`='''' WHERE `manifest` IS NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@plugin_exists, CONCAT('UPDATE `',@plugin_table,'` SET `needs_reinstall`=0 WHERE `manifest` IS NOT NULL'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
