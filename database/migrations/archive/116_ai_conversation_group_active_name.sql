-- 116 仅约束活动分组名称；保留软删除历史，支持同名重建。可重入。
SET @schema_name = DATABASE();
SET @sql = IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_ai_conversation_group' AND COLUMN_NAME='active_name'), 'ALTER TABLE `fun_ai_conversation_group` ADD COLUMN `active_name` varchar(100) GENERATED ALWAYS AS (CASE WHEN `deleted_at` IS NULL THEN `name` ELSE NULL END) STORED', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
-- 先建立新唯一约束，避免迁移中断时丢失活动名称唯一性。
SET @sql = IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_ai_conversation_group' AND INDEX_NAME='uk_ai_conversation_group_active_name'), 'ALTER TABLE `fun_ai_conversation_group` ADD UNIQUE KEY `uk_ai_conversation_group_active_name` (`admin_id`,`active_name`)', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_ai_conversation_group' AND INDEX_NAME='uk_ai_conversation_group_owner_name'), 'ALTER TABLE `fun_ai_conversation_group` DROP INDEX `uk_ai_conversation_group_owner_name`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
