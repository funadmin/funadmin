-- 已发布 migration 的前向补偿：收敛应用标识、菜单索引、插件图标与资源索引。
SET @schema_name = DATABASE();

-- Expand：先增加 nullable app_name，不触碰仍在使用的 module。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_permission' AND COLUMN_NAME='module')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_permission' AND COLUMN_NAME='app_name'),
  'ALTER TABLE `fun_permission` ADD COLUMN `app_name` varchar(50) NULL AFTER `module`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_admin_menu' AND COLUMN_NAME='module')
  AND NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_admin_menu' AND COLUMN_NAME='app_name'),
  'ALTER TABLE `fun_admin_menu` ADD COLUMN `app_name` varchar(50) NULL AFTER `module`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill：优先保留已有 app_name，其次复制 module，最后使用 console。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_permission' AND COLUMN_NAME='module')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_permission' AND COLUMN_NAME='app_name'),
  'UPDATE `fun_permission` SET `app_name`=COALESCE(NULLIF(`app_name`,''''),NULLIF(`module`,''''),''console'')',
  'UPDATE `fun_permission` SET `app_name`=''console'' WHERE `app_name` IS NULL OR `app_name`='''''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_admin_menu' AND COLUMN_NAME='module')
  AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_admin_menu' AND COLUMN_NAME='app_name'),
  'UPDATE `fun_admin_menu` SET `app_name`=COALESCE(NULLIF(`app_name`,''''),NULLIF(`module`,''''),''console'')',
  'UPDATE `fun_admin_menu` SET `app_name`=''console'' WHERE `app_name` IS NULL OR `app_name`='''''
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE `fun_permission`
  MODIFY COLUMN `app_name` varchar(50) NOT NULL DEFAULT 'console' COMMENT 'ThinkPHP应用标识';
ALTER TABLE `fun_admin_menu`
  MODIFY COLUMN `app_name` varchar(50) NOT NULL DEFAULT 'console' COMMENT 'ThinkPHP应用标识';

-- 索引切换必须位于 expand/backfill 之后、legacy contract 之前。
SET @menu_index_columns = (
  SELECT GROUP_CONCAT(`COLUMN_NAME` ORDER BY `SEQ_IN_INDEX` SEPARATOR ',')
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_admin_menu' AND INDEX_NAME='uk_menu_location'
);
SET @sql = IF(
  @menu_index_columns IS NOT NULL AND @menu_index_columns <> 'app_name,href,query',
  'ALTER TABLE `fun_admin_menu` DROP INDEX `uk_menu_location`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_admin_menu' AND INDEX_NAME='uk_menu_location'),
  'DO 0',
  'ALTER TABLE `fun_admin_menu` ADD UNIQUE KEY `uk_menu_location` (`app_name`,`href`,`query`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Contract：代码与索引已切换后再删除 module。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_permission' AND COLUMN_NAME='module'),
  'ALTER TABLE `fun_permission` DROP COLUMN `module`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_admin_menu' AND COLUMN_NAME='module'),
  'ALTER TABLE `fun_admin_menu` DROP COLUMN `module`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 承接曾误追加到已执行 migration 的非破坏性补偿。
UPDATE `fun_admin_menu`
SET `icon`='i-ep-grid', `updated_at`=NOW()
WHERE `source_type`='admin_web' AND `source_name`='plugin_center'
  AND (COALESCE(NULLIF(TRIM(`icon`),''),'')='' OR `icon` IN ('i-ep-circle-close','i-ep-medal'));

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_plugin_resource' AND INDEX_NAME='uk_plugin_resource_target')
  AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_plugin_resource' AND INDEX_NAME='uk_plugin_resource_target_path'),
  'ALTER TABLE `fun_plugin_resource` DROP INDEX `uk_plugin_resource_target`',
  'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
