-- 072 FormSchema v2：规范文档与不可变版本历史，仅向前新增。
SET @schema_name = DATABASE();

SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='schema_version'),'ALTER TABLE `fun_form` ADD COLUMN `schema_version` int NOT NULL DEFAULT 1 AFTER `form_config`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='schema_document'),'ALTER TABLE `fun_form` ADD COLUMN `schema_document` json DEFAULT NULL AFTER `schema_version`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='schema_hash'),'ALTER TABLE `fun_form` ADD COLUMN `schema_hash` char(64) NULL AFTER `schema_document`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='schema_origin'),'ALTER TABLE `fun_form` ADD COLUMN `schema_origin` varchar(20) NOT NULL DEFAULT ''designer'' AFTER `schema_hash`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='published_schema_hash'),'ALTER TABLE `fun_form` ADD COLUMN `published_schema_hash` char(64) NULL AFTER `published_definition_hash`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `fun_form_schema_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `schema_version` int unsigned NOT NULL DEFAULT 2,
  `schema_hash` char(64) NOT NULL,
  `schema_document` json NOT NULL,
  `origin` varchar(20) NOT NULL DEFAULT 'designer',
  `parent_version_id` bigint unsigned NULL,
  `change_summary` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_form_schema_version` (`form_id`,`version`),
  UNIQUE KEY `uk_form_schema_hash` (`form_id`,`schema_hash`),
  KEY `idx_form_schema_parent` (`parent_version_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Immutable FormSchema version history';
