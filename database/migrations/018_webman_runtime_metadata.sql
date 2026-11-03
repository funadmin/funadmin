-- 补齐 Webman 运行时审计元数据；只扩容或新增，不复制授权，不重写已有记录。
ALTER TABLE `fun_admin` MODIFY COLUMN `status` tinyint(1) DEFAULT '1' COMMENT '审核状态';
ALTER TABLE `fun_plugin`
  MODIFY COLUMN `name` varchar(250) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  MODIFY COLUMN `status` tinyint(1) DEFAULT '0' COMMENT '插件状态';
ALTER TABLE `fun_admin_log`
  MODIFY COLUMN `source_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  MODIFY COLUMN `source_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'core',
  MODIFY COLUMN `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `ip` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `fun_blacklist` MODIFY COLUMN `ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '';
ALTER TABLE `fun_plugin_operation` ADD COLUMN `metadata` json DEFAULT NULL;
ALTER TABLE `fun_plugin_version_history`
  ADD COLUMN `package_size` bigint unsigned NOT NULL DEFAULT '0',
  ADD COLUMN `manifest` text COLLATE utf8mb4_unicode_ci;
