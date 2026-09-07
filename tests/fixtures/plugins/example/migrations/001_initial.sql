-- 测试示例插件的幂等前向迁移，仅创建隔离测试表。
CREATE TABLE IF NOT EXISTS `fun_plugin_example_runtime_test` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payload` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
