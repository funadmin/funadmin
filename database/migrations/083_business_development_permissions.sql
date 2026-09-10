-- 083 Unified Business API permissions and compatibility grants; forward-only and idempotent.
SET @console_root_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='console_root' AND `resource_type`='group' ORDER BY `id` LIMIT 1);

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT COALESCE(@console_root_id,0),'console',NULL,'','',CONVERT(X'E4B89AE58AA1E5BC80E58F91' USING utf8mb4),'group',1,0,'admin_web','business_development',NOW(),NOW(),80,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='business_development' AND `resource_type`='group');
SET @business_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='business_development' AND `resource_type`='group' ORDER BY `id` LIMIT 1);

-- Every controller action keeps a unique route code; AdminAuth exposes unified development:business:* aliases.
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@business_group_id,'console','console/development.business:modules','console/development.business','modules',CONVERT(X'E69FA5E79C8BE4B89AE58AA1' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),10,NULL),
(@business_group_id,'console','console/development.business:module','console/development.business','module',CONVERT(X'E69FA5E79C8BE4B89AE58AA1' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),11,NULL),
(@business_group_id,'console','console/development.business:createvisual','console/development.business','createvisual',CONVERT(X'E5889BE5BBBAE4B89AE58AA1' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),20,NULL),
(@business_group_id,'console','console/development.business:inspectdatabase','console/development.business','inspectdatabase',CONVERT(X'E6A380E69FA5E695B0E68DAEE5BA93' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),30,NULL),
(@business_group_id,'console','console/development.business:createfromdatabase','console/development.business','createfromdatabase',CONVERT(X'E5889BE5BBBAE4B89AE58AA1' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),21,NULL),
(@business_group_id,'console','console/development.business:validateschema','console/development.business','validateschema',CONVERT(X'E6A0A1E9AA8C20536368656D61' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),22,NULL),
(@business_group_id,'console','console/development.business:saveschema','console/development.business','saveschema',CONVERT(X'E4BF9DE5AD9820536368656D61' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),23,NULL),
(@business_group_id,'console','console/development.business:previewpublish','console/development.business','previewpublish',CONVERT(X'E9A284E8A788E58AA8E68081E58F91E5B883' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),40,NULL),
(@business_group_id,'console','console/development.business:publish','console/development.business','publish',CONVERT(X'E689A7E8A18CE58AA8E68081E58F91E5B883' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),41,NULL),
(@business_group_id,'console','console/development.business:runtimemeta','console/development.business','runtimemeta',CONVERT(X'E69FA5E79C8BE8BF90E8A18CE58583E695B0E68DAE' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),12,NULL),
(@business_group_id,'console','console/development.business:previewformalgeneration','console/development.business','previewformalgeneration',CONVERT(X'E9A284E8A788E6ADA3E5BC8FE7949FE68890' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),50,NULL),
(@business_group_id,'console','console/development.business:formalgeneration','console/development.business','formalgeneration',CONVERT(X'E689A7E8A18CE6ADA3E5BC8FE7949FE68890' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),51,NULL),
(@business_group_id,'console','console/development.business:generations','console/development.business','generations',CONVERT(X'E69FA5E79C8BE7949FE68890E8AEB0E5BD95' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),70,NULL),
(@business_group_id,'console','console/development.business:generation','console/development.business','generation',CONVERT(X'E69FA5E79C8BE7949FE68890E8AFA6E68385' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),71,NULL),
(@business_group_id,'console','console/development.business:retryresources','console/development.business','retryresources',CONVERT(X'E9878DE8AF95E8B584E6BA90' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),61,NULL),
(@business_group_id,'console','console/development.business:adoptresolvedbaseline','console/development.business','adoptresolvedbaseline',CONVERT(X'E98787E7BAB3E586B2E7AA81E59FBAE7BABF' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),24,NULL),
(@business_group_id,'console','console/development.business:fieldcapabilities','console/development.business','fieldcapabilities',CONVERT(X'E69FA5E79C8BE5AD97E6AEB5E883BDE58A9B' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),13,NULL);

-- Stable capability nodes support explicit generate/resource checks without conflating controller actions.
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@business_group_id,'console','development:business:view','development/business','view',CONVERT(X'E69FA5E79C8BE4B89AE58AA1' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),110,NULL),
(@business_group_id,'console','development:business:save','development/business','save',CONVERT(X'E4BF9DE5AD9820536368656D61' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),120,NULL),
(@business_group_id,'console','development:business:inspect','development/business','inspect',CONVERT(X'E6A380E69FA5E695B0E68DAEE5BA93' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),130,NULL),
(@business_group_id,'console','development:business:publish','development/business','publish',CONVERT(X'E4B89AE58AA1E58F91E5B883' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),140,NULL),
(@business_group_id,'console','development:business:generate','development/business','generate',CONVERT(X'E7949FE68890E6ADA3E5BC8FE6A8A1E59D97' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),150,NULL),
(@business_group_id,'console','development:business:apply-resources','development/business','apply-resources',CONVERT(X'E5BA94E794A8E7949FE68890E8B584E6BA90' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),160,NULL),
(@business_group_id,'console','development:business:records','development/business','records',CONVERT(X'E69FA5E79C8BE7949FE68890E8AEB0E5BD95' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),170,NULL);

