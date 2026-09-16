-- 134 统一所有权限资源对外 code 为 admin/ 前缀；obj/act 与 Casbin p 规则保持不变。
-- 先建立旧权限到 canonical 权限的映射，再重绑引用，避免旧 code 与 admin/ code 共存时撞唯一索引。
CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_134_permission_code_map` (
  `legacy_id` bigint unsigned NOT NULL,
  `old_code` varchar(255) NOT NULL,
  `new_code` varchar(255) NOT NULL,
  `canonical_id` bigint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`legacy_id`),
  KEY `idx_134_new_code` (`new_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELETE FROM `tmp_134_permission_code_map`;
INSERT INTO `tmp_134_permission_code_map` (`legacy_id`,`old_code`,`new_code`)
SELECT `id`,`code`,CONCAT('admin/', `code`)
FROM `fun_permission`
WHERE `code` IS NOT NULL
  AND `code`<>''
  AND `code` NOT LIKE 'admin/%';

-- 已有 canonical code 时复用其 ID；无冲突记录保持原 ID，只原位更新 code。
UPDATE `tmp_134_permission_code_map` AS `map`
JOIN `fun_permission` AS `canonical` ON `canonical`.`code`=`map`.`new_code`
SET `map`.`canonical_id`=`canonical`.`id`;

UPDATE `fun_permission` AS `permission`
JOIN `tmp_134_permission_code_map` AS `map` ON `map`.`legacy_id`=`permission`.`id`
SET `permission`.`code`=`map`.`new_code`, `permission`.`app_name`='admin', `permission`.`updated_at`=NOW()
WHERE `map`.`canonical_id`=0;

UPDATE `tmp_134_permission_code_map`
SET `canonical_id`=`legacy_id`
WHERE `canonical_id`=0;

UPDATE `fun_admin_menu` AS `menu`
JOIN `tmp_134_permission_code_map` AS `map` ON `map`.`legacy_id`=`menu`.`permission_id`
SET `menu`.`permission_id`=`map`.`canonical_id`, `menu`.`updated_at`=NOW();

UPDATE `fun_permission` AS `child`
JOIN `tmp_134_permission_code_map` AS `map` ON `map`.`legacy_id`=`child`.`pid`
SET `child`.`pid`=`map`.`canonical_id`, `child`.`updated_at`=NOW();

UPDATE `fun_permission_field` AS `field`
JOIN `tmp_134_permission_code_map` AS `map` ON `map`.`legacy_id`=`field`.`permission_id`
SET `field`.`permission_id`=`map`.`canonical_id`, `field`.`updated_at`=NOW();

DELETE `legacy`
FROM `fun_permission` AS `legacy`
JOIN `tmp_134_permission_code_map` AS `map` ON `map`.`legacy_id`=`legacy`.`id`
LEFT JOIN `fun_admin_menu` AS `menu` ON `menu`.`permission_id`=`legacy`.`id`
LEFT JOIN `fun_permission` AS `child` ON `child`.`pid`=`legacy`.`id`
LEFT JOIN `fun_permission_field` AS `field` ON `field`.`permission_id`=`legacy`.`id`
WHERE `map`.`canonical_id`<>`legacy`.`id`
  AND `menu`.`id` IS NULL
  AND `child`.`id` IS NULL
  AND `field`.`id` IS NULL;

UPDATE `fun_permission`
SET `app_name`='admin', `updated_at`=NOW()
WHERE `code` LIKE 'admin/%';

UPDATE `fun_admin_menu` AS `menu`
JOIN `fun_permission` AS `permission` ON `permission`.`id`=`menu`.`permission_id`
SET `menu`.`query`=CASE
      WHEN `menu`.`query` NOT LIKE '%permission=%' THEN CONCAT(
        `menu`.`query`,CASE WHEN `menu`.`query`='' THEN '' ELSE '&' END,'permission=',`permission`.`code`
      )
      ELSE CONCAT(
        SUBSTRING_INDEX(`menu`.`query`,'permission=',1),
        'permission=',`permission`.`code`,
        CASE
          WHEN LOCATE('&',SUBSTRING_INDEX(`menu`.`query`,'permission=',-1))>0
          THEN CONCAT('&',SUBSTRING(
            SUBSTRING_INDEX(`menu`.`query`,'permission=',-1),
            LOCATE('&',SUBSTRING_INDEX(`menu`.`query`,'permission=',-1))+1
          ))
          ELSE ''
        END
      )
    END,
    `menu`.`updated_at`=NOW()
WHERE `permission`.`code` LIKE 'admin/%';

CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_134_permission_code_assertion` (
  `invalid_count` int unsigned NOT NULL,
  CONSTRAINT `chk_134_permission_code_admin_prefix` CHECK (`invalid_count`=0)
) ENGINE=InnoDB;
DELETE FROM `tmp_134_permission_code_assertion`;
INSERT INTO `tmp_134_permission_code_assertion` (`invalid_count`)
SELECT COUNT(*) FROM `fun_permission` WHERE `code` IS NOT NULL AND `code`<>'' AND `code` NOT LIKE 'admin/%';
