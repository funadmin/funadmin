-- 056 表单管理：元数据两表＋模块菜单/权限。
-- 中文统一使用 UTF-8 十六进制字面量；全部守卫式，可重跑。
SET @schema_name = DATABASE();

-- 表单定义表。
CREATE TABLE IF NOT EXISTS `fun_form` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `form_key` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '表单唯一标识',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '表单名称',
  `table_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '绑定真实表',
  `connection` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mysql' COMMENT '数据库连接',
  `source_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'created' COMMENT 'created=本模块建表 adopted=采纳已有表',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1启用',
  `list_config` json DEFAULT NULL COMMENT '列表配置(页大小/默认排序)',
  `form_config` json DEFAULT NULL COMMENT '表单配置(布局/label宽/分组)',
  `remark` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注',
  `sort_order` int NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_form_key` (`form_key`),
  KEY `idx_form_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='表单定义';

-- 表单字段定义表。
CREATE TABLE IF NOT EXISTS `fun_form_field` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '表单ID',
  `field_name` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '字段名(列名)',
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '显示名称',
  `type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'input' COMMENT '控件类型',
  `column_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '列类型(含长度精度)',
  `nullable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '可空',
  `default_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '默认值',
  `comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '列注释',
  `unsigned` tinyint(1) NOT NULL DEFAULT 0 COMMENT '无符号',
  `index_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT 'none/unique/index',
  `placeholder` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '占位提示',
  `options_source` json DEFAULT NULL COMMENT '选项来源(static/dict/relation/remote)',
  `control_props` json DEFAULT NULL COMMENT '控件props透传',
  `validate_rules` json DEFAULT NULL COMMENT '校验规则',
  `link_rules` json DEFAULT NULL COMMENT '联动规则',
  `relation_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none' COMMENT 'none/belongs_to/has_many',
  `relation_table` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '关联表',
  `relation_label_field` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '关联显示字段',
  `relation_value_field` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '关联值字段',
  `relation_multiple` tinyint(1) NOT NULL DEFAULT 0 COMMENT '关联多选',
  `relation_on_delete` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'restrict' COMMENT 'restrict/cascade/set_null',
  `list_show` tinyint(1) NOT NULL DEFAULT 1 COMMENT '列表显示',
  `list_sort` tinyint(1) NOT NULL DEFAULT 0 COMMENT '列表可排序',
  `list_filter` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '筛选类型 eq/like/range/date',
  `list_formatter` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '列格式化器',
  `list_width` int NOT NULL DEFAULT 0 COMMENT '列宽',
  `form_show` tinyint(1) NOT NULL DEFAULT 1 COMMENT '表单显示',
  `form_required` tinyint(1) NOT NULL DEFAULT 0 COMMENT '表单必填',
  `form_group` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '表单分组',
  `form_span` int NOT NULL DEFAULT 24 COMMENT '栅格span',
  `form_readonly` tinyint(1) NOT NULL DEFAULT 0 COMMENT '编辑禁改',
  `sort_order` int NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_field_form_name` (`form_id`, `field_name`),
  KEY `idx_field_form_sort` (`form_id`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='表单字段定义';

-- 权限组：表单管理（挂 Console 根）。
INSERT INTO `fun_permission` (
  `pid`, `app_name`, `code`, `obj`, `act`, `name`, `resource_type`, `status`, `is_public`,
  `sort`, `source_type`, `source_name`, `created_at`, `updated_at`, `sort_order`, `deleted_at`
)
SELECT COALESCE((SELECT `id` FROM `fun_permission` WHERE `source_type` = 'admin_web' AND `source_name` = 'console_root' AND `resource_type` = 'group' LIMIT 1), 0),
       'console', NULL, '', '', CONVERT(X'E8A1A8E58D95E7AEA1E79086' USING utf8mb4),
       'group', 1, 0, 70, 'admin_web', 'form_management', NOW(), NOW(), 70, NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `fun_permission`
  WHERE `source_type` = 'admin_web' AND `source_name` = 'form_management' AND `resource_type` = 'group'
);

SET @form_group_id = (SELECT `id` FROM `fun_permission` WHERE `source_type` = 'admin_web' AND `source_name` = 'form_management' AND `resource_type` = 'group' LIMIT 1);

