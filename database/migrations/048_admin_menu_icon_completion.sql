-- 为全部 Admin Web 菜单补齐可渲染图标；目录使用文件夹，页面使用文档图标。
UPDATE `fun_admin_menu` AS `menu`
LEFT JOIN (
    SELECT DISTINCT `pid`
    FROM `fun_admin_menu`
    WHERE `source_type` = 'admin_web'
      AND `status` = 1
) AS `parents` ON `parents`.`pid` = `menu`.`id`
SET `menu`.`icon` = CASE
    WHEN `parents`.`pid` IS NOT NULL THEN 'i-ep-folder'
    ELSE 'i-ep-document'
END,
`menu`.`updated_at` = NOW()
WHERE `menu`.`source_type` = 'admin_web'
  AND COALESCE(NULLIF(TRIM(`menu`.`icon`), ''), '') = '';
