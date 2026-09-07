-- 066 统一表单发布引擎：发布配置、状态与 CRUD 生成审计关联。
SET @schema_name = DATABASE();

SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='publish_config'),'ALTER TABLE `fun_form` ADD COLUMN `publish_config` json DEFAULT NULL COMMENT ''全栈发布配置'' AFTER `form_config`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='publish_status'),'ALTER TABLE `fun_form` ADD COLUMN `publish_status` varchar(20) NOT NULL DEFAULT ''draft'' COMMENT ''draft/publishing/published/partial/conflict/failed'' AFTER `publish_config`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='published_at'),'ALTER TABLE `fun_form` ADD COLUMN `published_at` datetime NULL AFTER `publish_status`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='crud_generation_id'),'ALTER TABLE `fun_form` ADD COLUMN `crud_generation_id` bigint unsigned NULL AFTER `published_at`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='published_definition_hash'),'ALTER TABLE `fun_form` ADD COLUMN `published_definition_hash` char(64) NULL AFTER `crud_generation_id`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND INDEX_NAME='idx_form_publish_status'),'ALTER TABLE `fun_form` ADD KEY `idx_form_publish_status` (`publish_status`,`published_at`)','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND INDEX_NAME='idx_form_crud_generation'),'ALTER TABLE `fun_form` ADD KEY `idx_form_crud_generation` (`crud_generation_id`)','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 发布操作使用独立权限，覆盖与资源应用仍由后端进行二次权限校验。
SET @form_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='form_management' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@form_group_id,'console','form:publish:preview','console/form.designer','previewpublish','预览表单发布','route',1,0,60,'admin_web','form_management',NOW(),NOW(),60,NULL),
(@form_group_id,'console','form:publish:generate','console/form.designer','publish','发布表单全栈代码','route',1,0,61,'admin_web','form_management',NOW(),NOW(),61,NULL),
(@form_group_id,'console','form:publish:status','console/form.designer','publishstatus','查看表单发布状态','route',1,0,62,'admin_web','form_management',NOW(),NOW(),62,NULL),
(@form_group_id,'console','form:publish:resources','console/form.designer','retryresources','重试表单菜单权限','route',1,0,63,'admin_web','form_management',NOW(),NOW(),63,NULL);
