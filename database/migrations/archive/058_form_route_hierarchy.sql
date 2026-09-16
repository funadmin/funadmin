-- 058 修复表单管理前端动态路由层级。
-- form_list/form_designer 均是 development_tools 下的叶子菜单，href 必须是相对路径。

SET @development_menu_id = (
  SELECT `id`
  FROM `fun_admin_menu`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'development_tools'
  LIMIT 1
);

UPDATE `fun_admin_menu`
SET `pid` = @development_menu_id,
    `href` = 'form/list',
    `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'form_list';

UPDATE `fun_admin_menu`
SET `pid` = @development_menu_id,
    `href` = 'form/designer',
    `updated_at` = NOW()
WHERE `source_type` = 'admin_web'
  AND `source_name` = 'form_designer';
