-- ============================================================================
-- 迁移 001 回滚（DOWN）：删除 RBAC 规范化关联表
-- ----------------------------------------------------------------------------
-- 说明：001 的 UP 为纯增量（未修改/删除任何旧列），因此回滚只需删除新建的
--       三张关联表，原逗号字符串字段仍完整保留，业务不受影响。
--
-- 前置条件：必须确认代码已回退到读取旧字段的版本，否则回滚后权限体系失效。
-- 执行顺序：外键为 CASCADE，直接 DROP TABLE 即可自动清理关联约束。
-- ============================================================================

DROP TABLE IF EXISTS `fun_member_group_access`;
DROP TABLE IF EXISTS `fun_auth_group_rule`;
DROP TABLE IF EXISTS `fun_admin_auth_group`;

-- 回滚后校验：以下三张表应不存在
-- SHOW TABLES LIKE 'fun_admin_auth_group';
-- SHOW TABLES LIKE 'fun_auth_group_rule';
-- SHOW TABLES LIKE 'fun_member_group_access';
