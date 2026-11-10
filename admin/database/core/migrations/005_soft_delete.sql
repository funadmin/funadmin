-- 005 后台数据全部软删除：每张核心表补 deleted_at（DATETIME NULL，无以它开头的索引时补 idx_{表}_deleted_at）；
-- 主键为单列 id 的表补 deleted_token（BIGINT UNSIGNED NOT NULL DEFAULT 0；0 表示未删除，软删除时写入该行 id），
-- 并回填已软删除存量行 deleted_token = id；每个非主键唯一索引按原名重建为「原列 + deleted_token」，删除后可用相同唯一值重建。
-- 与 PHP 迁移 database/migrations/005_soft_delete.php 终态一致；全部语句可重复执行。
-- 特例：fun_member_level 的 UNIQUE KEY `id` 与主键重复，直接删除；fun_member 删除与唯一索引首列重复的普通索引 username / mobile / email。
-- 主键不是 id 的表（令牌、会话、登录尝试、关联表、系统密钥等）没有可写入的 id，只补 deleted_at；它们没有主键以外的唯一索引。
-- 写法：MySQL 不支持 ADD COLUMN IF NOT EXISTS，每张表按 information_schema 拼出仍缺的子句，合成一条 ALTER 后用 PREPARE 执行；
-- 已全部到位时执行 DO 0。重建唯一索引在同一条 ALTER 内先删后建，外键依赖的前导列始终有索引支撑。

