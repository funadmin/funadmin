-- 077 统一业务开发中心领域模型：业务模块、生成基线及可靠关联。
-- 仅向前新增和幂等回填；origin 为 visual/database/legacy_form；无法证明归属的记录保持 unbound。
SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS `fun_business_module` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `form_id` bigint unsigned NULL,
  `origin` enum('visual','database','legacy_form') NOT NULL DEFAULT 'visual',
  `connection_name` varchar(64) NOT NULL DEFAULT 'mysql',
  `table_name` varchar(190) NOT NULL DEFAULT '',
  `runtime_route` varchar(255) NOT NULL DEFAULT '',
  `module_route` varchar(255) NOT NULL DEFAULT '',
  `lifecycle_status` varchar(32) NOT NULL DEFAULT 'draft',
  `published_schema_hash` char(64) NULL,
  `published_schema_version` int unsigned NULL,
  `current_generation_id` bigint unsigned NULL,
  `last_success_generation_id` bigint unsigned NULL,
  `generation_status` varchar(32) NOT NULL DEFAULT 'idle',
  `created_by` bigint unsigned NULL,
  `updated_by` bigint unsigned NULL,
  `metadata` json DEFAULT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_business_module_code` (`code`),
  UNIQUE KEY `uk_business_module_form` (`form_id`),
  KEY `idx_business_module_origin_lifecycle` (`origin`,`lifecycle_status`),
  KEY `idx_business_module_table` (`connection_name`,`table_name`),
  KEY `idx_business_module_published_hash` (`published_schema_hash`),
  KEY `idx_business_module_current_generation` (`current_generation_id`),
  KEY `idx_business_module_last_success_generation` (`last_success_generation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Business development modules';

