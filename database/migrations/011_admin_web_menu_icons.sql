-- 补齐 Admin Web 菜单图标，并替换前端未生成样式的图标名称。

UPDATE `fun_admin_menu`
SET `icon` = CASE `source_name`
    WHEN 'system_management' THEN 'i-ep-setting'
    WHEN 'user_management' THEN 'i-ep-user'
    WHEN 'user' THEN 'i-ep-user'
    WHEN 'role' THEN 'i-ep-avatar'
    WHEN 'department' THEN 'i-ep-office-building'
    WHEN 'menu' THEN 'i-ep-menu'
    WHEN 'permission' THEN 'i-ep-lock'
    WHEN 'operation_log' THEN 'i-ep-document'
    WHEN 'blacklist' THEN 'i-ep-warning'
    WHEN 'dictionary' THEN 'i-ep-collection'
    WHEN 'language' THEN 'i-ep-connection'
    WHEN 'member_group' THEN 'i-ep-user-filled'
    WHEN 'member_level' THEN 'i-ep-histogram'
    WHEN 'member' THEN 'i-ep-user'
    WHEN 'attachment' THEN 'i-ep-picture-filled'
    WHEN 'config' THEN 'i-ep-setting'
    WHEN 'plugin_center' THEN 'i-ep-grid'
    ELSE 'i-ep-menu'
END,
`update_time` = UNIX_TIMESTAMP()
WHERE `source_type` = 'admin_web'
  AND (
      COALESCE(NULLIF(TRIM(`icon`), ''), '') = ''
      OR `icon` IN ('i-ep-circle-close', 'i-ep-medal', 'i-ep-box')
  );
