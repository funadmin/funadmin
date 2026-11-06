-- 退役早期 Webman 四合一原型入口；保留记录及授权历史，不按菜单名称批量处理。
UPDATE `fun_admin_menu`
SET `status` = 0, `deleted_at` = COALESCE(`deleted_at`, NOW()), `updated_at` = NOW()
WHERE `app_name` = 'admin'
  AND `href` = '/admin/management'
  AND LOCATE('&name=WebmanManagement&', CONCAT('&', `query`, '&')) > 0
  AND (
    LOCATE('&component=webman%2Fmanagement&', CONCAT('&', `query`, '&')) > 0
    OR LOCATE('&component=webman/management&', CONCAT('&', `query`, '&')) > 0
  );

UPDATE `fun_permission` AS p
SET p.`status` = 0, p.`deleted_at` = COALESCE(p.`deleted_at`, NOW()), p.`updated_at` = NOW()
WHERE p.`app_name` = 'admin'
  AND p.`code` = 'system:management:view'
  AND p.`obj` = 'admin/system.management'
  AND p.`act` = 'index'
  AND EXISTS (
    SELECT 1 FROM `fun_admin_menu` AS retired
    WHERE retired.`permission_id` = p.`id`
      AND retired.`app_name` = 'admin'
      AND retired.`href` = '/admin/management'
      AND LOCATE('&name=WebmanManagement&', CONCAT('&', retired.`query`, '&')) > 0
      AND (
        LOCATE('&component=webman%2Fmanagement&', CONCAT('&', retired.`query`, '&')) > 0
        OR LOCATE('&component=webman/management&', CONCAT('&', retired.`query`, '&')) > 0
      )
      AND retired.`status` = 0 AND retired.`deleted_at` IS NOT NULL
  )
  AND NOT EXISTS (
    SELECT 1 FROM `fun_admin_menu` AS active_menu
    WHERE active_menu.`permission_id` = p.`id`
      AND active_menu.`status` = 1 AND active_menu.`deleted_at` IS NULL
  );