CREATE TABLE IF NOT EXISTS `fun_generated_file_baseline` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_module_id` bigint unsigned NOT NULL,
  `relative_path` varchar(512) NOT NULL,
  `artifact_type` varchar(64) NOT NULL DEFAULT '',
  `base_hash` char(64) NOT NULL,
  `base_storage_path` varchar(512) NOT NULL COMMENT 'Relative runtime private path written by repository',
  `target_hash` char(64) NULL,
  `template_version` varchar(64) NOT NULL DEFAULT '',
  `definition_hash` char(64) NOT NULL,
  `generation_id` bigint unsigned NULL,
  `content_kind` varchar(32) NOT NULL DEFAULT 'text',
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_generated_baseline_module_path` (`business_module_id`,`relative_path`),
  KEY `idx_generated_baseline_generation` (`generation_id`),
  KEY `idx_generated_baseline_base_hash` (`base_hash`),
  KEY `idx_generated_baseline_target_hash` (`target_hash`),
  KEY `idx_generated_baseline_definition_hash` (`definition_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Reliable generated file bases';

-- 扩展生成审计。所有新增关联字段默认不绑定，后续只通过可验证证据写入。
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='business_module_id'),'ALTER TABLE `fun_crud_generation` ADD COLUMN `business_module_id` bigint unsigned NULL AFTER `id`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='form_id'),'ALTER TABLE `fun_crud_generation` ADD COLUMN `form_id` bigint unsigned NULL AFTER `business_module_id`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='binding_status'),'ALTER TABLE `fun_crud_generation` ADD COLUMN `binding_status` varchar(20) NOT NULL DEFAULT ''unbound'' AFTER `form_id`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='generation_mode'),'ALTER TABLE `fun_crud_generation` ADD COLUMN `generation_mode` varchar(32) NOT NULL DEFAULT ''full'' AFTER `binding_status`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='transaction_id'),'ALTER TABLE `fun_crud_generation` ADD COLUMN `transaction_id` varchar(64) NULL AFTER `generation_mode`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='plan_digest'),'ALTER TABLE `fun_crud_generation` ADD COLUMN `plan_digest` char(64) NULL AFTER `transaction_id`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND COLUMN_NAME='recovery_status'),'ALTER TABLE `fun_crud_generation` ADD COLUMN `recovery_status` varchar(32) NOT NULL DEFAULT ''none'' AFTER `plan_digest`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND INDEX_NAME='idx_crud_generation_business_module'),'ALTER TABLE `fun_crud_generation` ADD KEY `idx_crud_generation_business_module` (`business_module_id`,`binding_status`)','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND INDEX_NAME='idx_crud_generation_form'),'ALTER TABLE `fun_crud_generation` ADD KEY `idx_crud_generation_form` (`form_id`,`binding_status`)','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND INDEX_NAME='idx_crud_generation_transaction'),'ALTER TABLE `fun_crud_generation` ADD KEY `idx_crud_generation_transaction` (`transaction_id`)','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_crud_generation' AND INDEX_NAME='idx_crud_generation_plan_digest'),'ALTER TABLE `fun_crud_generation` ADD KEY `idx_crud_generation_plan_digest` (`plan_digest`)','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 表单只新增领域关联和发布模式，不改写 Form Schema v2 内容。
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='business_module_id'),'ALTER TABLE `fun_form` ADD COLUMN `business_module_id` bigint unsigned NULL AFTER `id`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='publish_mode'),'ALTER TABLE `fun_form` ADD COLUMN `publish_mode` varchar(20) NOT NULL DEFAULT ''dynamic'' AFTER `business_module_id`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND INDEX_NAME='idx_form_business_module'),'ALTER TABLE `fun_form` ADD KEY `idx_form_business_module` (`business_module_id`)','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 每个未删除 legacy form 创建唯一模块；published_schema_version/hash 必须来自同一条 hash 精确匹配快照。
INSERT IGNORE INTO `fun_business_module` (
  `code`,`name`,`form_id`,`origin`,`connection_name`,`table_name`,`runtime_route`,`module_route`,`lifecycle_status`,
  `published_schema_hash`,`published_schema_version`,`current_generation_id`,`last_success_generation_id`,`generation_status`,
  `created_by`,`updated_by`,`metadata`,`created_at`,`updated_at`,`deleted_at`
)
SELECT
  f.`form_key`,f.`name`,f.`id`,'legacy_form',f.`connection`,f.`table_name`,
  CONCAT('/development/business/runtime/',f.`form_key`),CONCAT('/development/business/',f.`form_key`),
  CASE WHEN f.`status`=0 THEN 'disabled' WHEN f.`publish_status`='published' THEN 'published' ELSE 'draft' END,
  CASE WHEN f.`publish_status`='published' AND EXISTS(
    SELECT 1 FROM `fun_form_schema_version` v WHERE v.`form_id`=f.`id` AND v.`schema_hash`=f.`published_schema_hash`
  ) THEN f.`published_schema_hash` ELSE NULL END,
  CASE WHEN f.`publish_status`='published' THEN (
    SELECT MAX(v.`version`) FROM `fun_form_schema_version` v WHERE v.`form_id`=f.`id` AND v.`schema_hash`=f.`published_schema_hash`
  ) ELSE NULL END,
  f.`crud_generation_id`,NULL,'idle',NULL,NULL,JSON_OBJECT('legacyFormId',f.`id`),
  COALESCE(f.`created_at`,NOW()),COALESCE(f.`updated_at`,NOW()),NULL
FROM `fun_form` f
WHERE f.`deleted_at` IS NULL;

UPDATE `fun_business_module` m
INNER JOIN `fun_form` f ON f.`id`=m.`form_id` AND f.`form_key`=m.`code`
SET m.`name`=f.`name`,m.`connection_name`=f.`connection`,m.`table_name`=f.`table_name`,
    m.`runtime_route`=CONCAT('/development/business/runtime/',f.`form_key`),
    m.`module_route`=CONCAT('/development/business/',f.`form_key`),
    m.`lifecycle_status`=CASE WHEN f.`status`=0 THEN 'disabled' WHEN f.`publish_status`='published' THEN 'published' ELSE 'draft' END,
    m.`published_schema_hash`=CASE WHEN f.`publish_status`='published' AND EXISTS(
      SELECT 1 FROM `fun_form_schema_version` v WHERE v.`form_id`=f.`id` AND v.`schema_hash`=f.`published_schema_hash`
    ) THEN f.`published_schema_hash` ELSE NULL END,
    m.`published_schema_version`=CASE WHEN f.`publish_status`='published' THEN (
      SELECT MAX(v.`version`) FROM `fun_form_schema_version` v WHERE v.`form_id`=f.`id` AND v.`schema_hash`=f.`published_schema_hash`
    ) ELSE NULL END,
    m.`current_generation_id`=f.`crud_generation_id`,m.`updated_at`=COALESCE(f.`updated_at`,NOW())
WHERE m.`origin`='legacy_form' AND m.`deleted_at` IS NULL AND f.`deleted_at` IS NULL;

UPDATE `fun_form` f
INNER JOIN `fun_business_module` m ON m.`form_id`=f.`id` AND m.`code`=f.`form_key` AND m.`origin`='legacy_form' AND m.`deleted_at` IS NULL
SET f.`business_module_id`=m.`id`
WHERE f.`deleted_at` IS NULL AND (f.`business_module_id` IS NULL OR f.`business_module_id`<>m.`id`);

-- 第一优先级：唯一显式 crud_generation_id，并且 definition.formSchemaHash 与模块发布 hash 完全一致。
UPDATE `fun_crud_generation` g
INNER JOIN `fun_form` f ON f.`crud_generation_id`=g.`id` AND f.`deleted_at` IS NULL
INNER JOIN `fun_business_module` m ON m.`id`=f.`business_module_id` AND m.`deleted_at` IS NULL
SET g.`business_module_id`=m.`id`,g.`form_id`=f.`id`,g.`binding_status`='bound',g.`updated_at`=COALESCE(g.`updated_at`,NOW())
WHERE g.`deleted_at` IS NULL
  AND (SELECT COUNT(*) FROM `fun_form` reliable_form WHERE reliable_form.`crud_generation_id`=g.`id` AND reliable_form.`deleted_at` IS NULL) = 1
  AND JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.formSchemaHash'))=m.`published_schema_hash`
  AND (g.`business_module_id` IS NULL OR g.`business_module_id`=m.`id`)
  AND (g.`form_id` IS NULL OR g.`form_id`=f.`id`);

-- 第二优先级：无显式引用时，严格 hash 加 connection/table 只能唯一命中一个模块。
UPDATE `fun_crud_generation` g
INNER JOIN `fun_business_module` m
  ON m.`published_schema_hash`=JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.formSchemaHash'))
 AND m.`connection_name`=COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.connection')),''),g.`connection_name`)
 AND m.`table_name`=COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.table')),''),g.`table_name`)
 AND m.`deleted_at` IS NULL
SET g.`business_module_id`=m.`id`,g.`form_id`=m.`form_id`,g.`binding_status`='bound',g.`updated_at`=COALESCE(g.`updated_at`,NOW())
WHERE g.`deleted_at` IS NULL AND g.`business_module_id` IS NULL AND g.`form_id` IS NULL
  AND NOT EXISTS(SELECT 1 FROM `fun_form` explicit_form WHERE explicit_form.`crud_generation_id`=g.`id` AND explicit_form.`deleted_at` IS NULL)
  AND JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.formSchemaHash')) IS NOT NULL
  AND (SELECT COUNT(*) FROM `fun_business_module` reliable_module
       WHERE reliable_module.`published_schema_hash`=JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.formSchemaHash'))
         AND reliable_module.`connection_name`=COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.connection')),''),g.`connection_name`)
         AND reliable_module.`table_name`=COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.table')),''),g.`table_name`)
         AND reliable_module.`deleted_at` IS NULL) = 1;

-- 无 hash 时仅允许 definition metadata 中的 formId、connection、table 三者一致且唯一。
UPDATE `fun_crud_generation` g
INNER JOIN `fun_business_module` m
  ON m.`form_id`=CAST(JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.metadata.formId')) AS UNSIGNED)
 AND m.`connection_name`=JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.connection'))
 AND m.`table_name`=JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.table'))
 AND m.`deleted_at` IS NULL
SET g.`business_module_id`=m.`id`,g.`form_id`=m.`form_id`,g.`binding_status`='bound',g.`updated_at`=COALESCE(g.`updated_at`,NOW())
WHERE g.`deleted_at` IS NULL AND g.`business_module_id` IS NULL AND g.`form_id` IS NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.formSchemaHash')) IS NULL
  AND JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.metadata.formId')) IS NOT NULL
  AND (SELECT COUNT(*) FROM `fun_business_module` reliable_module
       WHERE reliable_module.`form_id`=CAST(JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.metadata.formId')) AS UNSIGNED)
         AND reliable_module.`connection_name`=JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.connection'))
         AND reliable_module.`table_name`=JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.table'))
         AND reliable_module.`deleted_at` IS NULL) = 1;

UPDATE `fun_business_module` m
LEFT JOIN `fun_crud_generation` current_generation ON current_generation.`id`=m.`current_generation_id` AND current_generation.`business_module_id`=m.`id`
SET m.`last_success_generation_id`=CASE WHEN current_generation.`status`='success' AND current_generation.`binding_status`='bound' THEN current_generation.`id` ELSE m.`last_success_generation_id` END,
    m.`generation_status`=CASE WHEN current_generation.`binding_status`='bound' THEN current_generation.`status` ELSE m.`generation_status` END
WHERE m.`deleted_at` IS NULL AND m.`current_generation_id` IS NOT NULL;
