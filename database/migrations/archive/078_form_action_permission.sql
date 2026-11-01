-- 078 表单生产动作执行权限；与已存在的 077 业务开发中心迁移隔离。
SET @form_data_group_id = (
  SELECT `id`
  FROM `fun_permission`
  WHERE `source_type` = 'admin_web'
    AND `source_name` = 'form_data'
    AND `resource_type` = 'group'
  ORDER BY `id`
  LIMIT 1
);

INSERT IGNORE INTO `fun_permission` (
  `pid`, `app_name`, `code`, `obj`, `act`, `name`, `resource_type`, `status`, `is_public`,
  `sort`, `source_type`, `source_name`, `created_at`, `updated_at`, `sort_order`, `deleted_at`
)
SELECT @form_data_group_id,
       'console',
       'console/form.data:action',
       'console/form.data',
       'action',
       CONVERT(X'E689A7E8A18CE8A1A8E58D95E58AA8E4BD9C' USING utf8mb4),
       'route', 1, 0, 43, 'admin_web', 'form_data', NOW(), NOW(), 43, NULL
WHERE @form_data_group_id IS NOT NULL;
