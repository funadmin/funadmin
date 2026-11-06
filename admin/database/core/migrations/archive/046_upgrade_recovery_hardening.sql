-- M6 系统升级数据库备份、哈希校验与陈旧租约恢复能力。
SET @schema_name = DATABASE();
SET @upgrade_task = CONCAT((SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME LIKE '%upgrade_task' ORDER BY (TABLE_NAME='fun_upgrade_task') DESC LIMIT 1));

SET @sql = CONCAT('ALTER TABLE `',@upgrade_task,'` MODIFY COLUMN `status` varchar(32) NOT NULL DEFAULT ''pending''');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@upgrade_task AND COLUMN_NAME='backup_hash'), CONCAT('ALTER TABLE `',@upgrade_task,'` ADD COLUMN `backup_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `backup_path`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@upgrade_task AND COLUMN_NAME='db_backup_path'), CONCAT('ALTER TABLE `',@upgrade_task,'` ADD COLUMN `db_backup_path` varchar(500) DEFAULT NULL AFTER `backup_hash`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@upgrade_task AND COLUMN_NAME='db_backup_hash'), CONCAT('ALTER TABLE `',@upgrade_task,'` ADD COLUMN `db_backup_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `db_backup_path`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @upgrade_permission_id = (SELECT `id` FROM `fun_permission` WHERE `code`='system:upgrade:list' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @upgrade_permission_id,'console','console/systemupgrade:recoverstale','console/systemupgrade','recoverstale','恢复陈旧升级任务','route',1,0,60,'admin_web','system_upgrade',NOW(),NOW()
WHERE @upgrade_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemupgrade:recoverstale');
