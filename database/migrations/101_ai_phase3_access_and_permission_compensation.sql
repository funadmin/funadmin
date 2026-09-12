-- 101 AI phase-three access and runtime permission compensation; forward-only.
SET @schema_name=DATABASE();
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_ai_task' AND COLUMN_NAME='sandbox_retained'),'ALTER TABLE `fun_ai_task` ADD COLUMN `sandbox_retained` tinyint(1) NOT NULL DEFAULT 0 AFTER `sandbox_status`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_ai_task' AND COLUMN_NAME='heartbeat_at'),'ALTER TABLE `fun_ai_task` ADD COLUMN `heartbeat_at` datetime NULL AFTER `sandbox_retained`','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql=IF(NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_ai_task' AND INDEX_NAME='idx_ai_task_sandbox_lease'),'ALTER TABLE `fun_ai_task` ADD KEY `idx_ai_task_sandbox_lease` (`sandbox_status`,`sandbox_retained`,`heartbeat_at`,`completed_at`)','DO 0'); PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @ai_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='ai_development' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES
(@ai_group_id,'console','development:ai:full-access','development/ai','full-access',CONVERT(X'E585A8E9809AE8AEFE5968E' USING utf8mb4),'capability',1,0,'admin_web','ai_development',NOW(),NOW(),180,NULL);
UPDATE `fun_permission`
SET `status`=1,`deleted_at`=NULL,`updated_at`=NOW()
WHERE `source_type`='admin_web'
  AND `source_name`='ai_development'
  AND `obj`='console/development.ai'
  AND `act` IN ('approvalindex','approvaldecide','tasktoolcalls','toolcalllog','sandboxstatus','sandboxcleanup');
UPDATE `fun_casbin_rule` SET `v3`=LOWER(`v3`),`rule_hash`=SHA2(CONCAT_WS(CHAR(31),`ptype`,`v0`,`v1`,`v2`,LOWER(`v3`)),256) WHERE `ptype`='p' AND `v2`='console/development.ai' AND BINARY `v3`<>BINARY LOWER(`v3`);
