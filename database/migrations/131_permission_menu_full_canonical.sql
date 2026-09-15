-- 131 全量统一菜单与权限：HTTP route 对齐 admin controller，capability 保留稳定业务 code。
-- 覆盖 development.business、development.ai、form.data、form.designer、form.full-publish、identity.、generated. 与 plugin。
ALTER TABLE `fun_permission`
  MODIFY COLUMN `resource_type` enum('group','route','capability') NOT NULL DEFAULT 'route';

CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_131_resource_map` (
  `permission_id` bigint unsigned NOT NULL,
  `old_obj` varchar(190) NOT NULL,
  `old_act` varchar(100) NOT NULL,
  `new_obj` varchar(190) NOT NULL,
  `new_act` varchar(100) NOT NULL,
  `new_code` varchar(255) NOT NULL,
  `new_type` varchar(20) NOT NULL,
  PRIMARY KEY (`permission_id`),
  KEY `idx_131_old_resource` (`old_obj`,`old_act`),
  KEY `idx_131_new_code` (`new_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELETE FROM `tmp_131_resource_map`;

-- console/backend 是旧 HTTP 应用名；运行时 PermissionResource 的事实源始终为 admin/<controller>:<action>。
INSERT INTO `tmp_131_resource_map`
  (`permission_id`,`old_obj`,`old_act`,`new_obj`,`new_act`,`new_code`,`new_type`)
SELECT `id`,`obj`,`act`,
       CONCAT('admin/',SUBSTRING(`obj`,LOCATE('/',`obj`)+1)),
       `act`,
       CONCAT('admin/',SUBSTRING(`obj`,LOCATE('/',`obj`)+1),':',`act`),
       'route'
FROM `fun_permission`
WHERE `resource_type`='route'
  AND `source_type`='admin_web'
  AND (`obj` LIKE 'console/%' OR `obj` LIKE 'backend/%');

-- generated code 是稳定 UI capability 标识，Casbin obj/act 则必须指向真实生成 controller。
INSERT INTO `tmp_131_resource_map`
  (`permission_id`,`old_obj`,`old_act`,`new_obj`,`new_act`,`new_code`,`new_type`)
SELECT `id`,`obj`,`act`,
       CONCAT('admin/generated.',REPLACE(REPLACE(LOWER(`source_name`),'-',''),'_',''),'controller'),
       CASE SUBSTRING_INDEX(`code`,':',-1)
         WHEN 'list' THEN 'index'
         WHEN 'delete' THEN 'remove'
         WHEN 'batch-delete' THEN 'recycle'
         WHEN 'batch-restore' THEN 'restoremany'
         WHEN 'batch-destroy' THEN 'destroymany'
         WHEN 'list-actions' THEN 'listactions'
         WHEN 'list-action' THEN 'listaction'
         ELSE REPLACE(SUBSTRING_INDEX(`code`,':',-1),'-','')
       END,
       `code`,'route'
FROM `fun_permission`
WHERE `source_type`='generated'
  AND `resource_type`='route'
  AND `code` LIKE 'generated:%';

-- 非 HTTP code 是一等 capability；code 不变，obj/act 由 code 确定性恢复，不能再伪装成 route。
INSERT INTO `tmp_131_resource_map`
  (`permission_id`,`old_obj`,`old_act`,`new_obj`,`new_act`,`new_code`,`new_type`)
SELECT `id`,`obj`,`act`,
       REPLACE(SUBSTRING_INDEX(`code`,':',2),':','/'),
       SUBSTRING_INDEX(`code`,':',-1),
       `code`,'capability'
FROM `fun_permission`
WHERE `resource_type`<>'group'
  AND `source_type`<>'generated'
  AND `code` IS NOT NULL
  AND `code`<>''
  AND `code` NOT LIKE 'admin/%'
  AND `code` NOT LIKE 'console/%'
  AND `code` NOT LIKE 'backend/%'
ON DUPLICATE KEY UPDATE
  `new_obj`=VALUES(`new_obj`),`new_act`=VALUES(`new_act`),
  `new_code`=VALUES(`new_code`),`new_type`=VALUES(`new_type`);

