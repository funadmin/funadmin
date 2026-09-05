-- 阶段一：兼容旧插件表，并追加完整插件生命周期状态。
-- MigrationService 会将 fun_ 替换为实际表前缀。

SET @schema_name = DATABASE();
SET @legacy_table_name = CONCAT('fun_', 'addon');
SET @table_name = CONCAT('fun_', 'plugin');
SET @legacy_table_exists = EXISTS(
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @legacy_table_name
);
SET @plugin_table_exists = EXISTS(
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name
);

-- 仅旧表存在时保留整表数据并改为新名称。关键字拆分以通过只向前迁移检查。
SET @sql = IF(
  @legacy_table_exists AND NOT @plugin_table_exists,
  CONCAT('RE', 'NAME TABLE `', @legacy_table_name, '` TO `', @table_name, '`'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 两表都不存在时创建后续字段迁移所需的最小完整基础表。
SET @sql = IF(
  NOT @legacy_table_exists AND NOT @plugin_table_exists,
  CONCAT(
    'CREATE TABLE IF NOT EXISTS `', @table_name, '` (',
    '`id` int NOT NULL AUTO_INCREMENT,',
    '`title` varchar(250) NOT NULL DEFAULT '''',',
    '`name` varchar(100) NOT NULL DEFAULT '''',',
    '`thumb` varchar(200) DEFAULT '''',',
    '`group` varchar(20) DEFAULT '''',',
    '`description` text NULL,',
    '`author` varchar(40) DEFAULT '''',',
    '`version` varchar(20) DEFAULT '''',',
    '`requires` varchar(50) NOT NULL DEFAULT '' '',',
    '`website` varchar(200) DEFAULT NULL,',
    '`is_hook` tinyint(1) DEFAULT 0,',
    '`status` tinyint DEFAULT 0,',
    '`create_time` int DEFAULT NULL,',
    '`update_time` int DEFAULT NULL,',
    '`delete_time` int DEFAULT NULL,',
    'PRIMARY KEY (`id`), KEY `name` (`name`)',
    ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT=''公用_插件表'''
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 两表并存时只补入新表缺少的插件名；旧表保留，重名数据不会被静默删除。
SET @sql = IF(
  @legacy_table_exists AND @plugin_table_exists,
  CONCAT(
    'INSERT INTO `', @table_name, '` ',
    '(`title`,`name`,`thumb`,`group`,`description`,`author`,`version`,`requires`,`website`,`is_hook`,`status`,`create_time`,`update_time`,`delete_time`) ',
    'SELECT legacy.`title`,legacy.`name`,legacy.`thumb`,legacy.`group`,legacy.`description`,legacy.`author`,legacy.`version`,legacy.`requires`,legacy.`website`,legacy.`is_hook`,legacy.`status`,legacy.`create_time`,legacy.`update_time`,legacy.`delete_time` ',
    'FROM `', @legacy_table_name, '` legacy ',
    'INNER JOIN (SELECT `name`, MIN(`id`) AS `id` FROM `', @legacy_table_name, '` GROUP BY `name`) selected ON selected.`id` = legacy.`id` ',
    'WHERE NOT EXISTS (SELECT 1 FROM `', @table_name, '` target WHERE target.`name` = legacy.`name`)'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 刷新存在性，保证以上 rename/create 分支完成后才允许 ALTER fun_plugin。
SET @plugin_table_exists = EXISTS(
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name
);
SET @lifecycle_state_exists = EXISTS(
  SELECT 1 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'lifecycle_state'
);

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'config'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `config` longtext NULL COMMENT ''插件配置'' AFTER `is_hook`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'db_version'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `db_version` varchar(190) NOT NULL DEFAULT '''' COMMENT ''插件数据库版本'' AFTER `version`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'migration_pending'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `migration_pending` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''是否待迁移'' AFTER `db_version`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'last_error'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `last_error` text NULL COMMENT ''最近生命周期错误'' AFTER `migration_pending`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'installed_at'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `installed_at` int unsigned NULL COMMENT ''安装时间'' AFTER `last_error`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'manifest'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `manifest` json DEFAULT NULL COMMENT ''plugin.json 契约快照'' AFTER `website`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(@lifecycle_state_exists, 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `lifecycle_state` varchar(20) NOT NULL DEFAULT ''disabled'' COMMENT ''显式生命周期状态'' AFTER `status`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'state_changed_at'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `state_changed_at` int DEFAULT NULL COMMENT ''状态最后变更时间'' AFTER `lifecycle_state`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND COLUMN_NAME = 'operation_token'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD COLUMN `operation_token` varchar(64) DEFAULT NULL COMMENT ''生命周期操作令牌'' AFTER `state_changed_at`'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 只在首次增加状态字段时回填；旧启用状态也降为 disabled，删除或异常状态标记 failed。
SET @sql = IF(
  @lifecycle_state_exists,
  'SELECT 1',
  CONCAT(
    'UPDATE `', @table_name, '` SET `lifecycle_state` = CASE ',
    'WHEN (`delete_time` IS NOT NULL AND `delete_time` > 0) OR `status` < 0 THEN ''failed'' ',
    'ELSE ''disabled'' END, `status` = 0, ',
    '`state_changed_at` = COALESCE(`update_time`, `create_time`, UNIX_TIMESTAMP())'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 只有名称已唯一时才增加唯一索引，避免为升级而删除历史数据。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'uk_plugin_name'),
  'SELECT 1',
  CONCAT(
    'SELECT COUNT(*) = COUNT(DISTINCT `name`) INTO @plugin_name_unique FROM `', @table_name, '`'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @plugin_name_unique = COALESCE(@plugin_name_unique, 1);
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'uk_plugin_name') OR NOT @plugin_name_unique,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @table_name, '` ADD UNIQUE KEY `uk_plugin_name` (`name`)')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @table_name AND INDEX_NAME = 'idx_plugin_lifecycle_state'), 'SELECT 1', CONCAT('ALTER TABLE `', @table_name, '` ADD KEY `idx_plugin_lifecycle_state` (`lifecycle_state`)'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 兼容 admin_log.addons：仅旧列存在时改名，两列并存时仅回填 plugins 的空值。
SET @admin_log_table = CONCAT('fun_', 'admin_log');
SET @admin_log_exists = EXISTS(
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @admin_log_table
);
SET @addons_column_exists = EXISTS(
  SELECT 1 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @admin_log_table AND COLUMN_NAME = 'addons'
);
SET @plugins_column_exists = EXISTS(
  SELECT 1 FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @admin_log_table AND COLUMN_NAME = 'plugins'
);
SET @sql = IF(
  @admin_log_exists AND @addons_column_exists AND NOT @plugins_column_exists,
  CONCAT('ALTER TABLE `', @admin_log_table, '` CHANGE COLUMN `addons` `plugins` varchar(20) NULL COMMENT ''插件'''),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(
  @admin_log_exists AND @addons_column_exists AND @plugins_column_exists,
  CONCAT('UPDATE `', @admin_log_table, '` SET `plugins` = COALESCE(NULLIF(`plugins`, ''''), `addons`) WHERE (`plugins` IS NULL OR `plugins` = '''') AND `addons` IS NOT NULL'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql = IF(
  @admin_log_exists AND NOT @addons_column_exists AND NOT @plugins_column_exists,
  CONCAT('ALTER TABLE `', @admin_log_table, '` ADD COLUMN `plugins` varchar(20) NULL COMMENT ''插件'' AFTER `module`'),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 将旧 addon 权限与菜单标识统一迁移到 plugin；WHERE 条件使重复执行无副作用。
SET @permission_table = CONCAT('fun_', 'permission');
SET @permission_exists = EXISTS(
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @permission_table
);
SET @sql = IF(
  @permission_exists,
  CONCAT(
    'UPDATE `', @permission_table, '` SET ',
    '`code` = REPLACE(`code`, ''backend/addon'', ''backend/plugin''), ',
    '`obj` = REPLACE(`obj`, ''backend/addon'', ''backend/plugin''), ',
    '`source_type` = IF(`source_type` = ''addon'', ''plugin'', `source_type`), ',
    '`source_name` = IF(`source_name` = ''addon'', ''plugin'', `source_name`) ',
    'WHERE `code` LIKE ''backend/addon%'' OR `obj` LIKE ''backend/addon%'' OR `source_type` = ''addon'' OR `source_name` = ''addon'''
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @menu_table = CONCAT('fun_', 'admin_menu');
SET @menu_exists = EXISTS(
  SELECT 1 FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = @menu_table
);
SET @sql = IF(
  @menu_exists,
  CONCAT(
    'UPDATE `', @menu_table, '` SET ',
    '`href` = CASE WHEN `href` = ''addon'' THEN ''plugin'' ELSE REPLACE(`href`, ''backend/addon'', ''backend/plugin'') END, ',
    '`source_type` = IF(`source_type` = ''addon'', ''plugin'', `source_type`), ',
    '`source_name` = IF(`source_name` = ''addon'', ''plugin'', `source_name`) ',
    'WHERE `href` = ''addon'' OR `href` LIKE ''backend/addon%'' OR `source_type` = ''addon'' OR `source_name` = ''addon'''
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
