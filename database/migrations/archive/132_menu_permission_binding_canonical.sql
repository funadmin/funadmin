-- 132 统一启用页面菜单的 query.permission 与 permission_id，消除双重权限口径。
UPDATE `fun_admin_menu` AS `menu`
JOIN `fun_permission` AS `permission`
  ON `permission`.`code`=SUBSTRING_INDEX(
    SUBSTRING_INDEX(`menu`.`query`,'permission=',-1),
    '&',
    1
  )
 AND `permission`.`resource_type` IN ('route','capability')
 AND `permission`.`status`=1
 AND `permission`.`deleted_at` IS NULL
SET `menu`.`permission_id`=`permission`.`id`,
    `menu`.`app_name`='admin',
    `menu`.`updated_at`=NOW()
WHERE `menu`.`status`=1
  AND `menu`.`deleted_at` IS NULL
  AND `menu`.`source_type` IN ('admin_web','generated','plugin')
  AND `menu`.`query` LIKE '%permission=%'
  AND `menu`.`permission_id`<>`permission`.`id`;
