-- 130 将当前 Admin controller 承载的 admin_web route 统一到运行时 admin 资源；forward-only、幂等。
-- 显式白名单避免迁移 development、identity、form 等独立 capability；权限组保持原状。
CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_130_admin_route_map` (
  `source_name` varchar(100) NOT NULL,
  `legacy_obj` varchar(190) NOT NULL,
  `canonical_obj` varchar(190) NOT NULL,
  `menu_action` varchar(100) NULL,
  PRIMARY KEY (`source_name`,`legacy_obj`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELETE FROM `tmp_130_admin_route_map`;

INSERT INTO `tmp_130_admin_route_map` (`source_name`,`legacy_obj`,`canonical_obj`,`menu_action`) VALUES
('menu','backend/systemmenu','admin/systemmenu','tree'),
('menu','console/systemmenu','admin/systemmenu','tree'),
('user','backend/systemadmin','admin/systemadmin','index'),
('user','console/systemadmin','admin/systemadmin','index'),
('role','backend/systemrole','admin/systemrole','index'),
('role','console/systemrole','admin/systemrole','index'),
('department','backend/systemdepartment','admin/systemdepartment','tree'),
('department','console/systemdepartment','admin/systemdepartment','tree'),
('dictionary','backend/systemdict','admin/systemdict','types'),
('dictionary','console/systemdict','admin/systemdict','types'),
('config','backend/systemconfig','admin/systemconfig','index'),
('config','console/systemconfig','admin/systemconfig','index'),
('config_group','backend/systemconfig','admin/systemconfig',NULL),
('config_group','console/systemconfig','admin/systemconfig',NULL),
('attachment','backend/systemattachment','admin/systemattachment','index'),
('attachment','console/systemattachment','admin/systemattachment','index'),
('attachment','backend/systemstorage','admin/systemstorage',NULL),
('attachment','console/systemstorage','admin/systemstorage',NULL),
('attachment_group','backend/systemattachmentgroup','admin/systemattachmentgroup','tree'),
('attachment_group','console/systemattachmentgroup','admin/systemattachmentgroup','tree'),
('member','backend/systemmember','admin/systemmember','index'),
('member','console/systemmember','admin/systemmember','index'),
('member_level','backend/systemmemberlevel','admin/systemmemberlevel','index'),
('member_level','console/systemmemberlevel','admin/systemmemberlevel','index'),
('member_group','backend/systemmembergroup','admin/systemmembergroup','index'),
('member_group','console/systemmembergroup','admin/systemmembergroup','index'),
('language','backend/systemlanguage','admin/systemlanguage','index'),
('language','console/systemlanguage','admin/systemlanguage','index'),
('permission','backend/systempermission','admin/systempermission','tree'),
('permission','console/systempermission','admin/systempermission','tree'),
('operation_log','backend/systemoperationlog','admin/systemoperationlog','index'),
('operation_log','console/systemoperationlog','admin/systemoperationlog','index'),
('blacklist','backend/systemblacklist','admin/systemblacklist','index'),
('blacklist','console/systemblacklist','admin/systemblacklist','index'),
('upload','backend/adminupload','admin/adminupload','upload'),
('upload','console/adminupload','admin/adminupload','upload'),
('profile','backend/adminprofile','admin/adminprofile','index'),
('profile','console/adminprofile','admin/adminprofile','index'),
('plugin_center','backend/systemplugin','admin/systemplugin','installed'),
('plugin_center','console/systemplugin','admin/systemplugin','installed'),
('system_upgrade','backend/systemupgrade','admin/systemupgrade','status'),
('system_upgrade','console/systemupgrade','admin/systemupgrade','status');

CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_130_legacy_permission` (
  `legacy_id` bigint unsigned NOT NULL,
  `pid` bigint unsigned NOT NULL,
  `act` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` tinyint NOT NULL,
  `is_public` tinyint NOT NULL,
  `source_type` varchar(50) NOT NULL,
  `source_name` varchar(100) NOT NULL,
  `sort_order` int NOT NULL,
  `deleted_at` datetime NULL,
  `legacy_obj` varchar(190) NOT NULL,
  `canonical_obj` varchar(190) NOT NULL,
  `canonical_code` varchar(255) NOT NULL,
  PRIMARY KEY (`legacy_id`),
  KEY `idx_130_canonical_code` (`canonical_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELETE FROM `tmp_130_legacy_permission`;
INSERT INTO `tmp_130_legacy_permission`
SELECT `legacy`.`id`,`legacy`.`pid`,`legacy`.`act`,`legacy`.`name`,`legacy`.`status`,`legacy`.`is_public`,
       `legacy`.`source_type`,`legacy`.`source_name`,`legacy`.`sort_order`,`legacy`.`deleted_at`,
       `mapping`.`legacy_obj`,`mapping`.`canonical_obj`,CONCAT(`mapping`.`canonical_obj`,':',`legacy`.`act`)
FROM `fun_permission` AS `legacy`
JOIN `tmp_130_admin_route_map` AS `mapping`
  ON `mapping`.`source_name`=`legacy`.`source_name` AND `mapping`.`legacy_obj`=`legacy`.`obj`
WHERE `legacy`.`source_type`='admin_web' AND `legacy`.`resource_type`='route';

-- 先建立缺失 canonical；目标已存在或多条旧记录时由唯一 code 收敛到同一行。
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT `legacy`.`pid`,'admin',`legacy`.`canonical_code`,`legacy`.`canonical_obj`,`legacy`.`act`,`legacy`.`name`,'route',
       `legacy`.`status`,`legacy`.`is_public`,`legacy`.`source_type`,`legacy`.`source_name`,NOW(),NOW(),`legacy`.`sort_order`,`legacy`.`deleted_at`
FROM `tmp_130_legacy_permission` AS `legacy`
ORDER BY `legacy`.`legacy_id`;

CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_130_canonical_permission` (
  `legacy_id` bigint unsigned NOT NULL,
  `canonical_id` bigint unsigned NOT NULL,
  `legacy_obj` varchar(190) NOT NULL,
  `canonical_obj` varchar(190) NOT NULL,
  `act` varchar(100) NOT NULL,
  PRIMARY KEY (`legacy_id`),
  KEY `idx_130_canonical_id` (`canonical_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELETE FROM `tmp_130_canonical_permission`;
INSERT INTO `tmp_130_canonical_permission`
SELECT `legacy`.`legacy_id`,`canonical`.`id`,`legacy`.`legacy_obj`,`legacy`.`canonical_obj`,`legacy`.`act`
FROM `tmp_130_legacy_permission` AS `legacy`
JOIN `fun_permission` AS `canonical` ON `canonical`.`code`=`legacy`.`canonical_code`;

UPDATE `fun_permission` AS `canonical`
JOIN (SELECT DISTINCT `canonical_id` FROM `tmp_130_canonical_permission`) AS `selected` ON `selected`.`canonical_id`=`canonical`.`id`
SET `canonical`.`app_name`='admin',`canonical`.`obj`=SUBSTRING_INDEX(`canonical`.`code`,':',1),`canonical`.`act`=SUBSTRING_INDEX(`canonical`.`code`,':',-1),`canonical`.`updated_at`=NOW();

-- 所有菜单和权限子节点先改绑 canonical，覆盖一目标已存在及多旧行并存场景。
UPDATE `fun_admin_menu` AS `menu`
JOIN `tmp_130_canonical_permission` AS `resolved` ON `resolved`.`legacy_id`=`menu`.`permission_id`
JOIN `fun_permission` AS `canonical` ON `canonical`.`id`=`resolved`.`canonical_id`
SET `menu`.`permission_id`=`canonical`.`id`,`menu`.`app_name`='admin',`menu`.`updated_at`=NOW();

-- 页面菜单可能仍绑定 capability group；按 source_name 显式绑定对应的运行时入口 route。
UPDATE `fun_admin_menu` AS `menu`
JOIN `tmp_130_admin_route_map` AS `mapping`
  ON `mapping`.`source_name`=`menu`.`source_name` AND `mapping`.`menu_action` IS NOT NULL
JOIN `fun_permission` AS `canonical`
  ON `canonical`.`code`=CONCAT(`mapping`.`canonical_obj`,':',`mapping`.`menu_action`)
SET `menu`.`permission_id`=`canonical`.`id`,`menu`.`app_name`='admin',`menu`.`updated_at`=NOW()
WHERE `menu`.`source_type`='admin_web';

UPDATE `fun_permission` AS `child`
JOIN `tmp_130_canonical_permission` AS `resolved` ON `resolved`.`legacy_id`=`child`.`pid`
JOIN `fun_permission` AS `canonical` ON `canonical`.`id`=`resolved`.`canonical_id`
SET `child`.`pid`=`canonical`.`id`,`child`.`updated_at`=NOW();

-- 每条旧 p 只复制为相同 subject/domain/action 的 canonical p，不产生额外动作授权。
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT 'p',`policy`.`v0`,`policy`.`v1`,`resolved`.`canonical_obj`,`policy`.`v3`,'','',
       SHA2(CONCAT_WS(CHAR(31),'p',`policy`.`v0`,`policy`.`v1`,`resolved`.`canonical_obj`,`policy`.`v3`),256)
FROM `fun_casbin_rule` AS `policy`
JOIN `tmp_130_canonical_permission` AS `resolved`
  ON `resolved`.`legacy_obj`=`policy`.`v2` AND `resolved`.`act`=`policy`.`v3`
WHERE `policy`.`ptype`='p';

DELETE `policy` FROM `fun_casbin_rule` AS `policy`
WHERE `policy`.`ptype`='p' AND EXISTS (
  SELECT 1 FROM `tmp_130_canonical_permission` AS `resolved`
  WHERE `resolved`.`legacy_obj`=`policy`.`v2` AND `resolved`.`act`=`policy`.`v3`
);

UPDATE `fun_casbin_rule` AS `policy`
JOIN (SELECT DISTINCT `canonical_obj`,`act` FROM `tmp_130_canonical_permission`) AS `resolved`
  ON `resolved`.`canonical_obj`=`policy`.`v2` AND `resolved`.`act`=`policy`.`v3`
SET `policy`.`rule_hash`=SHA2(CONCAT_WS(CHAR(31),`policy`.`ptype`,`policy`.`v0`,`policy`.`v1`,`policy`.`v2`,`policy`.`v3`),256)
WHERE `policy`.`ptype`='p';

-- 仅删除已无菜单、无子节点引用的旧 route；group 与其他 capability 均保留。
DELETE `legacy` FROM `fun_permission` AS `legacy`
JOIN `tmp_130_canonical_permission` AS `resolved` ON `resolved`.`legacy_id`=`legacy`.`id`
LEFT JOIN `fun_admin_menu` AS `menu` ON `menu`.`permission_id`=`legacy`.`id`
LEFT JOIN `fun_permission` AS `child` ON `child`.`pid`=`legacy`.`id`
WHERE `menu`.`id` IS NULL AND `child`.`id` IS NULL;

-- 临时表由连接生命周期自动释放，不执行破坏性清理语句。
