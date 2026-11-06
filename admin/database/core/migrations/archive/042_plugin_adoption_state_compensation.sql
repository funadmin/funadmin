-- 025 adoption 状态补偿：manifest 是 legacy/modern 的唯一边界，旧运行状态一律不可信。
SET @schema_name=DATABASE();
SET @plugin_table='fun_plugin';
SET @operation_table='fun_plugin_operation';

SET @plugin_ready=(
  EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table)
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='manifest')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='needs_reinstall')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='status')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='lifecycle_state')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@plugin_table AND COLUMN_NAME='name')
);

-- 无 manifest 的记录始终按 legacy 保守处置：禁用并要求重装。
SET @sql=IF(@plugin_ready,'UPDATE `fun_plugin` SET `status`=0,`lifecycle_state`=''disabled'',`needs_reinstall`=1 WHERE `manifest` IS NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- manifest 非空只解除误置的重装标记，不据此盲目启用合法 disabled 插件。
SET @sql=IF(@plugin_ready,'UPDATE `fun_plugin` SET `needs_reinstall`=0 WHERE `manifest` IS NOT NULL','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @operation_ready=(
  EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table)
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='id')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='plugin_name')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='operation')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME=@operation_table AND COLUMN_NAME='result')
);

-- 保守恢复：仅最近成功 enable 之后不存在成功 disable 时，历史证据才足以恢复 enabled。
SET @sql=IF(
  @plugin_ready AND @operation_ready,
  'UPDATE `fun_plugin` p INNER JOIN `fun_plugin_operation` latest_enable ON latest_enable.`id`=(SELECT MAX(enable_history.`id`) FROM `fun_plugin_operation` enable_history WHERE enable_history.`plugin_name`=p.`name` AND enable_history.`operation`=''enable'' AND enable_history.`result`=''success'') LEFT JOIN `fun_plugin_operation` newer_disable ON newer_disable.`plugin_name`=p.`name` AND newer_disable.`operation`=''disable'' AND newer_disable.`result`=''success'' AND newer_disable.`id`>latest_enable.`id` SET p.`status`=1,p.`lifecycle_state`=''enabled'',p.`needs_reinstall`=0 WHERE p.`manifest` IS NOT NULL AND newer_disable.`id` IS NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
