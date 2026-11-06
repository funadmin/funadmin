-- CRUD Workbench 补齐 schema 与 preview 权限；仅向前新增，不修改已执行的 033。
SET @crud_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'development_crud' AND `resource_type` = 'group'
  ORDER BY `id` LIMIT 1
);

INSERT IGNORE INTO `fun_permission`
(`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
VALUES
(@crud_permission_id,'console','development:crud:schema','development/crud','schema','查看 CRUD 表结构','route',1,0,72,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'console','development:crud:preview','development/crud','preview','预览 CRUD 生成','route',1,0,82,'admin_web','development_crud',NOW(),NOW());

UPDATE `fun_permission`
SET `code` = 'console/devcrud:tableschema', `obj` = 'console/devcrud', `act` = 'tableschema', `updated_at` = NOW()
WHERE `source_type` = 'admin_web' AND `source_name` = 'development_crud'
  AND `obj` = 'console/devcrud' AND `act` = 'tableschema';

UPDATE `fun_permission`
SET `code` = 'console/devcrud:preview', `obj` = 'console/devcrud', `act` = 'preview', `updated_at` = NOW()
WHERE `source_type` = 'admin_web' AND `source_name` = 'development_crud'
  AND `obj` = 'console/devcrud' AND `act` = 'preview';