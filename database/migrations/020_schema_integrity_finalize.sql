-- 020 最终验证并补齐结构完整性；018 已完成时仅安全跳过或复核。
SET @schema_name = DATABASE();

-- 清理 006 成功后遗留的白名单 guard 对象。
DROP TRIGGER IF EXISTS `schema_integrity_guard_006`;
DROP TABLE IF EXISTS `fun_schema_integrity_guard_006`;

-- 关联表必须具备精确列类型、复合主键、查询索引及两端删除规则。
SET @relation_schema_valid = (
  SELECT
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'member_id' AND COLUMN_TYPE = 'mediumint unsigned' AND IS_NULLABLE = 'NO')
    AND EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND COLUMN_NAME = 'group_id' AND COLUMN_TYPE = 'int unsigned' AND IS_NULLABLE = 'NO')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' AND CONSTRAINT_NAME = 'PRIMARY' GROUP BY CONSTRAINT_NAME HAVING COUNT(*) = 2 AND MAX(IF(ORDINAL_POSITION = 1 AND COLUMN_NAME = 'member_id', 1, 0)) = 1 AND MAX(IF(ORDINAL_POSITION = 2 AND COLUMN_NAME = 'group_id', 1, 0)) = 1)
    AND EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member_group_relation' GROUP BY INDEX_NAME HAVING MAX(IF(SEQ_IN_INDEX = 1 AND COLUMN_NAME = 'group_id', 1, 0)) = 1)
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member_group_relation' AND k.COLUMN_NAME = 'member_id' AND k.REFERENCED_TABLE_NAME = 'fun_member' AND k.REFERENCED_COLUMN_NAME = 'id' AND r.DELETE_RULE = 'CASCADE')
    AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member_group_relation' AND k.COLUMN_NAME = 'group_id' AND k.REFERENCED_TABLE_NAME = 'fun_member_group' AND k.REFERENCED_COLUMN_NAME = 'id' AND r.DELETE_RULE = 'RESTRICT')
);
SET @sql = IF(@relation_schema_valid, 'DO 0', 'SELECT * FROM `schema_integrity_error_020_invalid_member_group_relation_schema`');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 验证规则表必须保留 verify varchar(50) 单列主键。
SET @field_verify_schema_valid = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_field_verify' AND COLUMN_NAME = 'verify' AND COLUMN_TYPE = 'varchar(50)' AND IS_NULLABLE = 'NO') AND EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_field_verify' AND CONSTRAINT_NAME = 'PRIMARY' GROUP BY CONSTRAINT_NAME HAVING COUNT(*) = 1 AND MIN(IF(ORDINAL_POSITION = 1 AND COLUMN_NAME = 'verify', 1, 0)) = 1);
SET @sql = IF(@field_verify_schema_valid, 'DO 0', 'SELECT * FROM `schema_integrity_error_020_field_verify_verify_primary_required`');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 空白手机号无唯一值语义；先归一为 NULL，再确保列可空并严格治理单列唯一索引。
UPDATE `fun_member` SET `mobile` = NULL WHERE TRIM(COALESCE(`mobile`, '')) = '';
SET @mobile_nullable = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND COLUMN_NAME = 'mobile' AND COLUMN_TYPE = 'varchar(20)' AND IS_NULLABLE = 'YES' AND COLUMN_DEFAULT IS NULL);
SET @sql = IF(@mobile_nullable, 'DO 0', 'ALTER TABLE `fun_member` MODIFY COLUMN `mobile` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT ''手机号''');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @mobile_duplicate_exists = EXISTS(SELECT 1 FROM `fun_member` WHERE `mobile` IS NOT NULL GROUP BY `mobile` HAVING COUNT(*) > 1);
SET @sql = IF(@mobile_duplicate_exists, 'SELECT * FROM `schema_integrity_error_020_duplicate_member_mobile`', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @mobile_unique_exists = EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING COUNT(*) = 1 AND MIN(COLUMN_NAME = 'mobile') = 1);
SET @sql = IF(@mobile_unique_exists, 'DO 0', 'ALTER TABLE `fun_member` ADD UNIQUE KEY `uk_member_mobile` (`mobile`)');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- level_id 不允许孤儿值；既有外键也必须精确引用会员等级并采用 RESTRICT。
SET @level_orphan_exists = EXISTS(SELECT 1 FROM `fun_member` m LEFT JOIN `fun_member_level` l ON l.`id` = m.`level_id` WHERE l.`id` IS NULL);
SET @sql = IF(@level_orphan_exists, 'SELECT * FROM `schema_integrity_error_020_orphan_member_level`', 'DO 0');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET @level_fk_valid = EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA = @schema_name AND k.TABLE_NAME = 'fun_member' AND k.COLUMN_NAME = 'level_id' AND k.REFERENCED_TABLE_NAME = 'fun_member_level' AND k.REFERENCED_COLUMN_NAME = 'id' AND r.DELETE_RULE = 'RESTRICT');
SET @level_other_fk_exists = EXISTS(SELECT 1 FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_member' AND COLUMN_NAME = 'level_id' AND REFERENCED_TABLE_NAME IS NOT NULL);
SET @sql = IF(@level_fk_valid, 'DO 0', IF(@level_other_fk_exists, 'SELECT * FROM `schema_integrity_error_020_invalid_member_level_foreign_key`', 'ALTER TABLE `fun_member` ADD CONSTRAINT `fk_member_level` FOREIGN KEY (`level_id`) REFERENCES `fun_member_level` (`id`) ON DELETE RESTRICT'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 补齐 018 未负责的唯一性与查询索引，均按实际语义幂等判断。
SET @config_group_unique_exists = EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_config_group' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING COUNT(*) = 1 AND MIN(COLUMN_NAME = 'name') = 1);
SET @sql = IF(@config_group_unique_exists, 'DO 0', IF(EXISTS(SELECT 1 FROM `fun_config_group` GROUP BY `name` HAVING COUNT(*) > 1), 'SELECT * FROM `schema_integrity_error_020_duplicate_config_group_name`', 'ALTER TABLE `fun_config_group` ADD UNIQUE KEY `uk_config_group_name` (`name`)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
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
