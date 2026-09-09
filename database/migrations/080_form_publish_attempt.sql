-- 080 表单发布阶段状态机与幂等操作记录。
CREATE TABLE IF NOT EXISTS `fun_form_publish_attempt` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operation_key` varchar(128) NOT NULL,
  `form_id` bigint unsigned NULL,
  `schema_hash` char(64) NOT NULL,
  `definition_hash` char(64) NOT NULL,
  `dependency_hash` char(64) NOT NULL,
  `stage` varchar(32) NOT NULL DEFAULT 'prepared',
  `generation_id` bigint unsigned NULL,
  `status` varchar(32) NOT NULL DEFAULT 'running',
  `lease_token` char(32) NULL,
  `lease_expires_at` datetime NULL,
  `result` json DEFAULT NULL,
  `error` json DEFAULT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_form_publish_operation` (`operation_key`),
  KEY `idx_form_publish_attempt_form_hash` (`form_id`,`schema_hash`,`definition_hash`),
  KEY `idx_form_publish_attempt_status` (`status`,`stage`),
  KEY `idx_form_publish_attempt_lease` (`lease_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Idempotent form publish attempts';
