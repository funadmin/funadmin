-- 134 统一所有权限资源对外 code 为 admin/ 前缀；obj/act 与 Casbin p 规则保持不变。
CREATE TEMPORARY TABLE `tmp_134_permission_code_map` (
  `permission_id` bigint unsigned NOT NULL,
  `old_code` varchar(255) NOT NULL,
  `new_code` varchar(255) NOT NULL,
  PRIMARY KEY (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tmp_134_permission_code_map` (`permission_id`,`old_code`,`new_code`)
SELECT `id`,`code`,CONCAT('admin/', `code`)
FROM `fun_permission`
WHERE `code` IS NOT NULL
  AND `code`<>''
  AND `code` NOT LIKE 'admin/%';

UPDATE `fun_permission` AS `permission`
JOIN `tmp_134_permission_code_map` AS `map` ON `map`.`permission_id`=`permission`.`id`
SET `permission`.`code`=`map`.`new_code`,
    `permission`.`updated_at`=NOW();

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
WHERE `menu`.`permission_id`=`permission`.`id`
  AND `permission`.`code` LIKE 'admin/%';
