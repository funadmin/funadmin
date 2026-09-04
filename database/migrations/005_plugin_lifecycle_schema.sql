-- 插件生命周期状态：配置、数据库版本、待迁移状态与错误信息。
-- 本文件只生成迁移 SQL，不由应用自动执行。

SET @schema_name = DATABASE();
SET @table_name = CONCAT('fun_', 'addon');

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'config'),
  'SELECT 1',
  CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `config` longtext NULL COMMENT ''插件配置'' AFTER `is_hook`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'db_version'),
  'SELECT 1',
  CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `db_version` varchar(190) NOT NULL DEFAULT '''' COMMENT ''插件数据库版本'' AFTER `version`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'migration_pending'),
  'SELECT 1',
  CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `migration_pending` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''是否待执行数据库迁移'' AFTER `db_version`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'last_error'),
  'SELECT 1',
  CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `last_error` text NULL COMMENT ''最近一次生命周期错误'' AFTER `migration_pending`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'installed_at'),
  'SELECT 1',
  CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `installed_at` int unsigned NULL COMMENT ''安装时间'' AFTER `last_error`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 为新增的独立数据库迁移动作登记后台权限；表前缀由 MigrationService 替换。
SET @permission_table = CONCAT('fun_', 'permission');
SET @permission_exists = EXISTS(
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @permission_table
);
SET @sql = IF(
  @permission_exists,
  CONCAT(
    'INSERT INTO `', @permission_table, '` ',
    '(`pid`,`module`,`code`,`obj`,`act`,`title`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`create_time`) ',
    'SELECT COALESCE((SELECT `id` FROM `', @permission_table, '` WHERE `module` = ''backend'' AND `resource_type` = ''group'' AND LOWER(`title`) = ''addon'' LIMIT 1), 0), ',
    '''backend'', ''backend/addon:migrate'', ''backend/addon'', ''migrate'', ''更新插件数据库'', ''route'', 1, 0, 50, ''system'', ''core'', UNIX_TIMESTAMP() ',
    'WHERE NOT EXISTS (SELECT 1 FROM `', @permission_table, '` WHERE `code` = ''backend/addon:migrate'')'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
