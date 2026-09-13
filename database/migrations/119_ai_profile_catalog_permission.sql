-- 119 保存档案模型目录权限；保留 117/118 历史，仅继承 configure 授权。
SET @ai_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='ai_development' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
VALUES (COALESCE(@ai_group_id,0),'console','console/ai.profiles:models','console/ai.profiles','models','AI 档案模型目录','route',1,0,'admin_web','ai_development',NOW(),NOW(),191,NULL);
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT policy.`ptype`,policy.`v0`,policy.`v1`,'console/ai.profiles','models','','',SHA2(CONCAT_WS(CHAR(31),policy.`ptype`,policy.`v0`,policy.`v1`,'console/ai.profiles','models'),256)
FROM `fun_casbin_rule` policy
WHERE policy.`ptype`='p' AND policy.`v2`='development/ai' AND policy.`v3`='configure';
