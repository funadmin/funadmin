-- 014 字段命名治理：仅以 title 作为名称的五张表统一改名为 name。
-- 涉及：fun_admin_menu / fun_permission / fun_auth_group / fun_attach_group / fun_admin_log。
-- 约束：forward-only；每处改名均由 information_schema 守卫，重复执行自动跳过；无破坏性语句。

SET @schema_name = DATABASE();

-- 菜单表：菜单标题即菜单名称。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_admin_menu' AND COLUMN_NAME = 'title'),
  'ALTER TABLE `fun_admin_menu` CHANGE COLUMN `title` `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '''' COMMENT ''菜单名称''',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 权限资源表：权限标题即权限名称。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_permission' AND COLUMN_NAME = 'title'),
  'ALTER TABLE `fun_permission` CHANGE COLUMN `title` `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '''' COMMENT ''权限名称''',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 角色表：标题即角色名称；title 唯一索引随列同步改名为 name。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_auth_group' AND COLUMN_NAME = 'title'),
  'ALTER TABLE `fun_auth_group` CHANGE COLUMN `title` `name` char(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '''' COMMENT ''角色名称'', RENAME INDEX `title` TO `name`',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 附件分组表：分组名归一为 name。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_attach_group' AND COLUMN_NAME = 'title'),
  'ALTER TABLE `fun_attach_group` CHANGE COLUMN `title` `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '''' COMMENT ''分组名称''',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 操作日志表：日志描述归一为 name；定义与 012 的规范化口径一致（varchar(200) 非空）。
SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'fun_admin_log' AND COLUMN_NAME = 'title'),
  'ALTER TABLE `fun_admin_log` CHANGE COLUMN `title` `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '''' COMMENT ''操作说明''',
  'DO 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
