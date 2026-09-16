-- 018 承接已执行 006 之后的结构完整性修复；兼容 MySQL 5.7 与简单分号分割器。
SET @schema_name = DATABASE();

-- 006 成功后遗留的临时 guard 对象不再需要；MigrationService 仅对白名单名称放行。
DROP TRIGGER IF EXISTS `schema_integrity_guard_006`;
DROP TABLE IF EXISTS `fun_schema_integrity_guard_006`;

-- 已存在的会员分组关联表必须满足列、复合主键、索引及两端外键的完整语义。
SET @relation_schema_valid = (
  SELECT
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'member_id' AND COLUMN_TYPE = 'mediumint unsigned' AND IS_NULLABLE = 'NO')
    AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'group_id' AND COLUMN_TYPE = 'int unsigned' AND IS_NULLABLE = 'NO')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND CONSTRAINT_NAME = 'PRIMARY' GROUP BY CONSTRAINT_NAME HAVING COUNT(*) = 2 AND MAX(IF(ORDINAL_POSITION = 1 AND COLUMN_NAME = 'member_id', 1, 0)) = 1 AND MAX(IF(ORDINAL_POSITION = 2 AND COLUMN_NAME = 'group_id', 1, 0)) = 1)
    AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING COUNT(*) = 2 AND MAX(IF(SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'member_id', 1, 0)) = 1 AND MAX(IF(SEQ_IN_INDEX = 2 AND COLUMN_NAME = 'group_id', 1, 0)) = 1)
    AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' GROUP BY INDEX_NAME HAVING MAX(IF(SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'group_id', 1, 0)) = 1)
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'member_id' AND REFERENCED_TABLE_NAME = 'fun_member' AND REFERENCED_COLUMN_NAME = 'id')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'group_id' AND REFERENCED_TABLE_NAME = 'fun_member_group' AND REFERENCED_COLUMN_NAME = 'id')
);
SET @sql = IF(@relation_schema_valid, 'DO 0', 'SELECT * FROM `schema_integrity_error_018_member_group_relation`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 空白手机号统一为 NULL，允许多个“未填写”记录共存。
UPDATE `fun_member` SET `mobile` = NULL WHERE TRIM(COALESCE(`mobile`, '')) = '';
SET @mobile_duplicate_exists = EXISTS(SELECT 1 FROM `fun_member` WHERE `mobile` IS NOT NULL GROUP BY `mobile` HAVING COUNT(*) > 1);
SET @sql = IF(@mobile_duplicate_exists, 'SELECT * FROM `schema_integrity_error_018_member_mobile_duplicate`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @mobile_unique_exists = EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND NON_UNIQUE = 0 AND COLUMN_NAME = 'mobile');
SET @sql = IF(@mobile_unique_exists, 'DO 0', 'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_mobile` (`mobile`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- level_id 外键只在无孤儿数据时增加，删除等级时采用 RESTRICT。
SET @level_orphan_exists = EXISTS(SELECT 1 FROM `fun_member` member LEFT JOIN `fun_member_level` level ON level.`id` = member.`level_id` WHERE member.`level_id` > 0 AND level.`id` IS NULL);
SET @sql = IF(@level_orphan_exists, 'SELECT * FROM `schema_integrity_error_018_member_level_orphan`', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @level_fk_exists = EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND COLUMN_NAME = 'level_id' AND REFERENCED_TABLE_NAME = 'fun_member_level');
SET @sql = IF(@level_fk_exists, 'DO 0', 'ALTER TABLE `fun_member` ADD CONSTRAINT `fk_member_level` FOREIGN KEY (`level_id`) REFERENCES `fun_member_level` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
