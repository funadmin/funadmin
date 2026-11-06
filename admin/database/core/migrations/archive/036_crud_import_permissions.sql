-- 为会员组和会员等级补充 REST CSV 导入权限。
SET @member_group_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'member_group' AND `resource_type` = 'group'
  ORDER BY `id` ASC LIMIT 1
);
SET @member_level_permission_id = (
  SELECT `id` FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'member_level' AND `resource_type` = 'group'
  ORDER BY `id` ASC LIMIT 1
);

INSERT IGNORE INTO `fun_permission`
(`pid`,`module`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort_order`,`source_type`,`source_name`,`created_at`,`updated_at`) VALUES
(@member_group_permission_id,'backend','backend/systemmembergroup:import','backend/systemmembergroup','import','导入会员组','route',1,0,85,'admin_web','member_group',NOW(),NOW()),
(@member_level_permission_id,'backend','backend/systemmemberlevel:import','backend/systemmemberlevel','import','导入会员等级','route',1,0,85,'admin_web','member_level',NOW(),NOW());
