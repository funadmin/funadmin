-- 059 修正多级控制器的权限资源名。
-- PermissionResource 将 form\Designer / form\Data 规范化为 form.designer / form.data。

UPDATE `fun_permission`
SET `code` = CONCAT('console/form.designer:', `act`),
    `obj` = 'console/form.designer',
    `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'form_management'
  AND `resource_type` = 'route';

UPDATE `fun_permission`
SET `code` = CONCAT('console/form.data:', `act`),
    `obj` = 'console/form.data',
    `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'form_data'
  AND `resource_type` = 'route';
