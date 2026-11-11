-- 002 qa plugin soft delete (1.1.0), same rules as core 005_soft_delete:
-- qa_question gets `deleted_at` DATETIME NULL with an index led by it. The table has no unique key besides the
-- primary key, so it gets no `deleted_token`. The primary key `id` is unchanged: it reuses the CMS member
-- submission id (not auto-increment), is numeric and unique.
-- Every statement is guarded by information_schema and can be re-run; one ALTER, executed with PREPARE.

-- fun_qa_question
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_qa_question' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_qa_question' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_qa_question_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_qa_question` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
