-- 086 Migrate remaining read/schema capabilities to unified Business API; forward-only and idempotent.
SET @business_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='business_development' AND `resource_type`='group' ORDER BY `id` LIMIT 1);

INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@business_group_id,'console','console/development.business:compileschema','console/development.business','compileschema',CONVERT(X'E7BC96E8AF91E8A1A8E58D95' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),24,NULL),
(@business_group_id,'console','console/development.business:exportschema','console/development.business','exportschema',CONVERT(X'E5AFBCE587BAE8A1A8E58D95' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),25,NULL),
(@business_group_id,'console','console/development.business:schemaversions','console/development.business','schemaversions',CONVERT(X'E69FA5E79C8BE8A1A8E58D95E78988E69CACE58897E8A1A8' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),26,NULL),
(@business_group_id,'console','console/development.business:schemaversion','console/development.business','schemaversion',CONVERT(X'E69FA5E79C8BE8A1A8E58D95E78988E69CAC' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),27,NULL),
(@business_group_id,'console','console/development.business:schemadiff','console/development.business','schemadiff',CONVERT(X'E6AF94E8BE83E8A1A8E58D95E78988E69CAC' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),28,NULL),
(@business_group_id,'console','console/development.business:rollbackschema','console/development.business','rollbackschema',CONVERT(X'E59B9EE6BB9AE8A1A8E58D95E78988E69CAC' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),29,NULL),
(@business_group_id,'console','console/development.business:databasetables','console/development.business','databasetables',CONVERT(X'E69FA5E79C8BE695B0E68DAEE5BA93E8A1A8' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),31,NULL),
(@business_group_id,'console','console/development.business:databasetableschema','console/development.business','databasetableschema',CONVERT(X'E69FA5E79C8BE8A1A8E7BB93E69E84' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),32,NULL);

-- Preserve equivalent grants from the already-disabled legacy permission resources.
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT legacy.`ptype`,legacy.`v0`,legacy.`v1`,'console/development.business',mapping.`new_act`,'','',
       SHA2(CONCAT_WS(CHAR(31),legacy.`ptype`,legacy.`v0`,legacy.`v1`,'console/development.business',mapping.`new_act`),256)
FROM `fun_casbin_rule` legacy
INNER JOIN `fun_permission` old_permission ON old_permission.`obj`=legacy.`v2` AND old_permission.`act`=legacy.`v3`
  AND old_permission.`source_type`='admin_web' AND old_permission.`source_name` IN ('form_management','development_crud')
INNER JOIN (
 SELECT 'console/form.designer' `old_obj`,'compile' `old_act`,'compileschema' `new_act` UNION ALL
 SELECT 'console/form.designer','export','exportschema' UNION ALL
 SELECT 'console/form.designer','versions','schemaversions' UNION ALL
 SELECT 'console/form.designer','version','schemaversion' UNION ALL
 SELECT 'console/form.designer','diff','schemadiff' UNION ALL
 SELECT 'console/form.designer','rollback','rollbackschema' UNION ALL
 SELECT 'console/devcrud','tables','databasetables' UNION ALL
 SELECT 'console/devcrud','tableschema','databasetableschema'
) mapping ON mapping.`old_obj`=legacy.`v2` AND mapping.`old_act`=legacy.`v3`
WHERE legacy.`ptype`='p';
