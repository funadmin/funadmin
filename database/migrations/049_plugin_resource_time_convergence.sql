-- 插件资源表时间字段最终收口：统一为 Laravel 风格 datetime 三件套。
SET @schema_name = DATABASE();
SET @table_name = 'fun_plugin_resource';

-- 仅存在 legacy 字段时原位改名，保留现有数据与非空约束。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='create_time')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='created_at'),
  'ALTER TABLE `fun_plugin_resource` CHANGE COLUMN `create_time` `created_at` datetime NOT NULL',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 两列共存时先回填正式字段，再删除 legacy 字段。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='create_time')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='created_at'),
  'UPDATE `fun_plugin_resource` SET `created_at`=COALESCE(`created_at`,`create_time`)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='create_time'),
  'ALTER TABLE `fun_plugin_resource` DROP COLUMN `create_time`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 补齐标准更新时间与软删除字段，时间字段不设置数据库默认值。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='updated_at'),
  'DO 0',
  'ALTER TABLE `fun_plugin_resource` ADD COLUMN `updated_at` datetime NULL AFTER `created_at`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='deleted_at'),
  'DO 0',
  'ALTER TABLE `fun_plugin_resource` ADD COLUMN `deleted_at` datetime NULL AFTER `updated_at`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 026 与后续迁移曾使用两个名称创建同一 target_path 唯一索引；最终只保留正式索引。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND INDEX_NAME='uk_plugin_resource_target')
  AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND INDEX_NAME='uk_plugin_resource_target_path'),
  'ALTER TABLE `fun_plugin_resource` DROP INDEX `uk_plugin_resource_target`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
