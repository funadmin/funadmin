-- 004 document plugin soft delete (1.5.0), same rules as core 005_soft_delete:
-- every non-exempt table gets `deleted_at` DATETIME NULL with an index led by it; tables with a unique key also get
-- `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0 (0 = live, the row id once deleted) and every unique key is rebuilt
-- under its own name as "original columns + deleted_token", so a deleted slug can be reused.
-- documents.deleted_at changes from TIMESTAMP to DATETIME (MODIFY rebuilds the table).
-- document_category_links had the composite primary key (document_id, category_id): it gets an auto-increment `id`
-- primary key (existing rows are numbered automatically) and the old key becomes uk_document_category_links_pk.
-- Already soft-deleted rows get deleted_token = id.
-- Exempt (append-only history, never deleted): document_revisions, document_events.
-- Every statement is guarded by information_schema and can be re-run; one ALTER per table, executed with PREPARE.

-- fun_document_categories
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_categories' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_categories' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_document_categories_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_categories' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_categories' AND INDEX_NAME='document_categories_slug_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_categories' AND INDEX_NAME='document_categories_slug_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `document_categories_slug_unique`, ADD UNIQUE KEY `document_categories_slug_unique` (`slug`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_document_categories` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_document_categories` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_documents
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_documents' AND COLUMN_NAME='deleted_at' AND DATA_TYPE='timestamp')>0, 'MODIFY COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_documents' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_documents' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_documents_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_documents' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_documents' AND INDEX_NAME='documents_slug_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_documents' AND INDEX_NAME='documents_slug_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `documents_slug_unique`, ADD UNIQUE KEY `documents_slug_unique` (`slug`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_documents` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_documents` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_document_category_links
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_category_links' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_category_links' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_document_category_links_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_category_links' AND COLUMN_NAME='id')=0, 'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, DROP PRIMARY KEY, ADD PRIMARY KEY (`id`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_category_links' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_category_links' AND INDEX_NAME='uk_document_category_links_pk')=0, 'ADD UNIQUE KEY `uk_document_category_links_pk` (`document_id`,`category_id`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_document_category_links` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_document_category_links` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_document_seed_entries
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_seed_entries' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_seed_entries' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_document_seed_entries_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_seed_entries' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_seed_entries' AND INDEX_NAME='document_seed_entries_kind_slug_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_seed_entries' AND INDEX_NAME='document_seed_entries_kind_slug_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `document_seed_entries_kind_slug_unique`, ADD UNIQUE KEY `document_seed_entries_kind_slug_unique` (`kind`,`slug`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_document_seed_entries` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_document_seed_entries` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_document_feedback
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_feedback' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_feedback' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_document_feedback_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_feedback' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_feedback' AND INDEX_NAME='document_feedback_document_id_voter_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_document_feedback' AND INDEX_NAME='document_feedback_document_id_voter_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `document_feedback_document_id_voter_unique`, ADD UNIQUE KEY `document_feedback_document_id_voter_unique` (`document_id`,`voter`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_document_feedback` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_document_feedback` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;
