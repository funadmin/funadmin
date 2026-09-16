-- 081 角色字段授权与完整授权工作区，仅向前新增。
CREATE TABLE IF NOT EXISTS `fun_permission_field` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `permission_id` int unsigned NOT NULL COMMENT '所属功能权限资源ID',
  `resource` varchar(190) NOT NULL COMMENT '稳定资源标识',
  `field` varchar(100) NOT NULL COMMENT '服务端字段名',
  `name` varchar(100) NOT NULL COMMENT '字段显示名称',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permission_field_resource_field` (`resource`,`field`),
  KEY `idx_permission_field_permission` (`permission_id`,`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限资源字段白名单';

CREATE TABLE IF NOT EXISTS `fun_auth_group_field_permission` (
  `role_id` int unsigned NOT NULL,
  `field_id` bigint unsigned NOT NULL,
  `can_view` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`role_id`,`field_id`),
  KEY `idx_role_field_permission_field` (`field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色字段权限';

SET @role_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='role' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @role_group_id,'console','console/systemrole:authorization','console/systemrole','authorization','查看角色完整授权','route',1,0,'admin_web','role',NOW(),NOW(),61,NULL
WHERE @role_group_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemrole:authorization');
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @role_group_id,'console','console/systemrole:saveauthorization','console/systemrole','saveauthorization','保存角色完整授权','route',1,0,'admin_web','role',NOW(),NOW(),62,NULL
WHERE @role_group_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemrole:saveauthorization');
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT @role_group_id,'console','console/systemrole:copyauthorization','console/systemrole','copyauthorization','复制角色完整授权','route',1,0,'admin_web','role',NOW(),NOW(),63,NULL
WHERE @role_group_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='console/systemrole:copyauthorization');
