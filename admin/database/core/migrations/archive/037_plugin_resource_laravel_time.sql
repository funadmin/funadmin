-- 插件资源注册表时间字段收敛；兼容 031 将 created_at 改回 create_time 的历史状态。
SET @schema_name = DATABASE();
SET @table_name = 'fun_plugin_resource';

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='create_time')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='created_at'),
  'ALTER TABLE `fun_plugin_resource` CHANGE COLUMN `create_time` `created_at` datetime NOT NULL',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='create_time')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@table_name AND COLUMN_NAME='created_at'),
  'UPDATE `fun_plugin_resource` SET `created_at`=COALESCE(`created_at`,`create_time`)',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- M7 仅完成回填与运行时切换；legacy 列由 M8 的专用 maintenance 流程删除。
