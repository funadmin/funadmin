-- 权限与菜单表中的 module 实际表示 ThinkPHP 应用标识，统一改为 app_name。
-- uk_menu_location 唯一索引随 CHANGE COLUMN 自动保留并切换到 app_name。
ALTER TABLE `fun_permission`
  CHANGE COLUMN `module` `app_name` varchar(50) NOT NULL DEFAULT 'console' COMMENT 'ThinkPHP应用标识';

ALTER TABLE `fun_admin_menu`
  CHANGE COLUMN `module` `app_name` varchar(50) NOT NULL DEFAULT 'console' COMMENT 'ThinkPHP应用标识';
