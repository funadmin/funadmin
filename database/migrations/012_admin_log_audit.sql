-- 操作日志审计字段与查询索引，只向前兼容增强。

SET @schema_name = DATABASE();
SET @table_name = 'fun_admin_log';

SET @module_exists = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'module');
SET @app_name_exists = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'app_name');
SET @sql = IF(@module_exists AND NOT @app_name_exists, 'ALTER TABLE `fun_admin_log` CHANGE COLUMN `module` `app_name` varchar(50) NOT NULL DEFAULT '''' COMMENT ''应用标识''', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @plugins_exists = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'plugins');
SET @source_name_exists = EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'source_name');
SET @sql = IF(@plugins_exists AND NOT @source_name_exists, 'ALTER TABLE `fun_admin_log` CHANGE COLUMN `plugins` `source_name` varchar(100) NOT NULL DEFAULT ''core'' COMMENT ''来源标识''', 'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'source_type'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD COLUMN `source_type` varchar(20) NOT NULL DEFAULT ''system'' COMMENT ''来源类型'' AFTER `app_name`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `fun_admin_log`
SET `source_type` = CASE WHEN `source_name` IS NULL OR TRIM(`source_name`) = '' OR `source_name` = 'app' THEN 'system' ELSE 'plugin' END,
    `source_name` = CASE WHEN `source_name` IS NULL OR TRIM(`source_name`) = '' OR `source_name` = 'app' THEN 'core' ELSE `source_name` END;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'response_code'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD COLUMN `response_code` smallint unsigned NOT NULL DEFAULT 200 COMMENT ''HTTP响应状态码'' AFTER `status`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'duration_ms'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD COLUMN `duration_ms` int unsigned NOT NULL DEFAULT 0 COMMENT ''请求耗时毫秒'' AFTER `response_code`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'request_id'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD COLUMN `request_id` varchar(64) NOT NULL DEFAULT '''' COMMENT ''请求追踪标识'' AFTER `duration_ms`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'error_message'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD COLUMN `error_message` varchar(500) NOT NULL DEFAULT '''' COMMENT ''失败摘要'' AFTER `request_id`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `fun_admin_log`
SET `app_name` = COALESCE(`app_name`, ''),
    `source_type` = COALESCE(NULLIF(`source_type`, ''), 'system'),
    `source_name` = COALESCE(NULLIF(`source_name`, ''), 'core'),
    `admin_id` = COALESCE(`admin_id`, 0),
    `method` = COALESCE(`method`, ''),
    `controller` = COALESCE(`controller`, ''),
    `action` = COALESCE(`action`, ''),
    `title` = COALESCE(`title`, ''),
    `url` = COALESCE(`url`, ''),
    `ip` = COALESCE(`ip`, ''),
    `status` = COALESCE(`status`, 1),
    `create_time` = COALESCE(`create_time`, 0);

ALTER TABLE `fun_admin_log`
  MODIFY COLUMN `app_name` varchar(50) NOT NULL DEFAULT '' COMMENT '应用标识',
  MODIFY COLUMN `source_type` varchar(20) NOT NULL DEFAULT 'system' COMMENT '来源类型',
  MODIFY COLUMN `source_name` varchar(100) NOT NULL DEFAULT 'core' COMMENT '来源标识',
  MODIFY COLUMN `admin_id` int unsigned NOT NULL DEFAULT 0 COMMENT '管理员ID',
  MODIFY COLUMN `method` varchar(10) NOT NULL DEFAULT '' COMMENT 'HTTP方法',
  MODIFY COLUMN `controller` varchar(100) NOT NULL DEFAULT '' COMMENT '控制器',
  MODIFY COLUMN `action` varchar(50) NOT NULL DEFAULT '' COMMENT '动作',
  MODIFY COLUMN `title` varchar(200) NOT NULL DEFAULT '' COMMENT '操作说明',
  MODIFY COLUMN `url` varchar(500) NOT NULL DEFAULT '' COMMENT '请求地址',
  MODIFY COLUMN `ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT '' COMMENT 'IP地址',
  MODIFY COLUMN `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否成功',
  MODIFY COLUMN `create_time` int unsigned NOT NULL DEFAULT 0 COMMENT '日志时间';

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'idx_admin_log_admin_time'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD KEY `idx_admin_log_admin_time` (`admin_id`,`create_time`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'idx_admin_log_app_time'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD KEY `idx_admin_log_app_time` (`app_name`,`create_time`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'idx_admin_log_source_time'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD KEY `idx_admin_log_source_time` (`source_type`,`source_name`,`create_time`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'idx_admin_log_status_time'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD KEY `idx_admin_log_status_time` (`status`,`create_time`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'idx_admin_log_create_time'), 'DO 0', 'ALTER TABLE `fun_admin_log` ADD KEY `idx_admin_log_create_time` (`create_time`)');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
