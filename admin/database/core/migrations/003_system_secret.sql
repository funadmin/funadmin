-- 003 系统内部密钥表：保存运行时自动生成、无需配置的密钥（如表单列表动作二次确认令牌的 HMAC 密钥）。
-- 与 PHP 迁移 database/migrations/003_system_secret.php 终态一致；只新增、可重复执行。
-- 值由应用首次使用时生成（bin2hex(random_bytes(32))），按主键 name 插入去重；不属于系统配置，不经任何配置接口读取或展示。
CREATE TABLE IF NOT EXISTS `fun_system_secret` (
  `name` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `value` varchar(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统内部密钥';
