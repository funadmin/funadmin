-- 迁移 001 回滚（DOWN）：仅删除 Casbin 策略表。
-- 仅可在旧 fun_admin.group_id 与 fun_auth_group.rules 尚未删除时执行。
DROP TABLE IF EXISTS `fun_casbin_rule`;
