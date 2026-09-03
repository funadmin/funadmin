-- 通用业务字典。系统配置继续由 fun_config / fun_config_group 独立维护。

CREATE TABLE IF NOT EXISTS `fun_dict_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(60) NOT NULL COMMENT '字典编码',
  `name` varchar(100) NOT NULL COMMENT '字典名称',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用',
  `sort` int unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  `delete_time` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dict_type_code` (`code`),
  KEY `idx_dict_type_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字典类型';

CREATE TABLE IF NOT EXISTS `fun_dict_item` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int unsigned NOT NULL COMMENT '字典类型ID',
  `label` varchar(100) NOT NULL COMMENT '显示标签',
  `value` varchar(100) NOT NULL COMMENT '字典值',
  `css_class` varchar(30) NOT NULL DEFAULT '' COMMENT '前端样式',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用，1启用',
  `sort` int unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  `delete_time` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dict_item_value` (`type_id`,`value`),
  KEY `idx_dict_item_type_status_sort` (`type_id`,`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='字典项';

INSERT IGNORE INTO `fun_dict_type` (`id`,`code`,`name`,`status`,`sort`,`remark`,`create_time`,`update_time`,`delete_time`) VALUES
(1, 'sys_normal_disable', '系统状态', 1, 10, '通用启用与停用状态', 0, 0, NULL),
(2, 'sys_user_sex', '用户性别', 1, 20, '用户性别选项', 0, 0, NULL),
(3, 'yes_no', '是否', 1, 30, '通用是否选项', 0, 0, NULL);

INSERT IGNORE INTO `fun_dict_item` (`id`,`type_id`,`label`,`value`,`css_class`,`status`,`sort`,`remark`,`create_time`,`update_time`,`delete_time`) VALUES
(1, 1, '正常', '1', 'success', 1, 10, '', 0, 0, NULL),
(2, 1, '停用', '0', 'danger', 1, 20, '', 0, 0, NULL),
(3, 2, '未知', '0', 'info', 1, 10, '', 0, 0, NULL),
(4, 2, '男', '1', 'primary', 1, 20, '', 0, 0, NULL),
(5, 2, '女', '2', 'danger', 1, 30, '', 0, 0, NULL),
(6, 3, '是', '1', 'success', 1, 10, '', 0, 0, NULL),
(7, 3, '否', '0', 'info', 1, 20, '', 0, 0, NULL);

-- Admin Web 字典管理权限与菜单。角色授权关系仍只写入 fun_casbin_rule。
INSERT IGNORE INTO `fun_permission` (`id`,`pid`,`module`,`code`,`obj`,`act`,`title`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`) VALUES
(179, 1, 'backend', NULL, '', '', 'Dictionary', 'group', 1, 0, 60, 'admin_web', 'dictionary', 0, NULL),
(180, 179, 'backend', 'backend/systemdict:types', 'backend/systemdict', 'types', 'List dictionary types', 'route', 1, 0, 10, 'admin_web', 'dictionary', 0, NULL),
(181, 179, 'backend', 'backend/systemdict:createtype', 'backend/systemdict', 'createtype', 'Create dictionary type', 'route', 1, 0, 20, 'admin_web', 'dictionary', 0, NULL),
(182, 179, 'backend', 'backend/systemdict:updatetype', 'backend/systemdict', 'updatetype', 'Update dictionary type', 'route', 1, 0, 30, 'admin_web', 'dictionary', 0, NULL),
(183, 179, 'backend', 'backend/systemdict:deletetype', 'backend/systemdict', 'deletetype', 'Delete dictionary type', 'route', 1, 0, 40, 'admin_web', 'dictionary', 0, NULL),
(184, 179, 'backend', 'backend/systemdict:deletetypes', 'backend/systemdict', 'deletetypes', 'Batch delete dictionary types', 'route', 1, 0, 50, 'admin_web', 'dictionary', 0, NULL),
(185, 179, 'backend', 'backend/systemdict:items', 'backend/systemdict', 'items', 'List dictionary items', 'route', 1, 0, 60, 'admin_web', 'dictionary', 0, NULL),
(186, 179, 'backend', 'backend/systemdict:createitem', 'backend/systemdict', 'createitem', 'Create dictionary item', 'route', 1, 0, 70, 'admin_web', 'dictionary', 0, NULL),
(187, 179, 'backend', 'backend/systemdict:updateitem', 'backend/systemdict', 'updateitem', 'Update dictionary item', 'route', 1, 0, 80, 'admin_web', 'dictionary', 0, NULL),
(188, 179, 'backend', 'backend/systemdict:deleteitem', 'backend/systemdict', 'deleteitem', 'Delete dictionary item', 'route', 1, 0, 90, 'admin_web', 'dictionary', 0, NULL),
(189, 179, 'backend', 'backend/systemdict:deleteitems', 'backend/systemdict', 'deleteitems', 'Batch delete dictionary items', 'route', 1, 0, 100, 'admin_web', 'dictionary', 0, NULL),
(190, 179, 'backend', 'backend/systemdict:options', 'backend/systemdict', 'options', 'Query dictionary options', 'route', 1, 0, 110, 'admin_web', 'dictionary', 0, NULL),
(191, 179, 'backend', 'backend/systemdict:batch', 'backend/systemdict', 'batch', 'Batch query dictionaries', 'route', 1, 0, 120, 'admin_web', 'dictionary', 0, NULL);

INSERT IGNORE INTO `fun_admin_menu` (`id`,`pid`,`permission_id`,`module`,`title`,`href`,`query`,`target`,`icon`,`status`,`sort`,`source_type`,`source_name`,`create_time`,`update_time`) VALUES
(20, 0, 179, 'backend', 'Dictionary', 'system/dict', '', '_self', 'i-ep-collection', 1, 60, 'admin_web', 'dictionary', 0, NULL);
