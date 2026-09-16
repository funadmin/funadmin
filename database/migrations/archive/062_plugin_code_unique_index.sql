-- 061 已发布后的插件 code 唯一索引补偿：清理 legacy 重复唯一索引，仅保留 uk_plugin_code。
SET @schema_name = DATABASE();
SET @table_name = 'fun_plugin';

SET @valid_uk_plugin_code = (
    SELECT COUNT(*) = 1
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = @table_name
      AND INDEX_NAME = 'uk_plugin_code'
      AND NON_UNIQUE = 0
      AND COLUMN_NAME = 'code'
      AND SEQ_IN_INDEX = 1
      AND NOT EXISTS (
          SELECT 1
          FROM information_schema.STATISTICS extra
          WHERE extra.TABLE_SCHEMA = @schema_name
            AND extra.TABLE_NAME = @table_name
            AND extra.INDEX_NAME = 'uk_plugin_code'
            AND extra.SEQ_IN_INDEX > 1
      )
);
SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name
          AND INDEX_NAME = 'uk_plugin_code'
    ) AND @valid_uk_plugin_code = 0,
    'ALTER TABLE `fun_plugin` DROP INDEX `uk_plugin_code`',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET SESSION group_concat_max_len = 8192;
SET @legacy_unique_indexes = (
    SELECT GROUP_CONCAT(DISTINCT CONCAT('DROP INDEX `', REPLACE(INDEX_NAME, '`', '``'), '`') ORDER BY INDEX_NAME SEPARATOR ', ')
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = @table_name
      AND COLUMN_NAME = 'code'
      AND NON_UNIQUE = 0
      AND INDEX_NAME <> 'PRIMARY'
      AND INDEX_NAME <> 'uk_plugin_code'
);
SET @sql = IF(
    @legacy_unique_indexes IS NULL OR @legacy_unique_indexes = '',
    'DO 0',
    CONCAT('ALTER TABLE `fun_plugin` ', @legacy_unique_indexes)
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_uk_plugin_code = EXISTS(
    SELECT 1 FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = @table_name
      AND INDEX_NAME = 'uk_plugin_code'
      AND NON_UNIQUE = 0
      AND COLUMN_NAME = 'code'
);
SET @sql = IF(
    @has_uk_plugin_code = 0,
    'ALTER TABLE `fun_plugin` ADD UNIQUE KEY `uk_plugin_code` (`code`)',
    'DO 0'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
