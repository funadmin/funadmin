-- 007 CMS soft delete (1.6.0), same rules as core 005_soft_delete:
-- every non-exempt table has an auto-increment `id` primary key and `deleted_at` DATETIME NULL with an index led by it;
-- tables with a unique key also get `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0 (0 = live, the row id once
-- deleted) and every unique key is rebuilt under its own name as "original columns + deleted_token", so a deleted
-- code / host / path / pair can be created again.
-- deleted_at changes from TIMESTAMP to DATETIME on cms_site, cms_category, cms_content, cms_content_locale,
-- cms_template_tag and cms_site_role (MODIFY rebuilds the table).
-- cms_site_admin, cms_site_model, cms_site_role_permission and cms_site_role_member never had a primary key: each gets
-- an auto-increment `id` primary key (existing rows are numbered automatically) and keeps its unique key name.
-- Already soft-deleted rows get deleted_token = id.
-- Exempt (append-only history, never deleted): cms_form_event, cms_site_auth_audit, cms_model_version,
-- cms_content_revision, cms_template_tag_version.
-- Every statement is guarded by information_schema and can be re-run; one ALTER per table, executed with PREPARE.

-- fun_cms_site
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site' AND COLUMN_NAME='deleted_at' AND DATA_TYPE='timestamp')>0, 'MODIFY COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_site_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site' AND INDEX_NAME='cms_site_code_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site' AND INDEX_NAME='cms_site_code_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_site_code_unique`, ADD UNIQUE KEY `cms_site_code_unique` (`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_site` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_site` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_site_locale
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_locale' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_locale' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_site_locale_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_locale' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_locale' AND INDEX_NAME='cms_site_locale_site_id_locale_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_locale' AND INDEX_NAME='cms_site_locale_site_id_locale_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_site_locale_site_id_locale_unique`, ADD UNIQUE KEY `cms_site_locale_site_id_locale_unique` (`site_id`,`locale`,`deleted_token`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_locale' AND INDEX_NAME='cms_site_locale_site_id_url_prefix_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_locale' AND INDEX_NAME='cms_site_locale_site_id_url_prefix_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_site_locale_site_id_url_prefix_unique`, ADD UNIQUE KEY `cms_site_locale_site_id_url_prefix_unique` (`site_id`,`url_prefix`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_site_locale` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_site_locale` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_domain
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_domain' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_domain' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_domain_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_domain' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_domain' AND INDEX_NAME='cms_domain_host_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_domain' AND INDEX_NAME='cms_domain_host_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_domain_host_unique`, ADD UNIQUE KEY `cms_domain_host_unique` (`host`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_domain` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_domain` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_site_admin
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_admin' AND COLUMN_NAME='id')=0, 'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_admin' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_admin' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_site_admin_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_admin' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_admin' AND INDEX_NAME='cms_site_admin_site_id_admin_id_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_admin' AND INDEX_NAME='cms_site_admin_site_id_admin_id_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_site_admin_site_id_admin_id_unique`, ADD UNIQUE KEY `cms_site_admin_site_id_admin_id_unique` (`site_id`,`admin_id`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_site_admin` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_site_admin` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_content_model
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_model' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_model' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_content_model_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_model' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_model' AND INDEX_NAME='cms_content_model_code_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_model' AND INDEX_NAME='cms_content_model_code_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_content_model_code_unique`, ADD UNIQUE KEY `cms_content_model_code_unique` (`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_content_model` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_content_model` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_site_model
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_model' AND COLUMN_NAME='id')=0, 'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_model' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_model' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_site_model_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_model' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_model' AND INDEX_NAME='cms_site_model_site_id_model_id_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_model' AND INDEX_NAME='cms_site_model_site_id_model_id_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_site_model_site_id_model_id_unique`, ADD UNIQUE KEY `cms_site_model_site_id_model_id_unique` (`site_id`,`model_id`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_site_model` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_site_model` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_category
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_category' AND COLUMN_NAME='deleted_at' AND DATA_TYPE='timestamp')>0, 'MODIFY COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_category' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_category' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_category_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_category` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_cms_category_locale
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_category_locale' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_category_locale' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_category_locale_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_category_locale' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_category_locale' AND INDEX_NAME='cms_category_locale_category_id_locale_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_category_locale' AND INDEX_NAME='cms_category_locale_category_id_locale_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_category_locale_category_id_locale_unique`, ADD UNIQUE KEY `cms_category_locale_category_id_locale_unique` (`category_id`,`locale`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_category_locale` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_category_locale` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_content
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content' AND COLUMN_NAME='deleted_at' AND DATA_TYPE='timestamp')>0, 'MODIFY COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_content_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_content` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_cms_content_locale
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_locale' AND COLUMN_NAME='deleted_at' AND DATA_TYPE='timestamp')>0, 'MODIFY COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_locale' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_locale' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_content_locale_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_locale' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_locale' AND INDEX_NAME='cms_content_locale_content_id_locale_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_content_locale' AND INDEX_NAME='cms_content_locale_content_id_locale_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_content_locale_content_id_locale_unique`, ADD UNIQUE KEY `cms_content_locale_content_id_locale_unique` (`content_id`,`locale`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_content_locale` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_content_locale` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_route
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_route' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_route' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_route_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_route' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_route' AND INDEX_NAME='cms_route_site_id_path_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_route' AND INDEX_NAME='cms_route_site_id_path_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_route_site_id_path_unique`, ADD UNIQUE KEY `cms_route_site_id_path_unique` (`site_id`,`path`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_route` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_route` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_menu
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_menu' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_menu' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_menu_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_menu' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_menu' AND INDEX_NAME='cms_menu_site_id_locale_code_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_menu' AND INDEX_NAME='cms_menu_site_id_locale_code_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_menu_site_id_locale_code_unique`, ADD UNIQUE KEY `cms_menu_site_id_locale_code_unique` (`site_id`,`locale`,`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_menu` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_menu` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_menu_item
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_menu_item' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_menu_item' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_menu_item_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_menu_item` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_cms_template_tag
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_template_tag' AND COLUMN_NAME='deleted_at' AND DATA_TYPE='timestamp')>0, 'MODIFY COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_template_tag' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_template_tag' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_template_tag_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_template_tag' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_template_tag' AND INDEX_NAME='cms_template_tag_site_id_locale_name_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_template_tag' AND INDEX_NAME='cms_template_tag_site_id_locale_name_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_template_tag_site_id_locale_name_unique`, ADD UNIQUE KEY `cms_template_tag_site_id_locale_name_unique` (`site_id`,`locale`,`name`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_template_tag` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_template_tag` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_site_role
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role' AND COLUMN_NAME='deleted_at' AND DATA_TYPE='timestamp')>0, 'MODIFY COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_site_role_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role' AND INDEX_NAME='cms_site_role_site_id_code_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role' AND INDEX_NAME='cms_site_role_site_id_code_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_site_role_site_id_code_unique`, ADD UNIQUE KEY `cms_site_role_site_id_code_unique` (`site_id`,`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_site_role` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_site_role` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_site_role_permission
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_permission' AND COLUMN_NAME='id')=0, 'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_permission' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_permission' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_site_role_permission_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_permission' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_permission' AND INDEX_NAME='cms_role_permission_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_permission' AND INDEX_NAME='cms_role_permission_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_role_permission_unique`, ADD UNIQUE KEY `cms_role_permission_unique` (`site_id`,`role_id`,`permission_code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_site_role_permission` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_site_role_permission` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_site_role_member
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_member' AND COLUMN_NAME='id')=0, 'ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (`id`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_member' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_member' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_site_role_member_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_member' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_member' AND INDEX_NAME='cms_role_member_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_site_role_member' AND INDEX_NAME='cms_role_member_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_role_member_unique`, ADD UNIQUE KEY `cms_role_member_unique` (`site_id`,`role_id`,`admin_id`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_site_role_member` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_site_role_member` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_member_submission
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_member_submission' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_member_submission' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_member_submission_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_member_submission' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_member_submission' AND INDEX_NAME='cms_submission_request_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_member_submission' AND INDEX_NAME='cms_submission_request_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_submission_request_unique`, ADD UNIQUE KEY `cms_submission_request_unique` (`site_id`,`member_id`,`request_key`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_member_submission` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_member_submission` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_form
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_form_deleted_at` (`deleted_at`)', NULL),
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form' AND COLUMN_NAME='deleted_token')=0, 'ADD COLUMN `deleted_token` BIGINT UNSIGNED NOT NULL DEFAULT 0', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form' AND INDEX_NAME='cms_form_site_code_unique' AND NON_UNIQUE=0)>0 AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form' AND INDEX_NAME='cms_form_site_code_unique' AND COLUMN_NAME='deleted_token')=0, 'DROP INDEX `cms_form_site_code_unique`, ADD UNIQUE KEY `cms_form_site_code_unique` (`site_id`,`code`,`deleted_token`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_form` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
UPDATE `fun_cms_form` SET `deleted_token`=`id` WHERE `deleted_at` IS NOT NULL AND `deleted_token`=0;

-- fun_cms_form_submission
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form_submission' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form_submission' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_form_submission_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_form_submission` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;

-- fun_cms_form_file
SET @soft_delete_ddl = CONCAT_WS(', ',
  IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form_file' AND COLUMN_NAME='deleted_at')=0, 'ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL', NULL),
  IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_cms_form_file' AND COLUMN_NAME='deleted_at' AND SEQ_IN_INDEX=1)=0, 'ADD INDEX `idx_cms_form_file_deleted_at` (`deleted_at`)', NULL)
);
SET @soft_delete_ddl = IF(@soft_delete_ddl = '', 'DO 0', CONCAT('ALTER TABLE `fun_cms_form_file` ', @soft_delete_ddl));
PREPARE soft_delete_stmt FROM @soft_delete_ddl;
EXECUTE soft_delete_stmt;
DEALLOCATE PREPARE soft_delete_stmt;