-- fun_admin
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_admin_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin' AND INDEX_NAME='username' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin' AND INDEX_NAME='username' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `username`, ADD UNIQUE KEY `username` (`username`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_admin` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_admin` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_admin_auth_tokens
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_auth_tokens' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_auth_tokens' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_admin_auth_tokens_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_admin_auth_tokens` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_admin_department
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_department' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_department' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_admin_department_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_admin_department` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_admin_log
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_log' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_log' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_admin_log_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_log' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_admin_log` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_admin_log` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_admin_menu
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_menu' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_menu' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_admin_menu_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_menu' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_menu' AND INDEX_NAME='uk_menu_location' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_admin_menu' AND INDEX_NAME='uk_menu_location' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_menu_location`, ADD UNIQUE KEY `uk_menu_location` (`app_name`,`href`,`query`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_admin_menu` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_admin_menu` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_approval
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_approval' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_approval' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_approval_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_approval' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_approval' AND INDEX_NAME='uk_ai_approval_nonce' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_approval' AND INDEX_NAME='uk_ai_approval_nonce' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_approval_nonce`, ADD UNIQUE KEY `uk_ai_approval_nonce` (`nonce`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_approval' AND INDEX_NAME='uk_ai_approval_digest' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_approval' AND INDEX_NAME='uk_ai_approval_digest' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_approval_digest`, ADD UNIQUE KEY `uk_ai_approval_digest` (`conversation_id`,`digest`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_approval` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_approval` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_attachment
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_attachment' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_attachment' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_attachment_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_attachment' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_attachment' AND INDEX_NAME='uk_storage_path' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_attachment' AND INDEX_NAME='uk_storage_path' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_storage_path`, ADD UNIQUE KEY `uk_storage_path` (`storage_path`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_attachment` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_attachment` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_change_set
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_change_set_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND INDEX_NAME='uk_ai_change_set_idempotency' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND INDEX_NAME='uk_ai_change_set_idempotency' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_change_set_idempotency`, ADD UNIQUE KEY `uk_ai_change_set_idempotency` (`conversation_id`,`idempotency_key`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND INDEX_NAME='uk_ai_change_set_digest' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND INDEX_NAME='uk_ai_change_set_digest' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_change_set_digest`, ADD UNIQUE KEY `uk_ai_change_set_digest` (`conversation_id`,`digest`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND INDEX_NAME='uk_ai_change_set_transaction' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_change_set' AND INDEX_NAME='uk_ai_change_set_transaction' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_change_set_transaction`, ADD UNIQUE KEY `uk_ai_change_set_transaction` (`transaction_id`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_change_set` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_change_set` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_configuration_profile
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_configuration_profile' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_configuration_profile' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_configuration_profile_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_configuration_profile' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_configuration_profile' AND INDEX_NAME='uk_ai_profile_default' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_configuration_profile' AND INDEX_NAME='uk_ai_profile_default' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_profile_default`, ADD UNIQUE KEY `uk_ai_profile_default` (`default_admin_id`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_configuration_profile` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_configuration_profile` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_conversation
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_conversation_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation' AND INDEX_NAME='uk_ai_conversation_uuid' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation' AND INDEX_NAME='uk_ai_conversation_uuid' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_conversation_uuid`, ADD UNIQUE KEY `uk_ai_conversation_uuid` (`uuid`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_conversation` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_conversation` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_conversation_group
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation_group' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation_group' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_conversation_group_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation_group' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation_group' AND INDEX_NAME='uk_ai_conversation_group_active_name' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_conversation_group' AND INDEX_NAME='uk_ai_conversation_group_active_name' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_conversation_group_active_name`, ADD UNIQUE KEY `uk_ai_conversation_group_active_name` (`admin_id`,`active_name`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_conversation_group` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_conversation_group` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_message
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_message' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_message' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_message_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_message' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_message' AND INDEX_NAME='uk_ai_message_sequence' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_message' AND INDEX_NAME='uk_ai_message_sequence' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_message_sequence`, ADD UNIQUE KEY `uk_ai_message_sequence` (`conversation_id`,`sequence`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_message' AND INDEX_NAME='uk_ai_message_client_key' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_message' AND INDEX_NAME='uk_ai_message_client_key' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_message_client_key`, ADD UNIQUE KEY `uk_ai_message_client_key` (`conversation_id`,`idempotency_key`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_message` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_message` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_outbox
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_outbox' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_outbox' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_outbox_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_outbox' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_outbox' AND INDEX_NAME='uk_ai_outbox_idempotency' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_outbox' AND INDEX_NAME='uk_ai_outbox_idempotency' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_outbox_idempotency`, ADD UNIQUE KEY `uk_ai_outbox_idempotency` (`idempotency_key`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_outbox` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_outbox` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_stream_nonce
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_stream_nonce' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_stream_nonce' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_stream_nonce_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_stream_nonce' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_stream_nonce' AND INDEX_NAME='uk_ai_stream_nonce' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_stream_nonce' AND INDEX_NAME='uk_ai_stream_nonce' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_stream_nonce`, ADD UNIQUE KEY `uk_ai_stream_nonce` (`nonce_hash`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_stream_nonce` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_stream_nonce` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_task
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_task_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task' AND INDEX_NAME='uk_ai_task_idempotency' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task' AND INDEX_NAME='uk_ai_task_idempotency' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_task_idempotency`, ADD UNIQUE KEY `uk_ai_task_idempotency` (`conversation_id`,`idempotency_key`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task' AND INDEX_NAME='uk_ai_task_operation_token' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task' AND INDEX_NAME='uk_ai_task_operation_token' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_task_operation_token`, ADD UNIQUE KEY `uk_ai_task_operation_token` (`operation_token`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_task` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_task` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_task_event
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task_event' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task_event' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_task_event_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task_event' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task_event' AND INDEX_NAME='uk_ai_task_event_sequence' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_task_event' AND INDEX_NAME='uk_ai_task_event_sequence' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_task_event_sequence`, ADD UNIQUE KEY `uk_ai_task_event_sequence` (`task_id`,`sequence`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_task_event` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_task_event` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_ai_tool_call
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_tool_call' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_tool_call' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_ai_tool_call_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_tool_call' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_tool_call' AND INDEX_NAME='uk_ai_tool_call_idempotency' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_ai_tool_call' AND INDEX_NAME='uk_ai_tool_call_idempotency' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_ai_tool_call_idempotency`, ADD UNIQUE KEY `uk_ai_tool_call_idempotency` (`conversation_id`,`idempotency_key`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_ai_tool_call` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_ai_tool_call` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_attach
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_attach' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_attach' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_attach_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_attach' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_attach` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_attach` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_attach_group
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_attach_group' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_attach_group' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_attach_group_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_attach_group' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_attach_group` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_attach_group` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_auth_group
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_auth_group_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group' AND INDEX_NAME='name' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group' AND INDEX_NAME='name' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `name`, ADD UNIQUE KEY `name` (`name`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group' AND INDEX_NAME='uk_auth_group_code' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group' AND INDEX_NAME='uk_auth_group_code' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_auth_group_code`, ADD UNIQUE KEY `uk_auth_group_code` (`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_auth_group` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_auth_group` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_auth_group_department
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group_department' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group_department' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_auth_group_department_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_auth_group_department` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_auth_group_field_permission
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group_field_permission' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group_field_permission' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_auth_group_field_permission_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_auth_group_field_permission` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_auth_group_inherit
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group_inherit' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_auth_group_inherit' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_auth_group_inherit_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_auth_group_inherit` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_blacklist
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_blacklist' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_blacklist' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_blacklist_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_blacklist' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_blacklist` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_blacklist` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_browser_sessions
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_browser_sessions' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_browser_sessions' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_browser_sessions_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_browser_sessions` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_business_module
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_business_module' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_business_module' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_business_module_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_business_module' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_business_module' AND INDEX_NAME='uk_business_module_code' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_business_module' AND INDEX_NAME='uk_business_module_code' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_business_module_code`, ADD UNIQUE KEY `uk_business_module_code` (`code`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_business_module' AND INDEX_NAME='uk_business_module_form' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_business_module' AND INDEX_NAME='uk_business_module_form' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_business_module_form`, ADD UNIQUE KEY `uk_business_module_form` (`form_id`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_business_module` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_business_module` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_casbin_rule
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_casbin_rule' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_casbin_rule' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_casbin_rule_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_casbin_rule' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_casbin_rule' AND INDEX_NAME='uk_rule_hash' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_casbin_rule' AND INDEX_NAME='uk_rule_hash' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_rule_hash`, ADD UNIQUE KEY `uk_rule_hash` (`rule_hash`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_casbin_rule` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_casbin_rule` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_config
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_config_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config' AND INDEX_NAME='code' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config' AND INDEX_NAME='code' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `code`, ADD UNIQUE KEY `code` (`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_config` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_config` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_config_group
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config_group' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config_group' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_config_group_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config_group' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config_group' AND INDEX_NAME='uk_config_group_name' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_config_group' AND INDEX_NAME='uk_config_group_name' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_config_group_name`, ADD UNIQUE KEY `uk_config_group_name` (`name`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_config_group` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_config_group` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_crud_generation
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_crud_generation_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_crud_generation' AND INDEX_NAME='idx_crud_generation_operation_key' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_crud_generation' AND INDEX_NAME='idx_crud_generation_operation_key' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `idx_crud_generation_operation_key`, ADD UNIQUE KEY `idx_crud_generation_operation_key` (`operation_key`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_crud_generation` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_crud_generation` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_department
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_department' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_department' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_department_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_department' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_department` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_department` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_dict_item
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_item' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_item' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_dict_item_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_item' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_item' AND INDEX_NAME='uk_dict_item_value' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_item' AND INDEX_NAME='uk_dict_item_value' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_dict_item_value`, ADD UNIQUE KEY `uk_dict_item_value` (`type_id`,`value`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_dict_item` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_dict_item` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_dict_type
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_type' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_type' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_dict_type_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_type' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_type' AND INDEX_NAME='uk_dict_type_code' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_dict_type' AND INDEX_NAME='uk_dict_type_code' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_dict_type_code`, ADD UNIQUE KEY `uk_dict_type_code` (`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_dict_type` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_dict_type` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_failed_jobs
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_failed_jobs' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_failed_jobs' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_failed_jobs_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_failed_jobs' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_failed_jobs` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_failed_jobs` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_field_type
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_field_type' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_field_type' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_field_type_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_field_type' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_field_type` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_field_type` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_field_verify
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_field_verify' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_field_verify' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_field_verify_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_field_verify' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_field_verify' AND INDEX_NAME='verify' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_field_verify' AND INDEX_NAME='verify' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `verify`, ADD UNIQUE KEY `verify` (`verify`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_field_verify` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_field_verify` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_form
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_form_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form' AND INDEX_NAME='uk_form_key' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form' AND INDEX_NAME='uk_form_key' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_form_key`, ADD UNIQUE KEY `uk_form_key` (`form_key`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_form` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_form` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_form_list_action_execution
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_list_action_execution' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_list_action_execution' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_form_list_action_execution_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_form_list_action_execution` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_form_publish_attempt
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_publish_attempt' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_publish_attempt' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_form_publish_attempt_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_publish_attempt' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_publish_attempt' AND INDEX_NAME='uk_form_publish_operation' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_publish_attempt' AND INDEX_NAME='uk_form_publish_operation' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_form_publish_operation`, ADD UNIQUE KEY `uk_form_publish_operation` (`operation_key`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_form_publish_attempt` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_form_publish_attempt` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_form_schema_version
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_schema_version' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_schema_version' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_form_schema_version_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_schema_version' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_schema_version' AND INDEX_NAME='uk_form_schema_version' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_schema_version' AND INDEX_NAME='uk_form_schema_version' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_form_schema_version`, ADD UNIQUE KEY `uk_form_schema_version` (`form_id`,`version`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_schema_version' AND INDEX_NAME='uk_form_schema_hash' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_form_schema_version' AND INDEX_NAME='uk_form_schema_hash' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_form_schema_hash`, ADD UNIQUE KEY `uk_form_schema_hash` (`form_id`,`schema_hash`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_form_schema_version` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_form_schema_version` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_generated_file_baseline
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_generated_file_baseline' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_generated_file_baseline' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_generated_file_baseline_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_generated_file_baseline' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_generated_file_baseline' AND INDEX_NAME='uk_generated_baseline_module_path' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_generated_file_baseline' AND INDEX_NAME='uk_generated_baseline_module_path' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_generated_baseline_module_path`, ADD UNIQUE KEY `uk_generated_baseline_module_path` (`business_module_id`,`relative_path`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_generated_file_baseline` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_generated_file_baseline` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_jobs
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_jobs' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_jobs' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_jobs_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_jobs' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_jobs` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_jobs` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_language
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_language_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language' AND INDEX_NAME='uk_language_name' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language' AND INDEX_NAME='uk_language_name' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_language_name`, ADD UNIQUE KEY `uk_language_name` (`name`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_language` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_language` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_language_line
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language_line' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language_line' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_language_line_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language_line' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language_line' AND INDEX_NAME='uk_language_line_locale_key' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_language_line' AND INDEX_NAME='uk_language_line_locale_key' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_language_line_locale_key`, ADD UNIQUE KEY `uk_language_line_locale_key` (`locale`,`key`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_language_line` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_language_line` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_languages
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_languages' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_languages' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_languages_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_languages' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_languages' AND INDEX_NAME='name' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_languages' AND INDEX_NAME='name' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `name`, ADD UNIQUE KEY `name` (`name`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_languages` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_languages` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_login_attempts
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_login_attempts' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_login_attempts' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_login_attempts_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_login_attempts` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_member
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_member_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='uk_member_mobile' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='uk_member_mobile' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_member_mobile`, ADD UNIQUE KEY `uk_member_mobile` (`mobile`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='uk_member_username' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='uk_member_username' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_member_username`, ADD UNIQUE KEY `uk_member_username` (`username`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='uk_member_email' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='uk_member_email' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_member_email`, ADD UNIQUE KEY `uk_member_email` (`email`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='username' AND NON_UNIQUE=1)>0, 'DROP INDEX `username`', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='mobile' AND NON_UNIQUE=1)>0, 'DROP INDEX `mobile`', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member' AND INDEX_NAME='email' AND NON_UNIQUE=1)>0, 'DROP INDEX `email`', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_member` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_member` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_member_api_session
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_member_api_session_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session' AND INDEX_NAME='uk_member_api_session' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session' AND INDEX_NAME='uk_member_api_session' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_member_api_session`, ADD UNIQUE KEY `uk_member_api_session` (`session_id`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session' AND INDEX_NAME='uk_member_api_refresh' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session' AND INDEX_NAME='uk_member_api_refresh' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_member_api_refresh`, ADD UNIQUE KEY `uk_member_api_refresh` (`refresh_hash`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_member_api_session` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_member_api_session` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_member_api_session_used
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session_used' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_api_session_used' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_member_api_session_used_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_member_api_session_used` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_member_group
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_group' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_group' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_member_group_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_group' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_member_group` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_member_group` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_member_group_relation
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_group_relation' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_group_relation' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_member_group_relation_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_member_group_relation` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_member_level
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_level' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_level' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_member_level_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_level' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_level' AND INDEX_NAME='id')>0, 'DROP INDEX `id`', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_member_level` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_member_level` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_member_tag
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_tag' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_tag' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_member_tag_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_tag' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_tag' AND INDEX_NAME='uk_member_tag_name' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_tag' AND INDEX_NAME='uk_member_tag_name' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_member_tag_name`, ADD UNIQUE KEY `uk_member_tag_name` (`name`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_member_tag` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_member_tag` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_member_tag_relation
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_tag_relation' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_member_tag_relation' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_member_tag_relation_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_member_tag_relation` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_permission
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_permission_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission' AND INDEX_NAME='uk_permission_code' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission' AND INDEX_NAME='uk_permission_code' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_permission_code`, ADD UNIQUE KEY `uk_permission_code` (`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_permission` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_permission` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_permission_field
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission_field' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission_field' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_permission_field_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission_field' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission_field' AND INDEX_NAME='uk_permission_field_resource_field' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_permission_field' AND INDEX_NAME='uk_permission_field_resource_field' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_permission_field_resource_field`, ADD UNIQUE KEY `uk_permission_field_resource_field` (`resource`,`field`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_permission_field` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_permission_field` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_plugin
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_plugin_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin' AND INDEX_NAME='uk_plugin_code' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin' AND INDEX_NAME='uk_plugin_code' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_plugin_code`, ADD UNIQUE KEY `uk_plugin_code` (`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_plugin` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_plugin` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_plugin_operation
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_operation' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_operation' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_plugin_operation_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_operation' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_plugin_operation` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_plugin_operation` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_plugin_resource
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_resource' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_resource' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_plugin_resource_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_resource' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_resource' AND INDEX_NAME='uk_plugin_resource_target_path' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_resource' AND INDEX_NAME='uk_plugin_resource_target_path' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_plugin_resource_target_path`, ADD UNIQUE KEY `uk_plugin_resource_target_path` (`target_path`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_plugin_resource` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_plugin_resource` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_plugin_version_history
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_version_history' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_version_history' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_plugin_version_history_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_plugin_version_history' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_plugin_version_history` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_plugin_version_history` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_provinces
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_provinces' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_provinces' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_provinces_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_provinces' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_provinces` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_provinces` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_region
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_region' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_region' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_region_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_region' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_region` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_region` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_system_migration
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_system_migration' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_system_migration' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_system_migration_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_system_migration' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_system_migration' AND INDEX_NAME='uk_scope_version' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_system_migration' AND INDEX_NAME='uk_scope_version' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_scope_version`, ADD UNIQUE KEY `uk_scope_version` (`scope`,`version`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_system_migration` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_system_migration` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_system_secret
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_system_secret' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_system_secret' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_system_secret_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_system_secret` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_upgrade_manifest
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_manifest' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_manifest' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_upgrade_manifest_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_manifest' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_manifest' AND INDEX_NAME='uk_upgrade_manifest_id' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_manifest' AND INDEX_NAME='uk_upgrade_manifest_id' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_upgrade_manifest_id`, ADD UNIQUE KEY `uk_upgrade_manifest_id` (`manifest_id`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_upgrade_manifest` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_upgrade_manifest` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_upgrade_task
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_upgrade_task_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND INDEX_NAME='uk_upgrade_task_token' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND INDEX_NAME='uk_upgrade_task_token' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_upgrade_task_token`, ADD UNIQUE KEY `uk_upgrade_task_token` (`idempotency_token`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND INDEX_NAME='uk_upgrade_task_active' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_upgrade_task' AND INDEX_NAME='uk_upgrade_task_active' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `uk_upgrade_task_active`, ADD UNIQUE KEY `uk_upgrade_task_active` (`active_slot`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_upgrade_task` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_upgrade_task` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;
