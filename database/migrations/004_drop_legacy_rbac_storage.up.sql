-- ============================================================================
-- 迁移 004：删除旧 RBAC 存储（UP，破坏性操作）
-- ----------------------------------------------------------------------------
-- 前置条件：
--   1. 已执行迁移 001，且两个 missing_* 校验结果均为 0；
--   2. 已发布 Casbin 版本并完成管理员登录、角色授权、菜单和按钮回归；
--   3. 已完成数据库备份并确认回滚窗口。
-- ============================================================================

ALTER TABLE `fun_admin` DROP COLUMN `group_id`;
ALTER TABLE `fun_auth_group` DROP COLUMN `rules`;
DROP TABLE IF EXISTS `fun_admin_auth_group`;
DROP TABLE IF EXISTS `fun_auth_group_rule`;
