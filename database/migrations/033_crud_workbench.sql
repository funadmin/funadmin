-- M4 CRUD Workbench：生成审计、开发权限与 Admin Web 菜单，只向前新增且不硬编码 id。
CREATE TABLE IF NOT EXISTS `fun_crud_generation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `operation` varchar(20) NOT NULL,
  `status` varchar(32) NOT NULL,
  `connection_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(190) NOT NULL DEFAULT '',
  `definition_hash` char(64) NOT NULL,
  `definition` json DEFAULT NULL,
  `manifest` json DEFAULT NULL,
  `error` json DEFAULT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  KEY `idx_crud_generation_table` (`connection_name`,`table_name`),
  KEY `idx_crud_generation_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='CRUD 生成审计';

INSERT INTO `fun_permission`
(`pid`,`module`,`code`,`obj`,`act`,`title`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT 0,'backend',NULL,'','','CRUD 开发工具','group',1,0,360,'admin_web','development_crud',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='development_crud' AND `resource_type`='group');

SET @crud_permission_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='development_crud' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission`
(`pid`,`module`,`code`,`obj`,`act`,`title`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`) VALUES
(@crud_permission_id,'backend','backend/devcrud:connections','backend/devcrud','connections','查看数据源','route',1,0,10,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','backend/devcrud:tables','backend/devcrud','tables','查看数据表','route',1,0,11,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','backend/devcrud:tableschema','backend/devcrud','tableschema','查看表结构','route',1,0,12,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','backend/devcrud:infer','backend/devcrud','infer','推断 CRUD 字段','route',1,0,20,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','backend/devcrud:validate','backend/devcrud','validate','验证 CRUD 定义','route',1,0,30,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','backend/devcrud:preview','backend/devcrud','preview','预览 CRUD 生成','route',1,0,40,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','backend/devcrud:generate','backend/devcrud','generate','生成 CRUD 文件','route',1,0,50,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','backend/devcrud:generationdetail','backend/devcrud','generationdetail','查看生成审计','route',1,0,60,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','development:crud:list','development/crud','list','使用 CRUD Workbench','route',1,0,70,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','development:crud:generate','development/crud','generate','获取确认并生成','route',1,0,80,'admin_web','development_crud',NOW(),NOW()),
(@crud_permission_id,'backend','development:crud:overwrite','development/crud','overwrite','覆盖冲突文件','route',1,0,90,'admin_web','development_crud',NOW(),NOW());

INSERT INTO `fun_admin_menu`
(`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT 0,0,'backend','开发工具','/development','component=Layout&name=Development&type=M&redirect=/development/crud','_self','i-ep-tools',1,30,'admin_web','development_tools',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='development_tools');
SET @development_menu_id=(SELECT `id` FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='development_tools' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_admin_menu`
(`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`)
SELECT @development_menu_id,@crud_permission_id,'backend','CRUD生成器','crud','component=development/crud/index&name=DevelopmentCrud&type=C&permission=development:crud:list','_self','i-ep-magic-stick',1,10,'admin_web','development_crud',NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='development_crud');
