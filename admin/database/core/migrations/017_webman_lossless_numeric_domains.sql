-- 只扩大数值取值域；bigint 同时容纳 int 的全部有符号与无符号值，不强转或缩窄历史数据。
ALTER TABLE `fun_admin_menu` MODIFY COLUMN `sort_order` bigint NOT NULL DEFAULT '999';
ALTER TABLE `fun_permission` MODIFY COLUMN `sort_order` bigint NOT NULL DEFAULT '999';
ALTER TABLE `fun_attach`
  MODIFY COLUMN `sort_order` bigint NOT NULL DEFAULT '50',
  MODIFY COLUMN `size` bigint DEFAULT '0' COMMENT '大小';
ALTER TABLE `fun_attach_group` MODIFY COLUMN `sort_order` bigint DEFAULT '999';
ALTER TABLE `fun_member` MODIFY COLUMN `login_num` bigint DEFAULT '0' COMMENT '登录次数';
ALTER TABLE `fun_member_level`
  MODIFY COLUMN `discount` int DEFAULT '100' COMMENT '折扣',
  MODIFY COLUMN `sort_order` bigint DEFAULT '0';
ALTER TABLE `fun_auth_group`
  MODIFY COLUMN `level` int unsigned NOT NULL DEFAULT '100' COMMENT '角色等级，数值越小权限越高',
  MODIFY COLUMN `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '角色名称';
ALTER TABLE `fun_admin_log` MODIFY COLUMN `admin_id` bigint unsigned NOT NULL DEFAULT '0' COMMENT '管理员ID';
