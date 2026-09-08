-- 插件开发 API 权限；挂载到现有插件中心与 CRUD Workbench 权限组，不新增顶级菜单。
SET @plugin_permission_id = (SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='plugin_center' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
SET @crud_permission_id = (SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='development_crud' AND `resource_type`='group' ORDER BY `id` LIMIT 1);

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @plugin_permission_id,'console','development:plugin:create-preview','console/development.devplugin','previewcreate','预览插件创建','route',1,0,120,'admin_web','plugin_center',NOW(),NOW(),120,NULL
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:plugin:create-preview');
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @plugin_permission_id,'console','development:plugin:create','console/development.devplugin','create','创建插件骨架','route',1,0,121,'admin_web','plugin_center',NOW(),NOW(),121,NULL
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:plugin:create');
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @plugin_permission_id,'console','development:plugin:validate','console/development.devplugin','validate','校验插件','route',1,0,122,'admin_web','plugin_center',NOW(),NOW(),122,NULL
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:plugin:validate');
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @plugin_permission_id,'console','development:plugin:package','console/development.devplugin','package','打包插件','route',1,0,123,'admin_web','plugin_center',NOW(),NOW(),123,NULL
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:plugin:package');
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @plugin_permission_id,'console','development:plugin:download','console/development.devplugin','packagedownload','下载插件包','route',1,0,124,'admin_web','plugin_center',NOW(),NOW(),124,NULL
WHERE @plugin_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:plugin:download');
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @crud_permission_id,'console','development:plugin:options','console/development.devplugin','options','读取可开发插件','route',1,0,101,'admin_web','development_crud',NOW(),NOW(),101,NULL
WHERE @crud_permission_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:plugin:options');
