-- 修正插件资源根前缀并补充插件包下载权限，只向前补偿。
SET @plugin_permission_id = (SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='plugin_center' AND `resource_type`='group' ORDER BY `id` LIMIT 1);

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @plugin_permission_id,'console','development:plugin:download','console/development.devplugin','packagedownload','下载插件包','route',1,0,124,'admin_web','plugin_center',NOW(),NOW(),124,NULL
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:plugin:download');

UPDATE `fun_plugin_resource`
SET `target_path` = CONCAT('admin-web:', SUBSTRING(`target_path`, LENGTH('public:') + 1))
WHERE `resource_type` = 'file'
  AND `target_path` LIKE 'public:src/modules/%';

UPDATE `fun_plugin_resource`
SET `target_path` = CONCAT('admin-web:', `target_path`)
WHERE `resource_type` = 'file'
  AND `target_path` LIKE 'src/modules/%';
