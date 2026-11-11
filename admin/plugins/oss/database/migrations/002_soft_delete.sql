-- 002 oss plugin soft delete (1.1.0), same rules as core 005_soft_delete:
-- oss_profile gets `deleted_at` DATETIME NULL with an index led by it. Its primary key stays the 16-hex string `id`
-- (generated from random bytes, referenced by oss_setting.current_profile_id and by attachment driver names
-- oss_<id>). The table has no unique key besides the primary key, so it gets no `deleted_token`.
-- Exempt: oss_setting (single settings row, never deleted).
-- Every statement is guarded by information_schema and can be re-run; one ALTER, executed with PREPARE.

-- fun_oss_profile
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_oss_profile' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_oss_profile' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_oss_profile_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_oss_profile` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
