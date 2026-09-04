-- 基于现有数据库的增量字段治理；保留 fun_member.group_id 兼容列但不再作为业务来源。
SET @schema_name = DATABASE();

CREATE TABLE IF NOT EXISTS `fun_member_group_relation` (
  `member_id` mediumint unsigned NOT NULL COMMENT '会员ID',
  `group_id` int unsigned NOT NULL COMMENT '会员组ID',
  `create_time` int unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`member_id`,`group_id`),
  KEY `idx_member_group_relation_group` (`group_id`,`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员与会员组关联';

-- 将旧逗号分隔分组迁移到关联表，仅迁移仍存在的会员组。
INSERT IGNORE INTO `fun_member_group_relation` (`member_id`,`group_id`,`create_time`)
SELECT m.`id`, CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(m.`group_id`, ',', seq.n), ',', -1) AS UNSIGNED), UNIX_TIMESTAMP()
FROM `fun_member` m
JOIN (
  SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
  UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
  UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32
) seq ON seq.n <= 1 + LENGTH(m.`group_id`) - LENGTH(REPLACE(m.`group_id`, ',', ''))
JOIN `fun_member_group` g ON g.`id` = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(m.`group_id`, ',', seq.n), ',', -1) AS UNSIGNED)
WHERE TRIM(COALESCE(m.`group_id`, '')) <> '';

ALTER TABLE `fun_member` MODIFY COLUMN `level_id` int unsigned NOT NULL DEFAULT 1 COMMENT '会员等级';
UPDATE `fun_member` SET `email` = NULL WHERE TRIM(COALESCE(`email`, '')) = '';
ALTER TABLE `fun_member` MODIFY COLUMN `email` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮件';
ALTER TABLE `fun_member` MODIFY COLUMN `last_ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT '最后登录IP';
ALTER TABLE `fun_admin` MODIFY COLUMN `email` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮箱';
ALTER TABLE `fun_admin` MODIFY COLUMN `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT 'IP地址';
ALTER TABLE `fun_auth_group` MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态';
ALTER TABLE `fun_field_verify` MODIFY COLUMN `verify` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '验证规则';

-- 仅在业务数据满足唯一性时补充约束，避免升级因历史脏数据中断。
SET @sql = IF(
  NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = CONCAT('fun_', 'member') AND INDEX_NAME = 'uk_member_username')
  AND NOT EXISTS(SELECT 1 FROM `fun_member` WHERE `username` IS NOT NULL GROUP BY `username` HAVING COUNT(*) > 1),
  'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_username` (`username`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = CONCAT('fun_', 'member') AND INDEX_NAME = 'uk_member_mobile')
  AND NOT EXISTS(SELECT 1 FROM `fun_member` GROUP BY `mobile` HAVING COUNT(*) > 1),
  'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_mobile` (`mobile`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = CONCAT('fun_', 'member') AND INDEX_NAME = 'uk_member_email')
  AND NOT EXISTS(SELECT 1 FROM `fun_member` WHERE `email` IS NOT NULL GROUP BY `email` HAVING COUNT(*) > 1),
  'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_email` (`email`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- config_group 使用软删除且业务已 withTrashed 判重，因此可安全覆盖全部记录。
SET @sql = IF(
  NOT EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = CONCAT('fun_', 'config_group') AND INDEX_NAME = 'uk_config_group_name')
  AND NOT EXISTS(SELECT 1 FROM `fun_config_group` GROUP BY `name` HAVING COUNT(*) > 1),
  'ALTER TABLE `fun_config_group` ADD UNIQUE KEY `uk_config_group_name` (`name`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = CONCAT('fun_', 'attach') AND INDEX_NAME = 'idx_attach_group_path'),
  'SELECT 1',
  'ALTER TABLE `fun_attach` ADD KEY `idx_attach_group_path` (`group_id`,`path`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = CONCAT('fun_', 'blacklist') AND INDEX_NAME = 'idx_blacklist_ip'),
  'SELECT 1',
  'ALTER TABLE `fun_blacklist` ADD KEY `idx_blacklist_ip` (`ip`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;