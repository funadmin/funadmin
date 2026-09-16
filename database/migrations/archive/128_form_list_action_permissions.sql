-- 列表动作动态入口权限。仅供审阅，不自动执行；不赋予任何角色或公开访问。
-- 正式模块节点由 CrudDefinition 及其受管权限迁移按模块生成，不复用这些节点。
SET @form_data_group_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'form_data'
    AND `resource_type` = 'group' AND `deleted_at` IS NULL
  ORDER BY `id` LIMIT 1
);

INSERT IGNORE INTO `fun_permission` (
  `pid`, `app_name`, `code`, `obj`, `act`, `name`, `resource_type`, `status`, `is_public`,
  `sort`, `source_type`, `source_name`, `created_at`, `updated_at`, `sort_order`, `deleted_at`
)
SELECT @form_data_group_id, 'console', 'console/form.data:listactions', 'console/form.data',
       'listactions', '读取列表动作目录', 'route', 1, 0, 44, 'admin_web', 'form_data', NOW(), NOW(), 44, NULL
WHERE @form_data_group_id IS NOT NULL;

INSERT IGNORE INTO `fun_permission` (
  `pid`, `app_name`, `code`, `obj`, `act`, `name`, `resource_type`, `status`, `is_public`,
  `sort`, `source_type`, `source_name`, `created_at`, `updated_at`, `sort_order`, `deleted_at`
)
SELECT @form_data_group_id, 'console', 'console/form.data:listaction', 'console/form.data',
       'listaction', '执行列表动作', 'route', 1, 0, 45, 'admin_web', 'form_data', NOW(), NOW(), 45, NULL
WHERE @form_data_group_id IS NOT NULL;
