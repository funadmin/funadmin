-- 014 承接已执行 006 之后的结构完整性修复；兼容 MySQL 5.7 与简单分号分割器。
SET @schema_name = DATABASE();

-- 006 成功后遗留的临时 guard 对象不再需要；MigrationService 仅对白名单名称放行。
DROP TRIGGER IF EXISTS `schema_integrity_guard_006`;
DROP TABLE IF EXISTS `fun_schema_integrity_guard_006`;

-- 已存在的会员分组关联表必须满足列、复合主键、索引及两端外键的完整语义。
SET @relation_schema_valid = (
  SELECT
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'member_id' AND COLUMN_TYPE = 'mediumint unsigned' AND IS_NULLABLE = 'NO')
    AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'group_id' AND COLUMN_TYPE = 'int unsigned' AND IS_NULLABLE = 'NO')
    AND 2 = (SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND CONSTRAINT_NAME = 'PRIMARY' AND COLUMN_NAME IN ('member_id', 'group_id'))
    AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING COUNT(*) = 2 AND MIN(IF(SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'member_id', 1, 0)) = 1 AND MAX(IF(SEQ_IN_INDEX = 2 AND COLUMN_NAME = 'group_id', 1, 0)) = 1)
    AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' GROUP BY INDEX_NAME HAVING MIN(IF(SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'group_id', 1, 0)) = 1)
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member_group_relation' AND k.COLUMN_NAME = 'member_id' AND k.REFERENCED_TABLE_NAME = 'fun_member' AND k.REFERENCED_COLUMN_NAME = 'id' AND r.DELETE_RULE = 'CASCADE')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member_group_relation' AND k.COLUMN_NAME = 'group_id' AND k.REFERENCED_TABLE_NAME = 'fun_member_group' AND k.REFERENCED_COLUMN_NAME = 'id' AND r.DELETE_RULE = 'RESTRICT')
);
SET @sql = IF(@relation_schema_valid, 'DO 0', 'SELECT * FROM `schema_integrity_error_014_invalid_member_group_relation_schema`');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 空白手机号不具备唯一值语义，先归一为 NULL，再允许空值并治理唯一索引。
UPDATE `fun_member` SET `mobile` = NULL WHERE TRIM(COALESCE(`mobile`, '')) = '';
ALTER TABLE `fun_member` MODIFY COLUMN `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号';
SET @mobile_unique_exists = EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING COUNT(*) = 1 AND MIN(COLUMN_NAME = 'mobile') = 1);
SET @sql = IF(@mobile_unique_exists, 'DO 0', IF(EXISTS(SELECT 1 FROM `fun_member` WHERE `mobile` IS NOT NULL GROUP BY `mobile` HAVING COUNT(*) > 1), 'SELECT * FROM `schema_integrity_error_014_duplicate_member_mobile`', 'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_mobile` (`mobile`)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- level_id 必须能引用会员等级；任何历史孤儿值均明确中止，不静默修复。
SET @orphan_member_level_count = (SELECT COUNT(*) FROM `fun_member` m LEFT JOIN `fun_member_level` l ON l.`id` = m.`level_id` WHERE l.`id` IS NULL);
SET @sql = IF(@orphan_member_level_count > 0, 'SELECT * FROM `schema_integrity_error_014_orphan_member_level`', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
ALTER TABLE `fun_member` MODIFY COLUMN `level_id` int unsigned NOT NULL DEFAULT 1 COMMENT '会员等级';
SET @level_fk_exists = EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member' AND k.COLUMN_NAME = 'level_id' AND k.REFERENCED_TABLE_NAME = 'fun_member_level' AND k.REFERENCED_COLUMN_NAME = 'id' AND r.DELETE_RULE = 'RESTRICT');
SET @level_other_fk_exists = EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND COLUMN_NAME = 'level_id' AND REFERENCED_TABLE_NAME IS NOT NULL);
SET @sql = IF(@level_fk_exists, 'DO 0', IF(@level_other_fk_exists, 'SELECT * FROM `schema_integrity_error_014_invalid_member_level_foreign_key`', 'ALTER TABLE `fun_member` ADD CONSTRAINT `fk_member_level` FOREIGN KEY (`level_id`) REFERENCES `fun_member_level` (`id`) ON DELETE RESTRICT'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 配置分组唯一性按单列唯一语义判断，避免只依赖固定索引名。
SET @config_group_unique_exists = EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_config_group' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING COUNT(*) = 1 AND MIN(COLUMN_NAME = 'name') = 1);
SET @sql = IF(@config_group_unique_exists, 'DO 0', IF(EXISTS(SELECT 1 FROM `fun_config_group` GROUP BY `name` HAVING COUNT(*) > 1), 'SELECT * FROM `schema_integrity_error_014_duplicate_config_group_name`', 'ALTER TABLE `fun_config_group` ADD UNIQUE KEY `uk_config_group_name` (`name`)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 普通索引按首列查询语义判断，不依赖历史索引名称。
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_attach' AND SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'group_id'), 'DO 0', 'ALTER TABLE `fun_attach` ADD KEY `idx_attach_group` (`group_id`)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_attach' AND SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'path'), 'DO 0', 'ALTER TABLE `fun_attach` ADD KEY `idx_attach_path` (`path`)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_blacklist' AND SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'ip'), 'DO 0', 'ALTER TABLE `fun_blacklist` ADD KEY `idx_blacklist_ip` (`ip`)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;