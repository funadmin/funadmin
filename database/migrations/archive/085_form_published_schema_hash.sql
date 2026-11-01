-- 085 向前补充表单已发布 Schema hash；072 保持已发布 checksum 不变。
SET @schema_name=DATABASE();
SET @sql=IF(
  NOT EXISTS(
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=@schema_name AND TABLE_NAME='fun_form' AND COLUMN_NAME='published_schema_hash'
  ),
  'ALTER TABLE `fun_form` ADD COLUMN `published_schema_hash` char(64) NULL AFTER `published_definition_hash`',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
