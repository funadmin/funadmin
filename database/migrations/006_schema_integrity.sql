-- 基于现有数据库的增量字段治理；保留 fun_member.group_id 兼容列但不再作为业务来源。
SET @schema_name = DATABASE();

-- 在写入关联表前严格验证所有旧分组值：不能为空，只允许正整数逗号列表且不得重复。
SET @invalid_member_group_count = (
  SELECT COUNT(*)
  FROM `fun_member`
  WHERE `group_id` IS NULL
     OR TRIM(`group_id`) = ''
     OR `group_id` NOT REGEXP '^[1-9][0-9]*(,[1-9][0-9]*)*$'
);
SET @sql = IF(
  @invalid_member_group_count > 0,
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''fun_member.group_id invalid or empty''',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @duplicate_member_group_count = (
  SELECT COUNT(*)
  FROM (
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
SET @sql = IF(
  @duplicate_member_group_count > 0,
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''fun_member.group_id contains duplicate token''',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @missing_member_group_count = (
  SELECT COUNT(*)
  FROM `fun_member` m
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
SET @sql = IF(
  @missing_member_group_count > 0,
  'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''fun_member.group_id references missing member_group''',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `fun_member_group_relation` (
  `member_id` mediumint unsigned NOT NULL COMMENT '会员ID',
  `group_id` int unsigned NOT NULL COMMENT '会员组ID',
  `create_time` int unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`member_id`,`group_id`),
  KEY `idx_member_group_relation_group` (`group_id`,`member_id`),
  CONSTRAINT `fk_member_group_relation_member` FOREIGN KEY (`member_id`) REFERENCES `fun_member` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_group_relation_group` FOREIGN KEY (`group_id`) REFERENCES `fun_member_group` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会员与会员组关联';

INSERT INTO `fun_member_group_relation` (`member_id`,`group_id`,`create_time`)
SELECT m.`id`, CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(m.`group_id`, ',', seq.n), ',', -1) AS UNSIGNED), UNIX_TIMESTAMP()
FROM `fun_member` m
JOIN (
  SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
  UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
  UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20 UNION ALL SELECT 21 UNION ALL SELECT 22 UNION ALL SELECT 23 UNION ALL SELECT 24
  UNION ALL SELECT 25 UNION ALL SELECT 26 UNION ALL SELECT 27 UNION ALL SELECT 28 UNION ALL SELECT 29 UNION ALL SELECT 30 UNION ALL SELECT 31 UNION ALL SELECT 32
) seq ON seq.n <= 1 + LENGTH(m.`group_id`) - LENGTH(REPLACE(m.`group_id`, ',', ''))
ON DUPLICATE KEY UPDATE `create_time` = `fun_member_group_relation`.`create_time`;

ALTER TABLE `fun_member` MODIFY COLUMN `level_id` int unsigned NOT NULL DEFAULT 1 COMMENT '会员等级';
UPDATE `fun_member` SET `email` = NULL WHERE TRIM(COALESCE(`email`, '')) = '';
ALTER TABLE `fun_member` MODIFY COLUMN `email` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮件';
ALTER TABLE `fun_member` MODIFY COLUMN `last_ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT '最后登录IP';
ALTER TABLE `fun_admin` MODIFY COLUMN `email` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮箱';
ALTER TABLE `fun_admin` MODIFY COLUMN `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL COMMENT 'IP地址';
ALTER TABLE `fun_auth_group` MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态';
ALTER TABLE `fun_field_verify` MODIFY COLUMN `verify` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '验证规则';

-- 每个唯一索引先跳过同名既有索引，否则重复数据必须明确中止迁移。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND INDEX_NAME = 'uk_member_username'),
  'DO 0',
  IF(
    EXISTS(SELECT 1 FROM `fun_member` WHERE `username` IS NOT NULL GROUP BY `username` HAVING COUNT(*) > 1),
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''duplicate fun_member.username''',
    'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_username` (`username`)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND INDEX_NAME = 'uk_member_mobile'),
  'DO 0',
  IF(
    EXISTS(SELECT 1 FROM `fun_member` GROUP BY `mobile` HAVING COUNT(*) > 1),
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''duplicate fun_member.mobile''',
    'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_mobile` (`mobile`)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND INDEX_NAME = 'uk_member_email'),
  'DO 0',
  IF(
    EXISTS(SELECT 1 FROM `fun_member` WHERE `email` IS NOT NULL GROUP BY `email` HAVING COUNT(*) > 1),
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''duplicate fun_member.email''',
    'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_email` (`email`)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_config_group' AND INDEX_NAME = 'uk_config_group_name'),
  'DO 0',
  IF(
    EXISTS(SELECT 1 FROM `fun_config_group` GROUP BY `name` HAVING COUNT(*) > 1),
    'SIGNAL SQLSTATE ''45000'' SET MESSAGE_TEXT = ''duplicate fun_config_group.name''',
    'ALTER TABLE `fun_config_group` ADD UNIQUE KEY `uk_config_group_name` (`name`)'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_attach' AND INDEX_NAME = 'idx_attach_group'),
  'DO 0',
  'ALTER TABLE `fun_attach` ADD KEY `idx_attach_group` (`group_id`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_attach' AND INDEX_NAME = 'idx_attach_path'),
  'DO 0',
  'ALTER TABLE `fun_attach` ADD KEY `idx_attach_path` (`path`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_blacklist' AND INDEX_NAME = 'idx_blacklist_ip'),
  'DO 0',
  'ALTER TABLE `fun_blacklist` ADD KEY `idx_blacklist_ip` (`ip`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
