-- CRUD 菜单与权限受控应用权限；仅创建资源，不写普通角色 Casbin 策略。
SET @crud_permission_id = (SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='development_crud' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @crud_permission_id,'console','development:crud:apply-resources','development/crud','apply-resources','应用 CRUD 菜单与权限','route',1,0,100,'admin_web','development_crud',NOW(),NOW(),100,NULL
WHERE @crud_permission_id IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:crud:apply-resources');

UPDATE `fun_permission`
SET `pid`=@crud_permission_id,`app_name`='console',`obj`='development/crud',`act`='apply-resources',
    `name`='应用 CRUD 菜单与权限',`resource_type`='route',`status`=1,`is_public`=0,
    `sort`=100,`sort_order`=100,`source_type`='admin_web',`source_name`='development_crud',
    `updated_at`=NOW(),`deleted_at`=NULL
WHERE @crud_permission_id IS NOT NULL AND `code`='development:crud:apply-resources';
