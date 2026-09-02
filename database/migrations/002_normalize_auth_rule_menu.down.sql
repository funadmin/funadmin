-- ============================================================================
-- 迁移 002 回滚（DOWN）：还原 fun_auth_rule 索引与字段类型
-- ----------------------------------------------------------------------------
-- 注意：TRIM 造成的尾部空格删除属数据清理，不可逆也无必要还原。
--       本脚本仅还原表结构（字段类型与索引定义）。
-- 执行顺序：先恢复被 DROP 的索引，再改回字段类型，最后移除新增索引。
-- ============================================================================

SET NAMES utf8mb4;

-- 1. 恢复单列 pid 索引
ALTER TABLE `fun_auth_rule`
  ADD KEY `pid` (`pid`);

-- 2. 恢复与主键重复的唯一索引（还原原始结构）
ALTER TABLE `fun_auth_rule`
  ADD UNIQUE KEY `id` (`id`);

-- 3. 字段类型还原为 CHAR
ALTER TABLE `fun_auth_rule`
  MODIFY `module` char(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'backend' COMMENT '模块',
  MODIFY `href`   char(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '链接',
  MODIFY `title`  char(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '名字';

-- 4. 移除 002 新增的复合索引
ALTER TABLE `fun_auth_rule`
  DROP KEY `idx_pid_status_menu`;

-- 回滚后校验：索引应回到 PRIMARY KEY(id) / UNIQUE id(id) / UNIQUE href / KEY pid
-- SHOW INDEX FROM `fun_auth_rule`;
