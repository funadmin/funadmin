-- 091 AI development menu, capabilities, explicit controller actions, and least-privilege grants.
SET @development_permission_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='development_tools' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT COALESCE(@development_permission_id,0),'console',NULL,'','',CONVERT(X'E699BAE883BDE5BC80E58F91E58AA9E6898B' USING utf8mb4),'group',1,0,'admin_web','ai_development',NOW(),NOW(),90,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='ai_development' AND `resource_type`='group');
SET @ai_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='ai_development' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@ai_group_id,'console','development:ai:view','development/ai','view',CONVERT(X'E69FA5E79C8B' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),110,NULL),
(@ai_group_id,'console','development:ai:chat','development/ai','chat',CONVERT(X'E5AFB9E8AF9D' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),120,NULL),
(@ai_group_id,'console','development:ai:execute','development/ai','execute',CONVERT(X'E689A7E8A18C' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),130,NULL),
(@ai_group_id,'console','development:ai:approve','development/ai','approve',CONVERT(X'E5AEA1E689B9' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),140,NULL),
(@ai_group_id,'console','development:ai:apply','development/ai','apply',CONVERT(X'E5BA94E794A8E58F98E69BB4' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),150,NULL),
(@ai_group_id,'console','development:ai:configure','development/ai','configure',CONVERT(X'E9858DE7BDAE' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),160,NULL),
(@ai_group_id,'console','development:ai:audit','development/ai','audit',CONVERT(X'E5AEA1E8AEA1' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),170,NULL),
(@ai_group_id,'console','console/development.ai:conversationIndex','console/development.ai','conversationIndex',CONVERT(X'E4BC9AE8AF9DE58897E8A1A8' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),10,NULL),
(@ai_group_id,'console','console/development.ai:messageCreate','console/development.ai','messageCreate',CONVERT(X'E58F91E98081E6B688E681AF' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),20,NULL),
(@ai_group_id,'console','console/development.ai:taskExecute','console/development.ai','taskExecute',CONVERT(X'E689A7E8A18CE4BBBBE58AA1' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),30,NULL),
(@ai_group_id,'console','console/development.ai:approvalDecide','console/development.ai','approvalDecide',CONVERT(X'E586B3E7AD96E5AEA1E689B9' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),40,NULL),
(@ai_group_id,'console','console/development.ai:changeSetApply','console/development.ai','changeSetApply',CONVERT(X'E5BA94E794A8E58F98E69BB4E99B86' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),50,NULL),
(@ai_group_id,'console','console/development.ai:configurationUpdate','console/development.ai','configurationUpdate',CONVERT(X'E69BB4E696B0E9858DE7BDAE' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),60,NULL),
(@ai_group_id,'console','console/development.ai:auditIndex','console/development.ai','auditIndex',CONVERT(X'E69FA5E79C8BE5AEA1E8AEA1' USING utf8mb4),'route',1,0,'admin_web','ai_development',NOW(),NOW(),70,NULL);
SET @development_menu_id=(SELECT `id` FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='development_tools' ORDER BY `id` LIMIT 1);
SET @ai_view_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/development.ai:conversationIndex' ORDER BY `id` LIMIT 1);
INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT COALESCE(@development_menu_id,0),@ai_view_id,'console',CONVERT(X'E699BAE883BDE5BC80E58F91E58AA9E6898B' USING utf8mb4),'/development/ai','component=development/ai/index&name=AiDevelopment&type=C&permission=development:ai:view','_self','i-ep-cpu',0,'admin_web','ai_development',NOW(),NOW(),30,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='ai_development' AND `href`='/development/ai');
-- Copy only the approved, provably equivalent view-to-view/chat and generate-to-execute grants.
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT legacy.`ptype`,legacy.`v0`,legacy.`v1`,mapping.`new_obj`,mapping.`new_act`,'','',SHA2(CONCAT_WS(CHAR(31),legacy.`ptype`,legacy.`v0`,legacy.`v1`,mapping.`new_obj`,mapping.`new_act`),256)
FROM `fun_casbin_rule` legacy
INNER JOIN `fun_permission` old_permission ON old_permission.`obj`=legacy.`v2` AND old_permission.`act`=legacy.`v3` AND old_permission.`status`=1 AND old_permission.`deleted_at` IS NULL AND old_permission.`source_type`='admin_web' AND old_permission.`source_name`='business_development'
INNER JOIN (
 SELECT 'development/business' `old_obj`,'view' `old_act`,'development/ai' `new_obj`,'view' `new_act` UNION ALL
 SELECT 'development/business','view','console/development.ai','messageCreate' UNION ALL
 SELECT 'development/business','generate','development/ai','execute' UNION ALL
 SELECT 'development/business','generate','console/development.ai','taskExecute'
) mapping ON mapping.`old_obj`=legacy.`v2` AND mapping.`old_act`=legacy.`v3`
WHERE legacy.`ptype`='p';