-- 为旧 route 建立或复用 canonical permission；capability/generated 在原记录上规范化。
INSERT IGNORE INTO `fun_permission`
  (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT `legacy`.`pid`,'admin',`mapping`.`new_code`,`mapping`.`new_obj`,`mapping`.`new_act`,`legacy`.`name`,'route',
       `legacy`.`status`,`legacy`.`is_public`,`legacy`.`source_type`,`legacy`.`source_name`,NOW(),NOW(),`legacy`.`sort_order`,`legacy`.`deleted_at`
FROM `tmp_131_resource_map` AS `mapping`
JOIN `fun_permission` AS `legacy` ON `legacy`.`id`=`mapping`.`permission_id`
WHERE `mapping`.`new_type`='route'
  AND `legacy`.`source_type`='admin_web'
  AND (`mapping`.`old_obj` LIKE 'console/%' OR `mapping`.`old_obj` LIKE 'backend/%');

CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_131_resolved` (
  `legacy_id` bigint unsigned NOT NULL,
  `canonical_id` bigint unsigned NOT NULL,
  `old_obj` varchar(190) NOT NULL,
  `old_act` varchar(100) NOT NULL,
  `new_obj` varchar(190) NOT NULL,
  `new_act` varchar(100) NOT NULL,
  PRIMARY KEY (`legacy_id`),
  KEY `idx_131_canonical_id` (`canonical_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
DELETE FROM `tmp_131_resolved`;
INSERT INTO `tmp_131_resolved`
SELECT `mapping`.`permission_id`,`canonical`.`id`,`mapping`.`old_obj`,`mapping`.`old_act`,`mapping`.`new_obj`,`mapping`.`new_act`
FROM `tmp_131_resource_map` AS `mapping`
JOIN `fun_permission` AS `legacy` ON `legacy`.`id`=`mapping`.`permission_id`
JOIN `fun_permission` AS `canonical` ON `canonical`.`code`=`mapping`.`new_code`
WHERE `legacy`.`source_type`='admin_web'
  AND (`mapping`.`old_obj` LIKE 'console/%' OR `mapping`.`old_obj` LIKE 'backend/%');

UPDATE `fun_admin_menu` AS `menu`
JOIN `tmp_131_resolved` AS `resolved` ON `resolved`.`legacy_id`=`menu`.`permission_id`
SET `menu`.`permission_id`=`resolved`.`canonical_id`,`menu`.`app_name`='admin',`menu`.`updated_at`=NOW();
UPDATE `fun_permission` AS `child`
JOIN `tmp_131_resolved` AS `resolved` ON `resolved`.`legacy_id`=`child`.`pid`
SET `child`.`pid`=`resolved`.`canonical_id`,`child`.`updated_at`=NOW();

-- 等价迁移所有变化资源的 Casbin p；ptype=g 的角色关系完全不参与本迁移。
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT 'p',`policy`.`v0`,`policy`.`v1`,`mapping`.`new_obj`,`mapping`.`new_act`,'','',
       SHA2(CONCAT_WS(CHAR(31),'p',`policy`.`v0`,`policy`.`v1`,`mapping`.`new_obj`,`mapping`.`new_act`),256)
FROM `fun_casbin_rule` AS `policy`
JOIN `tmp_131_resource_map` AS `mapping`
  ON `mapping`.`old_obj`=`policy`.`v2` AND `mapping`.`old_act`=`policy`.`v3`
WHERE `policy`.`ptype`='p';
DELETE `policy`
FROM `fun_casbin_rule` AS `policy`
JOIN `tmp_131_resource_map` AS `mapping`
  ON `mapping`.`old_obj`=`policy`.`v2` AND `mapping`.`old_act`=`policy`.`v3`
WHERE `policy`.`ptype`='p'
  AND (`mapping`.`old_obj`<>`mapping`.`new_obj` OR `mapping`.`old_act`<>`mapping`.`new_act`);

-- capability 与 generated 使用稳定 code 原位更新；避免创建重复权限 ID。
UPDATE `fun_permission` AS `permission`
JOIN `tmp_131_resource_map` AS `mapping` ON `mapping`.`permission_id`=`permission`.`id`
LEFT JOIN `tmp_131_resolved` AS `resolved` ON `resolved`.`legacy_id`=`permission`.`id`
SET `permission`.`app_name`='admin',`permission`.`obj`=`mapping`.`new_obj`,`permission`.`act`=`mapping`.`new_act`,
    `permission`.`resource_type`=`mapping`.`new_type`,`permission`.`updated_at`=NOW()
WHERE `resolved`.`legacy_id` IS NULL;

DELETE `legacy`
FROM `fun_permission` AS `legacy`
JOIN `tmp_131_resolved` AS `resolved` ON `resolved`.`legacy_id`=`legacy`.`id`
LEFT JOIN `fun_admin_menu` AS `menu` ON `menu`.`permission_id`=`legacy`.`id`
LEFT JOIN `fun_permission` AS `child` ON `child`.`pid`=`legacy`.`id`
WHERE `menu`.`id` IS NULL AND `child`.`id` IS NULL;

-- 菜单和权限树均属于 Admin Web；插件、generated 菜单也必须由 /admin/auth/menus 下发。
UPDATE `fun_permission` SET `app_name`='admin',`updated_at`=NOW()
WHERE `source_type` IN ('admin_web','generated','plugin');
UPDATE `fun_permission` SET `app_name`='admin',`updated_at`=NOW() WHERE `source_type`='plugin';
UPDATE `fun_admin_menu` SET `app_name`='admin',`updated_at`=NOW()
WHERE `source_type` IN ('admin_web','generated','plugin');
UPDATE `fun_admin_menu` SET `app_name`='admin',`updated_at`=NOW() WHERE `source_type`='plugin';

-- 菜单过滤绑定到页面声明的稳定 capability/route，不允许继续绑定 group。
UPDATE `fun_admin_menu` AS `menu`
JOIN `fun_permission` AS `permission` ON `permission`.`code`='development:crud:list'
SET `menu`.`permission_id`=`permission`.`id`,`menu`.`updated_at`=NOW()
WHERE `menu`.`source_type`='admin_web' AND `menu`.`source_name`='development_crud';
UPDATE `fun_admin_menu` AS `menu`
JOIN `fun_permission` AS `permission` ON `permission`.`code`='admin/form.designer:index'
SET `menu`.`permission_id`=`permission`.`id`,
    `menu`.`query`='component=form/list&name=FormList&type=C&permission=admin/form.designer:index',
    `menu`.`updated_at`=NOW()
WHERE `menu`.`source_type`='admin_web' AND `menu`.`source_name`='form_list';
UPDATE `fun_admin_menu` AS `menu`
JOIN `fun_permission` AS `permission` ON `permission`.`code`='admin/form.designer:index'
SET `menu`.`permission_id`=`permission`.`id`,
    `menu`.`query`='component=form/designer/index&name=FormDesigner&type=C&permission=admin/form.designer:index&hidden=1',
    `menu`.`updated_at`=NOW()
WHERE `menu`.`source_type`='admin_web' AND `menu`.`source_name`='form_designer';