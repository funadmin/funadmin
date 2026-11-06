-- 独立接管证据与实际 SQL 执行历史分离；此迁移只建登记表，不写接管记录。
CREATE TABLE IF NOT EXISTS `fun_core_database_adoption` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plan_digest` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `database_name` varchar(64) NOT NULL,
  `table_prefix` varchar(64) NOT NULL,
  `server_uuid` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `source_checksums` json NOT NULL,
  `reference_digest` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `before_digest` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `after_digest` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `backup_path` varchar(1024) NOT NULL,
  `backup_checksum` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `stage` varchar(32) NOT NULL DEFAULT 'prepared',
  `completed_operations` json NOT NULL,
  `evidence` json NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_core_adoption_plan` (`plan_digest`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
