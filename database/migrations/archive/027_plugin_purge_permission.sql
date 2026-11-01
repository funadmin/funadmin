-- 独立插件 purge 权限，只向前增加，不修改已发布权限 migration。
SET @plugin_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'plugin_center' AND `resource_type` = 'group'
  ORDER BY `id` ASC LIMIT 1
);

INSERT INTO `fun_permission`
(`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`,`create_time`,`update_time`)
SELECT @plugin_permission_id, 'backend', 'backend/systemplugin:purge', 'backend/systemplugin', 'purge',
       '清除插件数据', 'route', 1, 0, 91, 'admin_web', 'plugin_center', NOW(), NOW(), UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE @plugin_permission_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code` = 'backend/systemplugin:purge');
