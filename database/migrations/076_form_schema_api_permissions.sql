-- 076 FormSchema API 独立路由权限，仅向前新增。
SET @form_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='form_management' AND `resource_type`='group' ORDER BY `id` LIMIT 1);

INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@form_group_id,'console','console/form.designer:compile','console/form.designer','compile',CONVERT(X'E7BC96E8AF91E8A1A8E58D95E58D8FE8AEAE' USING utf8mb4),'route',1,0,71,'admin_web','form_management',NOW(),NOW(),71,NULL),
(@form_group_id,'console','console/form.designer:import','console/form.designer','import',CONVERT(X'E5AFBCE585A5E8A1A8E58D95E58D8FE8AEAE' USING utf8mb4),'route',1,0,72,'admin_web','form_management',NOW(),NOW(),72,NULL),
(@form_group_id,'console','console/form.designer:export','console/form.designer','export',CONVERT(X'E5AFBCE587BAE8A1A8E58D95E58D8FE8AEAE' USING utf8mb4),'route',1,0,73,'admin_web','form_management',NOW(),NOW(),73,NULL),
(@form_group_id,'console','console/form.designer:versions','console/form.designer','versions',CONVERT(X'E69FA5E79C8BE8A1A8E58D95E78988E69CACE58897E8A1A8' USING utf8mb4),'route',1,0,74,'admin_web','form_management',NOW(),NOW(),74,NULL),
(@form_group_id,'console','console/form.designer:version','console/form.designer','version',CONVERT(X'E69FA5E79C8BE8A1A8E58D95E78988E69CACE' USING utf8mb4),'route',1,0,75,'admin_web','form_management',NOW(),NOW(),75,NULL),
(@form_group_id,'console','console/form.designer:diff','console/form.designer','diff',CONVERT(X'E6AF94E8BE83E8A1A8E58D95E78988E69CACE' USING utf8mb4),'route',1,0,76,'admin_web','form_management',NOW(),NOW(),76,NULL),
(@form_group_id,'console','console/form.designer:rollback','console/form.designer','rollback',CONVERT(X'E59B9EE6BB9AE8A1A8E58D95E78988E69CACE' USING utf8mb4),'route',1,0,77,'admin_web','form_management',NOW(),NOW(),77,NULL),
(@form_group_id,'console','console/form.designer:componentCatalog','console/form.designer','componentCatalog',CONVERT(X'E69FA5E79C8BE8A1A8E58D95E7BB84E4BBB6E79BAEE5BD95' USING utf8mb4),'route',1,0,78,'admin_web','form_management',NOW(),NOW(),78,NULL);
