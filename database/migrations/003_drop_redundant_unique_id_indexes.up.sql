-- ============================================================================
-- 迁移 003：清理与主键完全重复的冗余唯一索引（UP）
-- ----------------------------------------------------------------------------
-- 依据：以下 4 张表同时定义了 PRIMARY KEY(`id`) 与 UNIQUE KEY `id`(`id`)，
--       二者约束语义完全等价（主键本身即唯一且非空），冗余索引只会带来
--       写入放大与存储浪费，无任何查询收益。
--
--       app/install/sql/funadmin.sql:4546  fun_auth_group
--       app/install/sql/funadmin.sql:4570  fun_config
--       app/install/sql/funadmin.sql:4605  fun_member
--       app/install/sql/funadmin.sql:4645  fun_member_level
--
--       （fun_auth_rule 的同类冗余索引已由迁移 002 步骤 4 处理，此处不重复。）
--
-- 安全性：DROP KEY `id` 仅移除二级唯一索引，PRIMARY KEY 不受影响，
--         唯一性约束依旧由主键保证，不存在约束丢失风险。
-- 兼容性：MySQL 5.7+ / 8.0+。
-- ============================================================================

ALTER TABLE `fun_auth_group`   DROP KEY `id`;
ALTER TABLE `fun_config`       DROP KEY `id`;
ALTER TABLE `fun_member`       DROP KEY `id`;
ALTER TABLE `fun_member_level` DROP KEY `id`;

-- ============================================================================
-- 变更后自检：各表应仅保留 PRIMARY KEY 及业务索引，不再有名为 id 的二级索引
-- ============================================================================
-- SHOW INDEX FROM `fun_auth_group`   WHERE Key_name = 'id';  -- 期望 0 行
-- SHOW INDEX FROM `fun_config`       WHERE Key_name = 'id';  -- 期望 0 行
-- SHOW INDEX FROM `fun_member`       WHERE Key_name = 'id';  -- 期望 0 行
-- SHOW INDEX FROM `fun_member_level` WHERE Key_name = 'id';  -- 期望 0 行
