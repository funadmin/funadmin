-- M6 系统升级签名 manifest、一次性消费与全局任务租约。
CREATE TABLE IF NOT EXISTS `fun_upgrade_manifest` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `manifest_id` char(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload` json NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_upgrade_manifest_id` (`manifest_id`),
  KEY `idx_upgrade_manifest_expiry` (`expires_at`,`consumed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='一次性可信升级 manifest';

SET @upgrade_task_manifest_id = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND COLUMN_NAME='manifest_id'
);
SET @upgrade_task_manifest_sql = IF(@upgrade_task_manifest_id=0,
  'ALTER TABLE `fun_upgrade_task` ADD COLUMN `manifest_id` char(48) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL AFTER `idempotency_token`',
  'SELECT 1');
PREPARE upgrade_task_manifest_stmt FROM @upgrade_task_manifest_sql;
EXECUTE upgrade_task_manifest_stmt;
DEALLOCATE PREPARE upgrade_task_manifest_stmt;

SET @upgrade_task_active_slot = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND COLUMN_NAME='active_slot'
);
SET @upgrade_task_active_sql = IF(@upgrade_task_active_slot=0,
  'ALTER TABLE `fun_upgrade_task` ADD COLUMN `active_slot` tinyint unsigned DEFAULT NULL AFTER `metadata`',
  'SELECT 1');
PREPARE upgrade_task_active_stmt FROM @upgrade_task_active_sql;
EXECUTE upgrade_task_active_stmt;
DEALLOCATE PREPARE upgrade_task_active_stmt;

SET @upgrade_task_lease = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND COLUMN_NAME='lease_expires_at'
);
SET @upgrade_task_lease_sql = IF(@upgrade_task_lease=0,
  'ALTER TABLE `fun_upgrade_task` ADD COLUMN `lease_expires_at` datetime DEFAULT NULL AFTER `active_slot`',
  'SELECT 1');
PREPARE upgrade_task_lease_stmt FROM @upgrade_task_lease_sql;
EXECUTE upgrade_task_lease_stmt;
DEALLOCATE PREPARE upgrade_task_lease_stmt;

SET @upgrade_task_active_index = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND INDEX_NAME='uk_upgrade_task_active'
);
SET @upgrade_task_active_index_sql = IF(@upgrade_task_active_index=0,
  'ALTER TABLE `fun_upgrade_task` ADD UNIQUE KEY `uk_upgrade_task_active` (`active_slot`)',
  'SELECT 1');
PREPARE upgrade_task_active_index_stmt FROM @upgrade_task_active_index_sql;
EXECUTE upgrade_task_active_index_stmt;
DEALLOCATE PREPARE upgrade_task_active_index_stmt;

SET @upgrade_task_manifest_index = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND INDEX_NAME='idx_upgrade_task_manifest'
);
SET @upgrade_task_manifest_index_sql = IF(@upgrade_task_manifest_index=0,
  'ALTER TABLE `fun_upgrade_task` ADD KEY `idx_upgrade_task_manifest` (`manifest_id`)',
  'SELECT 1');
PREPARE upgrade_task_manifest_index_stmt FROM @upgrade_task_manifest_index_sql;
EXECUTE upgrade_task_manifest_index_stmt;
DEALLOCATE PREPARE upgrade_task_manifest_index_stmt;
