-- 按当前 Element Plus 图标集统一插件中心菜单图标；只向前修复，不修改已执行的 011。
UPDATE `fun_admin_menu`
SET `icon` = 'i-ep-grid',
    `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'plugin_center'
  AND COALESCE(NULLIF(TRIM(`icon`), ''), '') <> 'i-ep-grid';