SET @development_menu_id=(SELECT `id` FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='development_tools' ORDER BY `id` LIMIT 1);
SET @business_view_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/development.business:modules' ORDER BY `id` LIMIT 1);
SET @business_save_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/development.business:createvisual' ORDER BY `id` LIMIT 1);
SET @business_inspect_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/development.business:inspectdatabase' ORDER BY `id` LIMIT 1);
SET @business_records_id=(SELECT `id` FROM `fun_permission` WHERE `code`='console/development.business:generations' ORDER BY `id` LIMIT 1);

INSERT INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT COALESCE(@development_menu_id,0),@business_view_id,'console',CONVERT(X'E4B89AE58AA1E5BC80E58F91' USING utf8mb4),'business','component=Layout&name=BusinessDevelopment&type=M&redirect=/development/business/mine&permission=development:business:view','_self','i-ep-briefcase',1,'admin_web','business_development',NOW(),NOW(),20,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='business_development' AND `href`='business');
SET @business_menu_id=(SELECT `id` FROM `fun_admin_menu` WHERE `source_type`='admin_web' AND `source_name`='business_development' AND `href`='business' ORDER BY `id` LIMIT 1);

INSERT IGNORE INTO `fun_admin_menu` (`pid`,`permission_id`,`app_name`,`name`,`href`,`query`,`target`,`icon`,`status`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@business_menu_id,@business_view_id,'console',CONVERT(X'E68891E79A84E4B89AE58AA1' USING utf8mb4),'mine','component=development/business/mine&name=BusinessMine&type=C&permission=development:business:view','_self','i-ep-user',1,'admin_web','business_development',NOW(),NOW(),10,NULL),
(@business_menu_id,@business_save_id,'console',CONVERT(X'E58FAFE8A786E58C96E5889BE5BBBA' USING utf8mb4),'visual','component=development/business/visual&name=BusinessVisual&type=C&permission=development:business:save','_self','i-ep-edit-pen',1,'admin_web','business_development',NOW(),NOW(),20,NULL),
(@business_menu_id,@business_inspect_id,'console',CONVERT(X'E695B0E68DAEE5BA93E98787E7BAB3' USING utf8mb4),'database','component=development/business/database&name=BusinessDatabase&type=C&permission=development:business:inspect','_self','i-ep-coin',1,'admin_web','business_development',NOW(),NOW(),30,NULL),
(@business_menu_id,@business_records_id,'console',CONVERT(X'E7949FE68890E8AEB0E5BD95' USING utf8mb4),'records','component=development/business/records&name=BusinessRecords&type=C&permission=development:business:records','_self','i-ep-document',1,'admin_web','business_development',NOW(),NOW(),40,NULL);

-- Copy only grants backed by legacy permission rows from the two declared sources.
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT legacy.`ptype`,legacy.`v0`,legacy.`v1`,mapping.`new_obj`,mapping.`new_act`,'','',
       SHA2(CONCAT_WS(CHAR(31),legacy.`ptype`,legacy.`v0`,legacy.`v1`,mapping.`new_obj`,mapping.`new_act`),256)
FROM `fun_casbin_rule` legacy
INNER JOIN `fun_permission` old_permission ON old_permission.`obj`=legacy.`v2` AND old_permission.`act`=legacy.`v3`
  AND old_permission.`source_type`='admin_web' AND old_permission.`source_name` IN ('form_management','development_crud')
INNER JOIN (
 SELECT 'console/form.designer' `old_obj`,'index' `old_act`,'console/development.business' `new_obj`,'modules' `new_act` UNION ALL
 SELECT 'console/form.designer','detail','console/development.business','module' UNION ALL
 SELECT 'console/form.designer','save','console/development.business','createvisual' UNION ALL
 SELECT 'console/form.designer','validate','console/development.business','validateschema' UNION ALL
 SELECT 'console/form.designer','save','console/development.business','saveschema' UNION ALL
 SELECT 'console/form.designer','previewpublish','console/development.business','previewpublish' UNION ALL
 SELECT 'console/form.designer','publish','console/development.business','publish' UNION ALL
 SELECT 'console/form.designer','componentcatalog','console/development.business','fieldcapabilities' UNION ALL
 SELECT 'console/form.designer','index','development/business','view' UNION ALL
 SELECT 'console/form.designer','save','development/business','save' UNION ALL
 SELECT 'console/form.designer','publish','development/business','publish' UNION ALL
 SELECT 'console/devcrud','tableschema','console/development.business','inspectdatabase' UNION ALL
 SELECT 'console/devcrud','preview','console/development.business','previewformalgeneration' UNION ALL
 SELECT 'console/devcrud','generate','console/development.business','formalgeneration' UNION ALL
 SELECT 'console/devcrud','generationdetail','console/development.business','generation' UNION ALL
 SELECT 'console/devcrud','generationdetail','console/development.business','generations' UNION ALL
 SELECT 'development/crud','apply-resources','console/development.business','retryresources' UNION ALL
 SELECT 'console/devcrud','tableschema','development/business','inspect' UNION ALL
 SELECT 'console/devcrud','generate','development/business','generate' UNION ALL
 SELECT 'console/devcrud','generationdetail','development/business','records' UNION ALL
 SELECT 'development/crud','apply-resources','development/business','apply-resources'
) mapping ON mapping.`old_obj`=legacy.`v2` AND mapping.`old_act`=legacy.`v3`
WHERE legacy.`ptype`='p';
