-- 基于现有数据库的增量字段治理；保留 fun_member.group_id 兼容列但不再作为业务来源。
SET @schema_name = DATABASE();

-- 校验失败时执行一个明确命名且必定不存在的表查询，不创建任何持久 guard 对象。
SET @invalid_member_group_count = (
  SELECT COUNT(*) FROM `fun_member`
  WHERE `group_id` IS NULL
     OR TRIM(`group_id`) = ''
     OR `group_id` NOT REGEXP '^[1-9][0-9]*(,[1-9][0-9]*)*$'
);
SET @sql = IF(@invalid_member_group_count > 0,
  'SELECT * FROM `schema_integrity_error_006_invalid_or_empty_member_group`', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @duplicate_member_group_count = (
  SELECT COUNT(*) FROM (
    SELECT m.`id`, SUBSTRING_INDEX(SUBSTRING_INDEX(m.`group_id`, ',', seq.n), ',', -1) AS `group_token`
    FROM `fun_member` m
    JOIN (
      SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
      UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
      UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
      UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32
    ) seq ON seq.n <= 1 + LENGTH(m.`group_id`) - LENGTH(REPLACE(m.`group_id`, ',', ''))
    GROUP BY m.`id`, `group_token`
    HAVING COUNT(*) > 1
  ) duplicated_groups
);
SET @sql = IF(@duplicate_member_group_count > 0,
  'SELECT * FROM `schema_integrity_error_006_duplicate_member_group_token`', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @missing_member_group_count = (
  SELECT COUNT(*) FROM `fun_member` m
  JOIN (
    SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
    UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
    UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
    UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32
  ) seq ON seq.n <= 1 + LENGTH(m.`group_id`) - LENGTH(REPLACE(m.`group_id`, ',', ''))
  LEFT JOIN `fun_member_group` g
    ON g.`id` = CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(m.`group_id`, ',', seq.n), ',', -1) AS UNSIGNED)
  WHERE g.`id` IS NULL
);
SET @sql = IF(@missing_member_group_count > 0,
  'SELECT * FROM `schema_integrity_error_006_missing_member_group`', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 若关联表已存在，必须先按字段、主键、索引和外键语义校验，避免在不完整结构上写数据。
SET @relation_table_exists = EXISTS(
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation'
);
SET @relation_schema_valid = (
  SELECT
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'member_id' AND COLUMN_TYPE = 'mediumint unsigned' AND IS_NULLABLE = 'NO')
    AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'group_id' AND COLUMN_TYPE = 'int unsigned' AND IS_NULLABLE = 'NO')
    AND 2 = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND CONSTRAINT_NAME = 'PRIMARY' AND COLUMN_NAME IN ('member_id', 'group_id'))
    AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND NON_UNIQUE = 0 AND SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'member_id')
    AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'group_id')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member_group_relation' AND k.COLUMN_NAME = 'member_id' AND k.REFERENCED_TABLE_NAME = 'fun_member' AND k.REFERENCED_COLUMN_NAME = 'id' AND r.DELETE_RULE = 'CASCADE')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member_group_relation' AND k.COLUMN_NAME = 'group_id' AND k.REFERENCED_TABLE_NAME = 'fun_member_group' AND k.REFERENCED_COLUMN_NAME = 'id' AND r.DELETE_RULE = 'RESTRICT')
);
SET @sql = IF(@relation_table_exists AND NOT @relation_schema_valid,
  'SELECT * FROM `schema_integrity_error_006_invalid_member_group_relation_schema`', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `fun_member_group_relation` (
  `member_id` mediumint unsigned NOT NULL COMMENT '会员ID',
  `group_id` int unsigned NOT NULL COMMENT '会员组ID',
  PRIMARY KEY (`member_id`,`group_id`),
  KEY `idx_member_group_relation_group` (`group_id`,`member_id`),
  CONSTRAINT `fk_member_group_relation_member` FOREIGN KEY (`member_id`) REFERENCES `fun_member` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_group_relation_group` FOREIGN KEY (`group_id`) REFERENCES `fun_member_group` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员与会员组关联';

-- 首次创建后同样校验，确保 DDL 实际得到业务要求的结构。
SET @created_relation_schema_valid = (
  SELECT
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'member_id' AND COLUMN_TYPE = 'mediumint unsigned' AND IS_NULLABLE = 'NO')
    AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'group_id' AND COLUMN_TYPE = 'int unsigned' AND IS_NULLABLE = 'NO')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member_group_relation' AND k.COLUMN_NAME = 'member_id' AND k.REFERENCED_TABLE_NAME = 'fun_member' AND r.DELETE_RULE = 'CASCADE')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member_group_relation' AND k.COLUMN_NAME = 'group_id' AND k.REFERENCED_TABLE_NAME = 'fun_member_group' AND r.DELETE_RULE = 'RESTRICT')
);
SET @sql = IF(@created_relation_schema_valid, 'DO 0',
  'SELECT * FROM `schema_integrity_error_006_invalid_created_relation_schema`');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT INTO `fun_member_group_relation` (`member_id`,`group_id`)
SELECT m.`id`, CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(m.`group_id`, ',', seq.n), ',', -1) AS UNSIGNED)
FROM `fun_member` m
JOIN (
  SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
  UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
  UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32
) seq ON seq.n <= 1 + LENGTH(m.`group_id`) - LENGTH(REPLACE(m.`group_id`, ',', ''))
ON DUPLICATE KEY UPDATE `group_id` = VALUES(`group_id`);

ALTER TABLE `fun_member` MODIFY COLUMN `level_id` int unsigned NOT NULL DEFAULT 1 COMMENT '会员等级';
UPDATE `fun_member` SET `mobile` = NULL WHERE TRIM(COALESCE(`mobile`, '')) = '';
ALTER TABLE `fun_member` MODIFY COLUMN `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号';
UPDATE `fun_member` SET `email` = NULL WHERE TRIM(COALESCE(`email`, '')) = '';
ALTER TABLE `fun_member` MODIFY COLUMN `email` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮件';
ALTER TABLE `fun_member` MODIFY COLUMN `last_ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT '最后登录IP';
ALTER TABLE `fun_admin` MODIFY COLUMN `email` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮箱';
ALTER TABLE `fun_admin` MODIFY COLUMN `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT 'IP地址';
ALTER TABLE `fun_auth_group` MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态';
ALTER TABLE `fun_field_verify` MODIFY COLUMN `verify` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '验证规则';

-- 唯一索引按字段语义判断；历史重复数据通过明确命名的失败查询中止。
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND NON_UNIQUE = 0 AND COLUMN_NAME = 'username' AND SEQ_IN_INDEX = 1), 'DO 0', IF(EXISTS(SELECT 1 FROM `fun_member` WHERE `username` IS NOT NULL GROUP BY `username` HAVING COUNT(*) > 1), 'SELECT * FROM `schema_integrity_error_006_duplicate_member_username`', 'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_username` (`username`)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND NON_UNIQUE = 0 AND COLUMN_NAME = 'mobile' AND SEQ_IN_INDEX = 1), 'DO 0', IF(EXISTS(SELECT 1 FROM `fun_member` WHERE `mobile` IS NOT NULL GROUP BY `mobile` HAVING COUNT(*) > 1), 'SELECT * FROM `schema_integrity_error_006_duplicate_member_mobile`', 'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_mobile` (`mobile`)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND NON_UNIQUE = 0 AND COLUMN_NAME = 'email' AND SEQ_IN_INDEX = 1), 'DO 0', IF(EXISTS(SELECT 1 FROM `fun_member` WHERE `email` IS NOT NULL GROUP BY `email` HAVING COUNT(*) > 1), 'SELECT * FROM `schema_integrity_error_006_duplicate_member_email`', 'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_email` (`email`)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_config_group' AND NON_UNIQUE = 0 AND COLUMN_NAME = 'name' AND SEQ_IN_INDEX = 1), 'DO 0', IF(EXISTS(SELECT 1 FROM `fun_config_group` GROUP BY `name` HAVING COUNT(*) > 1), 'SELECT * FROM `schema_integrity_error_006_duplicate_config_group_name`', 'ALTER TABLE `fun_config_group` ADD UNIQUE KEY `uk_config_group_name` (`name`)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_attach' AND COLUMN_NAME = 'group_id' AND SEQ_IN_INDEX = 1), 'DO 0', 'ALTER TABLE `fun_attach` ADD KEY `idx_attach_group` (`group_id`)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_attach' AND COLUMN_NAME = 'path' AND SEQ_IN_INDEX = 1), 'DO 0', 'ALTER TABLE `fun_attach` ADD KEY `idx_attach_path` (`path`)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_blacklist' AND COLUMN_NAME = 'ip' AND SEQ_IN_INDEX = 1), 'DO 0', 'ALTER TABLE `fun_blacklist` ADD KEY `idx_blacklist_ip` (`ip`)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