-- 路由权限（守卫式批量插入）。
SET @sql = CONCAT(
  'INSERT IGNORE INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`sort`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`) VALUES ',
  '(@gid@,''console'',''console/formdesigner:index'',''console/formdesigner'',''index'',CONVERT(X''E69FA5E79C8BE8A1A8E58D95E58897E8A1A8'' USING utf8mb4),''route'',1,0,10,''admin_web'',''form_management'',NOW(),NOW(),10,NULL),',
  '(@gid@,''console'',''console/formdesigner:detail'',''console/formdesigner'',''detail'',CONVERT(X''E8A1A8E58D95E8AFA6E68385'' USING utf8mb4),''route'',1,0,11,''admin_web'',''form_management'',NOW(),NOW(),11,NULL),',
  '(@gid@,''console'',''console/formdesigner:save'',''console/formdesigner'',''save'',CONVERT(X''E5889BE5BBBAE8A1A8E58D95'' USING utf8mb4),''route'',1,0,20,''admin_web'',''form_management'',NOW(),NOW(),20,NULL),',
  '(@gid@,''console'',''console/formdesigner:update'',''console/formdesigner'',''update'',CONVERT(X''E7BC96E8BE91E8A1A8E58D95'' USING utf8mb4),''route'',1,0,21,''admin_web'',''form_management'',NOW(),NOW(),21,NULL),',
  '(@gid@,''console'',''console/formdesigner:remove'',''console/formdesigner'',''remove'',CONVERT(X''E588A0E999A4E8A1A8E58D95'' USING utf8mb4),''route'',1,0,30,''admin_web'',''form_management'',NOW(),NOW(),30,NULL),',
  '(@gid@,''console'',''console/formdesigner:status'',''console/formdesigner'',''status'',CONVERT(X''E590AFE794A8E7A681E794A8E8A1A8E58D95'' USING utf8mb4),''route'',1,0,31,''admin_web'',''form_management'',NOW(),NOW(),31,NULL),',
  '(@gid@,''console'',''console/formdesigner:validate'',''console/formdesigner'',''validate'',CONVERT(X''E8A1A8E58D95E5AE9AE4B989E6A0A1E9AA8C'' USING utf8mb4),''route'',1,0,40,''admin_web'',''form_management'',NOW(),NOW(),40,NULL),',
  '(@gid@,''console'',''console/formdesigner:infer'',''console/formdesigner'',''infer'',CONVERT(X''E8A1A8E7BB93E69E84E68EA8E696AD'' USING utf8mb4),''route'',1,0,41,''admin_web'',''form_management'',NOW(),NOW(),41,NULL),',
  '(@gid@,''console'',''console/formdesigner:preview'',''console/formdesigner'',''preview'',CONVERT(X''E8BF81E7A7BBE9A284E8A788'' USING utf8mb4),''route'',1,0,50,''admin_web'',''form_management'',NOW(),NOW(),50,NULL),',
  '(@gid@,''console'',''console/formdesigner:apply'',''console/formdesigner'',''apply'',CONVERT(X''E8BF81E7A7BBE5BA94E794A8'' USING utf8mb4),''route'',1,0,51,''admin_web'',''form_management'',NOW(),NOW(),51,NULL);'
);
SET @sql = REPLACE(@sql, '@gid@', CAST(@form_group_id AS CHAR));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 菜单：表单管理列表（可见）与设计器（隐藏）。
INSERT INTO `fun_admin_menu` (
  `pid`, `permission_id`, `app_name`, `name`, `href`, `query`, `target`, `icon`, `status`,
  `sort`, `source_type`, `source_name`, `created_at`, `updated_at`, `sort_order`, `deleted_at`
)
SELECT COALESCE((SELECT `id` FROM `fun_admin_menu` WHERE `source_type` = 'admin_web' AND `source_name` = 'development_tools' LIMIT 1), 0),
       @form_group_id, 'console', CONVERT(X'E8A1A8E58D95E7AEA1E79086' USING utf8mb4), '/form/list',
       'component=form/list&name=FormList&type=C&permission=console/formdesigner:index',
       '_self', 'i-ep-document-copy', 1, 40, 'admin_web', 'form_list', NOW(), NOW(), 40, NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `fun_admin_menu` WHERE `source_type` = 'admin_web' AND `source_name` = 'form_list'
);

SET @form_list_menu_id = (SELECT `id` FROM `fun_admin_menu` WHERE `source_type` = 'admin_web' AND `source_name` = 'form_list' LIMIT 1);

INSERT INTO `fun_admin_menu` (
  `pid`, `permission_id`, `app_name`, `name`, `href`, `query`, `target`, `icon`, `status`,
  `sort`, `source_type`, `source_name`, `created_at`, `updated_at`, `sort_order`, `deleted_at`
)
SELECT @form_list_menu_id,
       @form_group_id, 'console', CONVERT(X'E8A1A8E58D95E8AEBEE8AEA1E599A8' USING utf8mb4), '/form/designer',
       'component=form/designer&name=FormDesigner&type=C&hidden=1',
       '_self', 'i-ep-edit', 1, 1, 'admin_web', 'form_designer', NOW(), NOW(), 1, NULL
WHERE NOT EXISTS (
  SELECT 1 FROM `fun_admin_menu` WHERE `source_type` = 'admin_web' AND `source_name` = 'form_designer'
);
