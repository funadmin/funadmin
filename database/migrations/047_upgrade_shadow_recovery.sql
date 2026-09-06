-- M6 shadow 数据库恢复能力标记；仅向前新增且保持幂等。
SET @schema_name = DATABASE();
SET @upgrade_task = CONCAT((SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME LIKE '%upgrade_task' ORDER BY (TABLE_NAME='fun_upgrade_task') DESC LIMIT 1));
SET @shadow_column_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@upgrade_task AND COLUMN_NAME='db_backup_schema'
);
SET @shadow_sql = IF(
  @shadow_column_exists=0,
  CONCAT('ALTER TABLE `',@upgrade_task,'` ADD COLUMN `db_backup_schema` varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `db_backup_hash`'),
  'SELECT 1'
);
PREPARE shadow_stmt FROM @shadow_sql;
EXECUTE shadow_stmt;
DEALLOCATE PREPARE shadow_stmt;
