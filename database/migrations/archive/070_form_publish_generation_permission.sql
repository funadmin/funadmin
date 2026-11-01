-- 069 表单发布生成记录查看权限。
SET @form_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='form_management' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
VALUES (@form_group_id,'console','console/form.designer:generation','console/form.designer','generation',CONVERT(X'E69FA5E79C8BE7949FE68890E8AEB0E5BD95' USING utf8mb4),'route',1,0,66,'admin_web','form_management',NOW(),NOW(),66,NULL);
