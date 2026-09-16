-- 136 权限资源树顶层改为按 app/admin/controller 目录组织，移除 Console 系统根。
-- 中文统一使用 UTF-8 十六进制字面量，避免受客户端连接字符集影响。
-- Casbin 只引用 obj/act，本迁移只调整 pid 归属，不改变任何授权语义。

-- 1. 按控制器目录创建顶级分组（幂等）。
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin',NULL,'','',CONVERT(X'E7B3BBE7BB9FE7AEA1E79086' USING utf8mb4),'group',1,0,10,'admin_web','controller_system',NOW(),NOW(),10,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='controller_system' AND `resource_type`='group');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin',NULL,'','',CONVERT(X'E69D83E99990E7AEA1E79086' USING utf8mb4),'group',1,0,20,'admin_web','controller_authorization',NOW(),NOW(),20,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='controller_authorization' AND `resource_type`='group');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin',NULL,'','',CONVERT(X'E8AEA4E8AF81' USING utf8mb4),'group',1,0,30,'admin_web','controller_authentication',NOW(),NOW(),30,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='controller_authentication' AND `resource_type`='group');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin',NULL,'','',CONVERT(X'E8BAABE4BBBDE6A087E8AF86' USING utf8mb4),'group',1,0,40,'admin_web','controller_identity',NOW(),NOW(),40,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='controller_identity' AND `resource_type`='group');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin',NULL,'','',CONVERT(X'E4B89AE58AA1E5BC80E58F91' USING utf8mb4),'group',1,0,50,'admin_web','controller_development',NOW(),NOW(),50,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='controller_development' AND `resource_type`='group');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin',NULL,'','',CONVERT(X'E8A1A8E58D95' USING utf8mb4),'group',1,0,60,'admin_web','controller_form',NOW(),NOW(),60,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='controller_form' AND `resource_type`='group');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin',NULL,'','',CONVERT(X'E68F92E4BBB6' USING utf8mb4),'group',1,0,70,'admin_web','controller_plugin',NOW(),NOW(),70,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='controller_plugin' AND `resource_type`='group');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin',NULL,'','',CONVERT(X'414920E58AA9E6898B' USING utf8mb4),'group',1,0,80,'admin_web','controller_ai',NOW(),NOW(),80,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='controller_ai' AND `resource_type`='group');

-- 2. 既有分组按控制器目录重挂；子树随分组 pid 自动迁移。
-- system 目录：字典/系统/系统升级（system 组下 role、menu、permission 单独归入 authorization）。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_system' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`source_type`='admin_web'
  AND child.`source_name` IN ('dictionary','system','system_upgrade')
  AND child.`resource_type`='group';

-- authorization 目录：角色/菜单/权限资源控制器。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_authorization' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`source_type`='admin_web'
  AND child.`source_name` IN ('role','menu','permission')
  AND child.`resource_type`='group';

-- authentication 目录：个人资料。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_authentication' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`source_type`='admin_web'
  AND child.`source_name` IN ('profile')
  AND child.`resource_type`='group';

-- identity 目录：企业应用中心。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_identity' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`source_type`='admin_web'
  AND child.`source_name` IN ('enterprise_application_center')
  AND child.`resource_type`='group';

-- development 目录：业务开发与已退役的 CRUD 生成器。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_development' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`source_type`='admin_web'
  AND child.`source_name` IN ('business_development','development_crud')
  AND child.`resource_type`='group';

-- form 目录：表单管理与表单数据。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_form' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`source_type`='admin_web'
  AND child.`source_name` IN ('form_management','form_data')
  AND child.`resource_type`='group';

-- plugin 目录：插件中心。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_plugin' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`source_type`='admin_web'
  AND child.`source_name` IN ('plugin_center')
  AND child.`resource_type`='group';

-- ai 目录：智能开发助手。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_ai' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`source_type`='admin_web'
  AND child.`source_name` IN ('ai_development')
  AND child.`resource_type`='group';

-- 3. 无控制器归属的顶层能力资源归入合理目录：示例插件归插件目录，OAuth 客户端/签名 Key 归身份标识目录。
UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_plugin' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`pid`=0
  AND child.`resource_type`<>'group'
  AND child.`source_type`='plugin'
  AND child.`source_name`='example';

UPDATE `fun_permission` AS child
JOIN `fun_permission` AS parent
  ON parent.`source_type`='admin_web' AND parent.`source_name`='controller_identity' AND parent.`resource_type`='group'
SET child.`pid`=parent.`id`, child.`updated_at`=NOW()
WHERE child.`pid`=0
  AND child.`resource_type`<>'group'
  AND child.`source_name`='oauth_client_console';

-- 4. 删除已无子节点的 Console 系统根（开发阶段不保留兼容）。
DELETE `root`
FROM `fun_permission` AS `root`
LEFT JOIN `fun_permission` AS `child` ON `child`.`pid`=`root`.`id`
WHERE `root`.`source_type`='admin_web'
  AND `root`.`source_name`='console_root'
  AND `root`.`resource_type`='group'
  AND `child`.`id` IS NULL;

-- 5. 收敛校验：console_root 清空、顶层恰好 8 个目录分组、无悬挂节点。
CREATE TEMPORARY TABLE IF NOT EXISTS `tmp_136_permission_tree_assertion` (
  `console_root_count` int unsigned NOT NULL,
  `top_group_count` int unsigned NOT NULL,
  `orphan_count` int unsigned NOT NULL,
  CONSTRAINT `chk_136_console_root_removed` CHECK (`console_root_count`=0),
  CONSTRAINT `chk_136_top_groups` CHECK (`top_group_count`=8),
  CONSTRAINT `chk_136_no_orphans` CHECK (`orphan_count`=0)
) ENGINE=InnoDB;
DELETE FROM `tmp_136_permission_tree_assertion`;
INSERT INTO `tmp_136_permission_tree_assertion` (`console_root_count`,`top_group_count`,`orphan_count`)
SELECT
  (SELECT COUNT(*) FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='console_root'),
  (SELECT COUNT(*) FROM `fun_permission` WHERE `pid`=0 AND `resource_type`='group' AND `source_type`='admin_web'),
  (SELECT COUNT(*) FROM `fun_permission` AS `child` LEFT JOIN `fun_permission` AS `parent` ON `parent`.`id`=`child`.`pid` WHERE `child`.`pid`<>0 AND `parent`.`id` IS NULL);
