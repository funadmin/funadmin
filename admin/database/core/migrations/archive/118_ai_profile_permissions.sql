-- 118 独立档案路由权限；只向已有 configure 策略授予对应路由。
SET @ai_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='ai_development' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT COALESCE(@ai_group_id,0),'console',CONCAT('console/ai.profiles:',actions.action),'console/ai.profiles',actions.action,CONCAT('AI profiles ',actions.action),'route',1,0,'admin_web','ai_development',NOW(),NOW(),190,NULL
FROM (SELECT 'index' AS action UNION ALL SELECT 'read' UNION ALL SELECT 'create' UNION ALL SELECT 'update' UNION ALL SELECT 'delete' UNION ALL SELECT 'defaultread' UNION ALL SELECT 'defaultset' UNION ALL SELECT 'copy') actions;
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT policy.`ptype`,policy.`v0`,policy.`v1`,'console/ai.profiles',actions.action,'','',SHA2(CONCAT_WS(CHAR(31),policy.`ptype`,policy.`v0`,policy.`v1`,'console/ai.profiles',actions.action),256)
FROM `fun_casbin_rule` policy
CROSS JOIN (SELECT 'index' AS action UNION ALL SELECT 'read' UNION ALL SELECT 'create' UNION ALL SELECT 'update' UNION ALL SELECT 'delete' UNION ALL SELECT 'defaultread' UNION ALL SELECT 'defaultset' UNION ALL SELECT 'copy') actions
WHERE policy.`ptype`='p' AND policy.`v2`='development/ai' AND policy.`v3`='configure';
