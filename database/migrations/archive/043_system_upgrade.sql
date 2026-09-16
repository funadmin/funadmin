-- M6 系统升级持久任务与 Admin Web 菜单权限；仅向前新增并保持幂等。
CREATE TABLE IF NOT EXISTS `fun_upgrade_task` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `idempotency_token` varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `manifest_id` char(48) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `from_version` varchar(64) NOT NULL DEFAULT '',
  `to_version` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `stage` varchar(32) NOT NULL DEFAULT 'pending',
  `progress` tinyint unsigned NOT NULL DEFAULT 0,
  `package_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `backup_path` varchar(500) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `active_slot` tinyint unsigned DEFAULT NULL,
  `lease_expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_upgrade_task_token` (`idempotency_token`),
  UNIQUE KEY `uk_upgrade_task_active` (`active_slot`),
  KEY `idx_upgrade_task_status` (`status`,`created_at`),
  KEY `idx_upgrade_task_manifest` (`manifest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统升级任务';

CREATE TABLE IF NOT EXISTS `fun_upgrade_manifest` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `manifest_id` char(48) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload` json NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_upgrade_manifest_id` (`manifest_id`),
  KEY `idx_upgrade_manifest_expiry` (`expires_at`,`consumed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='一次性可信升级 manifest';

SET @system_permission_id = (SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='system_management' ORDER BY `id` LIMIT 1);
SET @system_menu_id = (SELECT `id` FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='system_management' ORDER BY `id` LIMIT 1);

INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT COALESCE(@system_permission_id,0),'console','system:upgrade:list','system:upgrade','list','系统升级','group',1,0,350,'admin_web','system_upgrade',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='system:upgrade:list');
SET @upgrade_permission_id = (SELECT `id` FROM `fun_permission` WHERE `code`='system:upgrade:list' ORDER BY `id` LIMIT 1);

INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @upgrade_permission_id,'console','console/systemupgrade:status','console/systemupgrade','status','查看升级任务','route',1,0,10,'admin_web','system_upgrade',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemupgrade:status');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @upgrade_permission_id,'console','console/systemupgrade:check','console/systemupgrade','check','检查系统更新','route',1,0,20,'admin_web','system_upgrade',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemupgrade:check');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @upgrade_permission_id,'console','console/systemupgrade:executeupgrade','console/systemupgrade','executeupgrade','执行系统升级','route',1,0,30,'admin_web','system_upgrade',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemupgrade:executeupgrade');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @upgrade_permission_id,'console','console/systemupgrade:upload','console/systemupgrade','upload','上传升级包','route',1,0,40,'admin_web','system_upgrade',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemupgrade:upload');
INSERT INTO `fun_permission` (`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @upgrade_permission_id,'console','console/systemupgrade:restore','console/systemupgrade','restore','恢复系统版本','route',1,0,50,'admin_web','system_upgrade',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemupgrade:restore');

INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`module`,`name`,`href`,`query`,`target`,`icon`,`status`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT COALESCE(@system_menu_id,0),@upgrade_permission_id,'console','系统升级','upgrade','component=system/upgrade/index&name=SystemUpgrade&type=C&permission=system:upgrade:list','_self','i-ep-upload-filled',1,350,'admin_web','system_upgrade',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='system_upgrade');
