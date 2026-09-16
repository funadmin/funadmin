-- 057 表单数据（M3 运行态）权限：模块级 console/formdata:* 路由权限。
-- 中文统一使用 UTF-8 十六进制字面量；全部守卫式，可重跑。

-- 权限组：表单数据（挂 Console 根）。
INSERT INTO `fun_permission` (
  `pid`, `app_name`, `code`, `obj`, `act`, `name`, `resource_type`, `status`, `is_public`,
  `sort`, `source_type`, `source_name`, `created_at`, `updated_at`, `sort_order`, `deleted_at`
)
SELECT COALESCE((SELECT `id` FROM `fun_permission` WHERE `source_type` = 'admin_web' AND `source_name` = 'console_root' AND `resource_type` = 'group' LIMIT 1), 0),
       'console', NULL, '', '', CONVERT(X'E8A1A8E58D95E695B0E68DAE' USING utf8mb4),
       'group', 1, 0, 71, 'admin_web', 'form_data', NOW(), NOW(), 71, NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'form_data' AND `resource_type` = 'group'
);

SET @form_data_group_id = (SELECT `id` FROM `fun_permission` WHERE `source_type` = 'admin_web' AND `source_name` = 'form_data' AND `resource_type` = 'group' LIMIT 1);

-- 路由权限（守卫式批量插入）。
SET @sql = CONCAT(
  'INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES ',
  '(@gid@,''console'',''console/formdata:meta'',''console/formdata'',''meta'',CONVERT(X''E8A1A8E58D95E695B0E68DAE'' USING utf8mb4),''route'',1,0,1,''admin_web'',''form_data'',NOW(),NOW(),1,NULL),',
  '(@gid@,''console'',''console/formdata:index'',''console/formdata'',''index'',CONVERT(X''E69FA5E79C8BE8A1A8E58D95E695B0E68DAE'' USING utf8mb4),''route'',1,0,10,''admin_web'',''form_data'',NOW(),NOW(),10,NULL),',
  '(@gid@,''console'',''console/formdata:detail'',''console/formdata'',''detail'',CONVERT(X''E8A1A8E58D95E695B0E68DAEE8AFA6E68385'' USING utf8mb4),''route'',1,0,11,''admin_web'',''form_data'',NOW(),NOW(),11,NULL),',
  '(@gid@,''console'',''console/formdata:create'',''console/formdata'',''create'',CONVERT(X''E5889BE5BBBAE8A1A8E58D95E695B0E68DAE'' USING utf8mb4),''route'',1,0,20,''admin_web'',''form_data'',NOW(),NOW(),20,NULL),',
  '(@gid@,''console'',''console/formdata:update'',''console/formdata'',''update'',CONVERT(X''E69BB4E696B0E8A1A8E58D95E695B0E68DAE'' USING utf8mb4),''route'',1,0,21,''admin_web'',''form_data'',NOW(),NOW(),21,NULL),',
  '(@gid@,''console'',''console/formdata:remove'',''console/formdata'',''remove'',CONVERT(X''E588A0E999A4E8A1A8E58D95E695B0E68DAE'' USING utf8mb4),''route'',1,0,30,''admin_web'',''form_data'',NOW(),NOW(),30,NULL),',
  '(@gid@,''console'',''console/formdata:export'',''console/formdata'',''export'',CONVERT(X''E5AFBCE587BAE8A1A8E58D95E695B0E68DAE'' USING utf8mb4),''route'',1,0,40,''admin_web'',''form_data'',NOW(),NOW(),40,NULL),',
  '(@gid@,''console'',''console/formdata:options'',''console/formdata'',''options'',CONVERT(X''E8A1A8E58D95E98089E9A1B9E6BA90'' USING utf8mb4),''route'',1,0,41,''admin_web'',''form_data'',NOW(),NOW(),41,NULL),',
  '(@gid@,''console'',''console/formdata:sub'',''console/formdata'',''sub'',CONVERT(X''E8A1A8E58D95E5AD90E8A1A8E695B0E68DAE'' USING utf8mb4),''route'',1,0,42,''admin_web'',''form_data'',NOW(),NOW(),42,NULL);'
);
SET @sql = REPLACE(@sql, '@gid@', CAST(@form_data_group_id AS CHAR));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
