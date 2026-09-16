-- 修复 051 之后的权限树孤儿、乱码和 generated 资源命名。
-- 中文统一使用 UTF-8 十六进制字面量，避免受客户端连接字符集影响。

-- 恢复稳定的 Console 根目录（幂等）。
INSERT INTO `fun_permission` (
  `pid`, `module`, `code`, `obj`, `act`, `name`, `resource_type`, `status`, `is_public`,
  `sort`, `source_type`, `source_name`, `created_at`, `updated_at`, `sort_order`, `deleted_at`
)
SELECT
  0, 'console', NULL, '', '', CONVERT(X'436F6E736F6C6520E7B3BBE7BB9F' USING utf8mb4),
  'group', 1, 0, 0, 'admin_web', 'console_root', NOW(), NOW(), 0, NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'console_root' AND `resource_type` = 'group'
);

-- 将 051 删除旧根后留下的三个目录挂回 Console 根。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type` = 'admin_web'
 AND parent.`source_name` = 'console_root'
 AND parent.`resource_type` = 'group'
SET child.`pid` = parent.`id`, child.`updated_at` = NOW()
WHERE child.`source_type` = 'admin_web'
  AND child.`source_name` IN ('dictionary', 'system', 'profile')
  AND child.`resource_type` = 'group';

-- 上传动作属于附件管理，不应悬挂在已删除的旧根下。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type` = 'admin_web'
 AND parent.`source_name` = 'attachment'
 AND parent.`resource_type` = 'group'
SET child.`pid` = parent.`id`, child.`updated_at` = NOW()
WHERE child.`code` = 'console/adminupload:upload';

-- 修复插件权限乱码与动作语义。
UPDATE `fun_permission`
SET `name` = CONVERT(X'E6B885E999A4E68F92E4BBB6E695B0E68DAE' USING utf8mb4), `updated_at` = NOW()
WHERE `code` = 'console/systemplugin:purge';

UPDATE `fun_permission`
SET `name` = CONVERT(X'E69BB4E696B0E68F92E4BBB6' USING utf8mb4), `updated_at` = NOW()
WHERE `code` = 'system:plugin:update';

-- 修复菜单乱码和英文残留，使用来源标记与绑定权限定位。
UPDATE `fun_admin_menu` AS menu
JOIN `fun_permission` AS permission ON permission.`id` = menu.`permission_id`
SET menu.`name` = CONVERT(X'E5AD97E585B8E7AEA1E79086' USING utf8mb4), menu.`updated_at` = NOW()
WHERE menu.`source_type` = 'admin_web'
  AND menu.`source_name` = 'dictionary'
  AND permission.`source_name` = 'dictionary';

UPDATE `fun_admin_menu` AS menu
JOIN `fun_permission` AS permission ON permission.`id` = menu.`permission_id`
SET menu.`name` = CONVERT(X'E7AEA1E79086E59198E7AEA1E79086' USING utf8mb4), menu.`updated_at` = NOW()
WHERE menu.`source_type` = 'admin_web'
  AND menu.`source_name` = 'user'
  AND permission.`code` = 'console/systemadmin:index';

-- generated blacklist 组与菜单统一为中文业务名。
UPDATE `fun_permission`
SET `name` = CONVERT(X'E9BB91E5908DE58D95EFBC884352554420E7949FE68890EFBC89' USING utf8mb4), `updated_at` = NOW()
WHERE `source_type` = 'generated'
  AND `source_name` = 'blacklist'
  AND `resource_type` = 'group';

UPDATE `fun_admin_menu`
SET `name` = CONVERT(X'E9BB91E5908DE58D95EFBC884352554420E7949FE68890EFBC89' USING utf8mb4), `updated_at` = NOW()
WHERE `source_type` = 'generated' AND `source_name` = 'blacklist';

-- generated blacklist 动作名称统一为中文。
UPDATE `fun_permission`
SET `name` = CASE `code`
  WHEN 'generated:blacklist:list' THEN CONVERT(X'E69FA5E79C8BE58897E8A1A8' USING utf8mb4)
  WHEN 'generated:blacklist:detail' THEN CONVERT(X'E69FA5E79C8BE8AFA6E68385' USING utf8mb4)
  WHEN 'generated:blacklist:create' THEN CONVERT(X'E696B0E5A29E' USING utf8mb4)
  WHEN 'generated:blacklist:update' THEN CONVERT(X'E7BC96E8BE91' USING utf8mb4)
  WHEN 'generated:blacklist:status' THEN CONVERT(X'E4BFAEE694B9E78AB6E68081' USING utf8mb4)
  WHEN 'generated:blacklist:delete' THEN CONVERT(X'E588A0E999A4' USING utf8mb4)
  WHEN 'generated:blacklist:restore' THEN CONVERT(X'E681A2E5A48D' USING utf8mb4)
  WHEN 'generated:blacklist:destroy' THEN CONVERT(X'E5BDBBE5BA95E588A0E999A4' USING utf8mb4)
  WHEN 'generated:blacklist:batch-delete' THEN CONVERT(X'E689B9E9878FE588A0E999A4' USING utf8mb4)
  WHEN 'generated:blacklist:batch-restore' THEN CONVERT(X'E689B9E9878FE681A2E5A48D' USING utf8mb4)
  WHEN 'generated:blacklist:batch-destroy' THEN CONVERT(X'E689B9E9878FE5BDBBE5BA95E588A0E999A4' USING utf8mb4)
  WHEN 'generated:blacklist:import' THEN CONVERT(X'E5AFBCE585A5' USING utf8mb4)
  WHEN 'generated:blacklist:export' THEN CONVERT(X'E5AFBCE587BA' USING utf8mb4)
  ELSE `name`
END,
`updated_at` = NOW()
WHERE `source_type` = 'generated'
  AND `source_name` = 'blacklist'
  AND `resource_type` = 'route';
