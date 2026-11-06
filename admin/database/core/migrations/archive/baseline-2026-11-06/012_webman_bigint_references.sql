-- Webman 与根基线的实体主键均为 bigint unsigned，引用列仅扩容，不重排 ID 或改写授权。
ALTER TABLE `fun_admin` MODIFY COLUMN `dept_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '部门ID';
ALTER TABLE `fun_admin_menu`
  MODIFY COLUMN `pid` bigint unsigned NOT NULL DEFAULT '0',
  MODIFY COLUMN `permission_id` bigint unsigned NOT NULL DEFAULT '0';
ALTER TABLE `fun_department` MODIFY COLUMN `pid` bigint unsigned NOT NULL DEFAULT '0' COMMENT '上级部门ID';
ALTER TABLE `fun_dict_item` MODIFY COLUMN `type_id` bigint unsigned NOT NULL COMMENT '字典类型ID';
ALTER TABLE `fun_permission` MODIFY COLUMN `pid` bigint unsigned NOT NULL DEFAULT '0';
ALTER TABLE `fun_permission_field` MODIFY COLUMN `permission_id` bigint unsigned NOT NULL COMMENT '所属功能权限资源ID';
ALTER TABLE `fun_auth_group_department`
  MODIFY COLUMN `role_id` bigint unsigned NOT NULL COMMENT '角色ID',
  MODIFY COLUMN `dept_id` bigint unsigned NOT NULL COMMENT '自定义数据范围部门ID';
ALTER TABLE `fun_auth_group_inherit`
  MODIFY COLUMN `role_id` bigint unsigned NOT NULL COMMENT '子角色ID',
  MODIFY COLUMN `parent_role_id` bigint unsigned NOT NULL COMMENT '继承的父角色ID';
ALTER TABLE `fun_auth_group_field_permission` MODIFY COLUMN `role_id` bigint unsigned NOT NULL;
