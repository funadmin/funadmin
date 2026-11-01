-- 私有 AI 附件；仅新增，不修改历史迁移，不向公共附件表写入 URL。
CREATE TABLE IF NOT EXISTS `fun_ai_attachment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `admin_id` bigint unsigned NOT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `kind` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mime` varchar(64) NOT NULL,
  `size` int unsigned NOT NULL,
  `width` int unsigned DEFAULT NULL,
  `height` int unsigned DEFAULT NULL,
  `sha256` char(64) NOT NULL,
  `storage_path` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_storage_path` (`storage_path`),
  KEY `idx_owner_conversation` (`admin_id`,`conversation_id`),
  KEY `idx_message` (`message_id`),
  KEY `idx_draft_expiry` (`message_id`,`expires_at`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @ai_group_id=(SELECT `id` FROM `fun_permission` WHERE `source_type`='admin_web' AND `source_name`='ai_development' AND `resource_type`='group' ORDER BY `id` LIMIT 1);
INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
VALUES
(COALESCE(@ai_group_id,0),'console','console/development.ai:attachmentcreate','console/development.ai','attachmentcreate','AI 私有附件上传','route',1,0,'admin_web','ai_development',NOW(),NOW(),192,NULL),
(COALESCE(@ai_group_id,0),'console','console/development.ai:attachmentcontent','console/development.ai','attachmentcontent','AI 私有附件读取','route',1,0,'admin_web','ai_development',NOW(),NOW(),193,NULL),
(COALESCE(@ai_group_id,0),'console','console/development.ai:attachmentdelete','console/development.ai','attachmentdelete','AI 私有草稿删除','route',1,0,'admin_web','ai_development',NOW(),NOW(),194,NULL);
INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT p.`ptype`,p.`v0`,p.`v1`,'console/development.ai',a.`act`,'','',SHA2(CONCAT_WS(CHAR(31),p.`ptype`,p.`v0`,p.`v1`,'console/development.ai',a.`act`),256)
FROM `fun_casbin_rule` p
CROSS JOIN (SELECT 'attachmentcreate' AS `act` UNION ALL SELECT 'attachmentcontent' UNION ALL SELECT 'attachmentdelete') a
WHERE p.`ptype`='p' AND p.`v2`='console/development.ai' AND p.`v3`='messagecreate';
