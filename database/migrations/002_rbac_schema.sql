-- 全新 RBAC：菜单、权限资源、Casbin 授权关系职责分离。

CREATE TABLE IF NOT EXISTS `fun_permission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int unsigned NOT NULL DEFAULT 0,
  `module` varchar(50) NOT NULL DEFAULT 'backend',
  `code` varchar(255) DEFAULT NULL,
  `obj` varchar(190) NOT NULL DEFAULT '',
  `act` varchar(100) NOT NULL DEFAULT '',
  `title` varchar(100) NOT NULL DEFAULT '',
  `resource_type` enum('group','route') NOT NULL DEFAULT 'route',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `sort` int NOT NULL DEFAULT 999,
  `source_type` varchar(20) NOT NULL DEFAULT 'system',
  `source_name` varchar(100) NOT NULL DEFAULT '',
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permission_code` (`code`),
  KEY `idx_permission_tree` (`pid`,`status`,`sort`),
  KEY `idx_permission_resource` (`obj`,`act`),
  KEY `idx_permission_source` (`source_type`,`source_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限资源目录';

CREATE TABLE IF NOT EXISTS `fun_admin_menu` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `pid` int unsigned NOT NULL DEFAULT 0,
  `permission_id` int unsigned NOT NULL DEFAULT 0,
  `module` varchar(50) NOT NULL DEFAULT 'backend',
  `title` varchar(100) NOT NULL DEFAULT '',
  `href` varchar(255) NOT NULL DEFAULT '',
  `query` varchar(250) NOT NULL DEFAULT '',
  `target` varchar(20) NOT NULL DEFAULT '_self',
  `icon` varchar(100) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort` int NOT NULL DEFAULT 999,
  `source_type` varchar(20) NOT NULL DEFAULT 'system',
  `source_name` varchar(100) NOT NULL DEFAULT '',
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_menu_location` (`module`,`href`,`query`),
  KEY `idx_menu_tree` (`pid`,`status`,`sort`),
  KEY `idx_menu_permission` (`permission_id`),
  KEY `idx_menu_source` (`source_type`,`source_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台菜单';

CREATE TABLE IF NOT EXISTS `fun_casbin_rule` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ptype` varchar(10) NOT NULL,
  `v0` varchar(190) NOT NULL DEFAULT '',
  `v1` varchar(190) NOT NULL DEFAULT '',
  `v2` varchar(190) NOT NULL DEFAULT '',
  `v3` varchar(190) NOT NULL DEFAULT '',
  `v4` varchar(190) NOT NULL DEFAULT '',
  `v5` varchar(190) NOT NULL DEFAULT '',
  `rule_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rule_hash` (`rule_hash`),
  KEY `idx_subject_domain` (`ptype`,`v0`,`v1`),
  KEY `idx_object_action` (`ptype`,`v2`,`v3`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Casbin授权策略';

INSERT IGNORE INTO `fun_permission` (`id`,`pid`,`module`,`code`,`obj`,`act`,`title`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`) VALUES
(1, 0, 'backend', NULL, '', '', 'Sys', 'group', 1, 0, 0, 'system', 'core', 0, NULL),
(37, 1, 'backend', NULL, '', '', 'Attach', 'group', 1, 0, 50, 'system', 'core', 0, NULL),
(38, 37, 'backend', 'backend/sys.attach:index', 'backend/sys.attach', 'index', 'List', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(39, 37, 'backend', 'backend/sys.attach:add', 'backend/sys.attach', 'add', 'Add', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(40, 37, 'backend', 'backend/sys.attach:delete', 'backend/sys.attach', 'delete', 'Delete', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(44, 1, 'backend', 'backend/ajax:uploads', 'backend/ajax', 'uploads', 'Uploads', 'route', 1, 0, 0, 'system', 'core', 0, NULL),
(64, 0, 'backend', NULL, '', '', 'Plugin', 'group', 1, 0, 501, 'system', 'core', 0, NULL),
(65, 64, 'backend', 'backend/plugin:index', 'backend/plugin', 'index', 'List', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(72, 64, 'backend', 'backend/plugin:install', 'backend/plugin', 'install', 'Install', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(73, 64, 'backend', 'backend/plugin:modify', 'backend/plugin', 'modify', 'modify', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(74, 64, 'backend', 'backend/plugin:config', 'backend/plugin', 'config', 'Config', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(76, 1, 'backend', NULL, '', '', 'upgrade', 'group', 1, 0, 50, 'system', 'core', 0, NULL),
(77, 76, 'backend', 'backend/sys.upgrade:index', 'backend/sys.upgrade', 'index', 'list', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(78, 76, 'backend', 'backend/sys.upgrade:check', 'backend/sys.upgrade', 'check', 'check', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(79, 76, 'backend', 'backend/sys.upgrade:backup', 'backend/sys.upgrade', 'backup', 'backup', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(80, 76, 'backend', 'backend/sys.upgrade:install', 'backend/sys.upgrade', 'install', 'install', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(169, 37, 'backend', 'backend/sys.attach:edit', 'backend/sys.attach', 'edit', 'Edit', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(170, 37, 'backend', 'backend/sys.attach:move', 'backend/sys.attach', 'move', 'Move', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(171, 37, 'backend', 'backend/sys.attach:selectfiles', 'backend/sys.attach', 'selectfiles', 'selectfiles', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(172, 176, 'backend', 'backend/sys.attachgroup:add', 'backend/sys.attachgroup', 'add', 'add', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(173, 176, 'backend', 'backend/sys.attachgroup:edit', 'backend/sys.attachgroup', 'edit', 'edit', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(174, 176, 'backend', 'backend/sys.attachgroup:delete', 'backend/sys.attachgroup', 'delete', 'delete', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(175, 64, 'backend', 'backend/plugin:add', 'backend/plugin', 'add', 'Install', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(176, 1, 'backend', NULL, '', '', 'attachGroup', 'group', 1, 0, 99, 'system', 'core', 0, NULL),
(177, 176, 'backend', 'backend/sys.attachgroup:destroy', 'backend/sys.attachgroup', 'destroy', 'destroy', 'route', 1, 0, 50, 'system', 'core', 0, NULL),
(178, 176, 'backend', 'backend/sys.attachgroup:recycle', 'backend/sys.attachgroup', 'recycle', 'recycle', 'route', 1, 0, 50, 'system', 'core', 0, NULL);

INSERT IGNORE INTO `fun_admin_menu` (`id`,`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`) VALUES
(1, 0, 1, 'backend', 'Sys', 'sys', '', '_self', 'layui-icon layui-icon-home', 1, 0, 'system', 'core', 0, NULL),
(15, 0, 64, 'backend', 'Plugin', 'plugin', '', '_self', 'layui-icon layui-icon-app', 1, 501, 'system', 'core', 0, NULL),
(17, 1, 76, 'backend', 'upgrade', 'sys.upgrade', '', '_self', 'layui-icon layui-icon-refresh-1', 1, 50, 'system', 'core', 0, NULL);

INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`) VALUES
('g','admin:1','role:1','default','','','', '19ce2ecadf0a1cf41bc4247609cb7af71f96ad6e0db663c9ba65a4bb501eabed');
