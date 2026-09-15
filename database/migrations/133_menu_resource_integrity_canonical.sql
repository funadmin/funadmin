-- 133 全量收敛 Admin Web 页面菜单与权限资源；完整性失败等价于 SIGNAL SQLSTATE。
-- permission_id 是关系事实源，query.permission 是供前端路由消费的同步投影。
UPDATE `fun_admin_menu`
SET `app_name`='admin',`updated_at`=NOW()
WHERE `source_type` IN ('admin_web','generated','plugin')
  AND `app_name`<>'admin';

-- 历史 generated 页面可能绑在 group；按稳定的 list code 改绑真实入口 route。
UPDATE `fun_admin_menu` AS `menu`
JOIN `fun_permission` AS `permission`
  ON `permission`.`code`=CONCAT('generated:',`menu`.`source_name`,':list')
 AND `permission`.`app_name`='admin'
 AND `permission`.`resource_type`='route'
 AND `permission`.`status`=1
 AND `permission`.`deleted_at` IS NULL
LEFT JOIN `fun_permission` AS `bound` ON `bound`.`id`=`menu`.`permission_id`
SET `menu`.`permission_id`=`permission`.`id`,
    `menu`.`updated_at`=NOW()
WHERE `menu`.`status`=1
  AND `menu`.`deleted_at` IS NULL
  AND `menu`.`source_type`='generated'
  AND (`bound`.`id` IS NULL OR `bound`.`resource_type`='group' OR `bound`.`status`<>1 OR `bound`.`deleted_at` IS NOT NULL);

-- permission_id 是关系事实源；无论投影缺失还是仍为旧 code，都统一重建 query.permission。
UPDATE `fun_admin_menu` AS `menu`
JOIN `fun_permission` AS `permission`
  ON `permission`.`id`=`menu`.`permission_id`
 AND `permission`.`app_name`='admin'
 AND `permission`.`resource_type` IN ('route','capability')
 AND `permission`.`status`=1
 AND `permission`.`deleted_at` IS NULL
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
WHERE `menu`.`status`=1
  AND `menu`.`deleted_at` IS NULL
  AND `menu`.`source_type` IN ('admin_web','generated','plugin')
  AND `menu`.`query` NOT LIKE '%type=M%'
  AND (
    `menu`.`query` NOT LIKE '%permission=%'
    OR SUBSTRING_INDEX(SUBSTRING_INDEX(`menu`.`query`,'permission=',-1),'&',1)<>`permission`.`code`
  );

-- 最终完整性门禁：任何启用页面仍绑定零值、孤儿、group、禁用/删除权限或双口径不一致时，迁移必须失败。
CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_133_menu_integrity_assertion` (
  `invalid_count` int unsigned NOT NULL,
  CONSTRAINT `chk_133_menu_integrity` CHECK (`invalid_count`=0)
) ENGINE=InnoDB;
DELETE FROM `tmp_133_menu_integrity_assertion`;
INSERT INTO `tmp_133_menu_integrity_assertion` (`invalid_count`)
SELECT COUNT(*)
FROM `fun_admin_menu` AS `menu`
LEFT JOIN `fun_permission` AS `permission` ON `permission`.`id`=`menu`.`permission_id`
WHERE `menu`.`status`=1
  AND `menu`.`deleted_at` IS NULL
  AND `menu`.`source_type` IN ('admin_web','generated','plugin')
  AND `menu`.`query` NOT LIKE '%type=M%'
  AND (
    `menu`.`app_name`<>'admin'
    OR `permission`.`id` IS NULL
    OR `permission`.`app_name`<>'admin'
    OR `permission`.`resource_type` NOT IN ('route','capability')
    OR `permission`.`status`<>1
    OR `permission`.`deleted_at` IS NOT NULL
    OR `menu`.`query` NOT LIKE '%permission=%'
    OR SUBSTRING_INDEX(SUBSTRING_INDEX(`menu`.`query`,'permission=',-1),'&',1)<>`permission`.`code`
  );