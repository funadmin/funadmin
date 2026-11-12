-- 004 qa plugin member attachments (1.3.0):
-- New table qa_attachment: files a member uploaded with a question or an answer. `submission_id` is the CMS member
-- submission (question or answer) the file belongs to, `question_id` the question of the thread (equal to
-- `submission_id` for question files). Files are stored privately on disk through the CMS form file storage, under
-- runtime/plugins-data/qa/attachments; `path` is relative to that root.
-- Soft delete: `deleted_at` DATETIME NULL with an index led by it. No unique key besides the primary key, so no
-- `deleted_token`. Attachments are soft deleted and restored together with their question or answer; rows and files
-- are removed only by the recycle bin permanent delete.
-- CMS data is not touched. CREATE TABLE IF NOT EXISTS makes the file safe to re-run.

CREATE TABLE IF NOT EXISTS `fun_qa_attachment` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `site_id` BIGINT UNSIGNED NOT NULL,
  `question_id` BIGINT UNSIGNED NOT NULL,
  `submission_id` BIGINT UNSIGNED NOT NULL,
  `member_id` BIGINT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(100) NOT NULL,
  `size` BIGINT UNSIGNED NOT NULL,
  `sha256` CHAR(64) NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_qa_attachment_submission` (`site_id`, `submission_id`, `id`),
  KEY `idx_qa_attachment_question` (`question_id`),
  KEY `idx_qa_attachment_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
