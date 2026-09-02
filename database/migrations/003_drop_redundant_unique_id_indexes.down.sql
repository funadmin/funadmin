-- ============================================================================
-- 迁移 003 回滚（DOWN）：恢复被移除的冗余唯一索引
-- ----------------------------------------------------------------------------
-- 说明：这些索引在功能上是冗余的，恢复它们仅为严格还原原始表结构。
--       fun_auth_group 原定义为 USING BTREE，此处保持一致。
-- ============================================================================

ALTER TABLE `fun_auth_group`   ADD UNIQUE KEY `id` (`id`) USING BTREE;
ALTER TABLE `fun_config`       ADD UNIQUE KEY `id` (`id`);
ALTER TABLE `fun_member`       ADD UNIQUE KEY `id` (`id`);
ALTER TABLE `fun_member_level` ADD UNIQUE KEY `id` (`id`);

-- 回滚后校验：应各返回 1 行
-- SHOW INDEX FROM `fun_auth_group`   WHERE Key_name = 'id';
-- SHOW INDEX FROM `fun_config`       WHERE Key_name = 'id';
-- SHOW INDEX FROM `fun_member`       WHERE Key_name = 'id';
-- SHOW INDEX FROM `fun_member_level` WHERE Key_name = 'id';
