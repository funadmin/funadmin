-- 117 AI 独立配置档案；仅新增表，不修改历史数据。
CREATE TABLE IF NOT EXISTS `fun_ai_configuration_profile` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint unsigned NOT NULL,
  `name` varchar(100) NOT NULL,
  `configuration` json NOT NULL,
  `secret_ciphertext` text NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `default_admin_id` bigint unsigned GENERATED ALWAYS AS (CASE WHEN `is_default` = 1 THEN `admin_id` ELSE NULL END) STORED,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ai_profile_admin` (`admin_id`, `id`),
  UNIQUE KEY `uk_ai_profile_default` (`default_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
