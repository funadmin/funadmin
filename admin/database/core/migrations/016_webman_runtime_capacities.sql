-- 承接当前 Webman 运行时的长令牌、阶段名与恢复路径，仅扩容，保留可空性、默认值及唯一约束。
ALTER TABLE `fun_plugin`
  MODIFY COLUMN `version` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '版本号',
  MODIFY COLUMN `error_stage` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '失败阶段',
  MODIFY COLUMN `recovery_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '人工恢复路径',
  MODIFY COLUMN `lifecycle_state` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'disabled' COMMENT '生命周期状态',
  MODIFY COLUMN `operation_token` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '生命周期操作令牌';
ALTER TABLE `fun_plugin_operation`
  MODIFY COLUMN `stage` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'complete' COMMENT '生命周期阶段',
  MODIFY COLUMN `operation_token` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '生命周期操作令牌';
ALTER TABLE `fun_plugin_resource`
  MODIFY COLUMN `resource_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'file',
  MODIFY COLUMN `source_path` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL;
ALTER TABLE `fun_plugin_version_history` MODIFY COLUMN `package_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `fun_upgrade_task`
  MODIFY COLUMN `idempotency_token` varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  MODIFY COLUMN `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  MODIFY COLUMN `stage` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  MODIFY COLUMN `backup_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  MODIFY COLUMN `db_backup_path` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
ALTER TABLE `fun_upgrade_manifest` MODIFY COLUMN `manifest_id` varchar(96) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
