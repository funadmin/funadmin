-- 003 qa plugin question admin (1.2.0):
-- 1. qa_question gets `pinned` TINYINT(1) NOT NULL DEFAULT 0 and index `idx_qa_question_pinned` (site_id, locale, pinned, id).
-- 2. New table qa_answer: the QA-side state of an answer (hidden by an administrator, soft deleted). Its primary key
--    `id` reuses the CMS member submission id of the answer (not auto-increment). It has `deleted_at` DATETIME NULL with
--    an index led by it and no unique key besides the primary key, so it gets no `deleted_token`.
-- 3. Existing answers get a state row, once: only when qa_answer did not exist before this run (checked before the
--    CREATE and kept in @qa_answer_created) and the CMS submission table exists. Re-running 003 never backfills, so
--    answer rows an administrator permanently deleted (even all of them) never come back.
-- CMS data is only read, never changed. Every statement is guarded by information_schema and can be re-run.

-- fun_qa_question
SET @qa_admin_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_qa_question' AND COLUMN_NAME='pinned')=0, 'ADD COLUMN `pinned` TINYINT(1) NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_qa_question' AND INDEX_NAME='idx_qa_question_pinned')=0, 'ADD INDEX `idx_qa_question_pinned` (`site_id`, `locale`, `pinned`, `id`)', NULL)
);
SET @qa_admin_ddl = IF(@qa_admin_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_qa_question` ', @qa_admin_ddl));
PREPARE qa_admin_stmt FROM @qa_admin_ddl;
EXECUTE qa_admin_stmt;
DEALLOCATE PREPARE qa_admin_stmt;

-- fun_qa_answer
SET @qa_answer_created = (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_qa_answer')=0;
CREATE TABLE IF NOT EXISTS `fun_qa_answer` (
  `id` BIGINT UNSIGNED NOT NULL,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `site_id` BIGINT UNSIGNED NOT NULL,
  `locale` VARCHAR(20) NOT NULL,
  `hidden` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qa_answer_question` (`question_id`, `id`),
  KEY `idx_qa_answer_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- backfill answer state rows for existing CMS answers whose question has a QA row
SET @qa_admin_fill = IF(
  (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_member_submission')=1
    AND @qa_answer_created = 1,
  'INSERT INTO `fun_qa_answer` (`id`, `question_id`, `site_id`, `locale`, `hidden`, `created_at`, `updated_at`) SELECT s.`id`, s.`parent_id`, s.`site_id`, s.`locale`, 0, s.`created_at`, s.`updated_at` FROM `fun_cms_member_submission` s INNER JOIN `fun_qa_question` q ON q.`id` = s.`parent_id` AND q.`site_id` = s.`site_id` AND q.`locale` = s.`locale` WHERE s.`kind` = ''answer''',
  'DO 0'
);
PREPARE qa_admin_stmt FROM @qa_admin_fill;
EXECUTE qa_admin_stmt;
DEALLOCATE PREPARE qa_admin_stmt;
