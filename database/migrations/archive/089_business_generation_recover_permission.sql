-- 089 Add the dedicated Business generation recovery permission; forward-only and idempotent.
SET @business_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='business_development' AND `resource_type`='group' ORDER BY `id` LIMIT 1);

INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@business_group_id,'console','development:business:recover','console/development.business','recovergeneration',CONVERT(X'E681A2E5A48DE7949FE68890E4BBBBE58AA1' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),43,NULL),
(@business_group_id,'console','console/development.business:recovergeneration','console/development.business','recovergeneration',CONVERT(X'E681A2E5A48DE7949FE68890E4BBBBE58AA1' USING utf8mb4),'route',1,0,'admin_web','business_development',NOW(),NOW(),44,NULL);
