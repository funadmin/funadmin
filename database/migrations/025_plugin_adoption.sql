-- 安全收养旧 fun_addon：只迁移白名单元数据与 config，永不采信旧运行状态。
SET @schema_name = DATABASE();
SET @legacy_table_name = CONCAT('fun_', 'addon');
SET @plugin_table_name = CONCAT('fun_', 'plugin');
SET @legacy_exists = EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@legacy_table_name);
SET @plugin_exists = EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table_name);
SET @legacy_config_exists = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@legacy_table_name AND COLUMN_NAME='config');

SET @sql = IF(
  @legacy_exists AND @plugin_exists,
  CONCAT(
    'INSERT INTO `', @plugin_table_name, '` (`title`,`name`,`description`,`author`,`version`,`requires`,`website`,`config`,`status`,`lifecycle_state`,`needs_reinstall`,`create_time`,`update_time`) ',
    'SELECT LEFT(legacy.`title`,250),LEFT(legacy.`name`,100),legacy.`description`,LEFT(legacy.`author`,40),LEFT(legacy.`version`,20),LEFT(legacy.`requires`,50),LEFT(legacy.`website`,200),',
    IF(@legacy_config_exists, 'legacy.`config`', 'NULL'),
    ',0,''disabled'',1,legacy.`create_time`,legacy.`update_time` FROM `', @legacy_table_name, '` legacy ',
    'INNER JOIN (SELECT `name`,MIN(`id`) id FROM `', @legacy_table_name, '` WHERE `name` REGEXP ''^[a-z][a-z0-9]*$'' GROUP BY `name`) safe ON safe.id=legacy.id ',
    'WHERE NOT EXISTS (SELECT 1 FROM `', @plugin_table_name, '` current_record WHERE current_record.`name`=legacy.`name`)'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  @legacy_exists AND @plugin_exists,
  CONCAT('UPDATE `', @plugin_table_name, '` current_record INNER JOIN `', @legacy_table_name, '` legacy ON legacy.`name`=current_record.`name` SET current_record.`status` = 0,current_record.`lifecycle_state` = ''disabled'',current_record.`needs_reinstall` = 1,current_record.`config` = IF(current_record.`config` IS NULL,', IF(@legacy_config_exists, 'legacy.`config`', 'NULL'), ',current_record.`config`)'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
