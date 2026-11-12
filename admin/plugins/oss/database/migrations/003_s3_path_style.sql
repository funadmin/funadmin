-- 003 oss plugin S3-compatible provider (1.2.0):
-- oss_profile gets `path_style` TINYINT(1) NOT NULL DEFAULT 0 (address the bucket by path instead of by virtual
-- host; used by the `s3` provider for MinIO and similar services, always 0 for the other providers).
-- Existing rows keep their values and get path_style = 0. No other table changes; oss_setting is untouched.
-- The statement is guarded by information_schema and can be re-run; one ALTER, executed with PREPARE.

-- fun_oss_profile
SET @oss_s3_ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='fun_oss_profile' AND COLUMN_NAME='path_style')=0,
  'ALTER TABLE `fun_oss_profile` ADD COLUMN `path_style` TINYINT(1) NOT NULL DEFAULT 0', 'DO 0');
PREPARE oss_s3_stmt FROM @oss_s3_ddl;
EXECUTE oss_s3_stmt;
DEALLOCATE PREPARE oss_s3_stmt;
