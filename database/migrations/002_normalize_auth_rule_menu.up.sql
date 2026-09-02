-- ============================================================================
-- 迁移 002：权限节点/菜单表 fun_auth_rule 规范化（UP）
-- ----------------------------------------------------------------------------
-- 依据（基于 app/install/sql/funadmin.sql 种子数据实测，120 行）：
--   1. 热路径索引缺失：AuthService::authMenuNode() 在构建菜单树时，对每个节点
--      执行 where(status=1)->where(menu_status=1)->where(pid=$v['id'])->find()
--      （app/backend/service/AuthService.php:789-792），属典型 N+1；
--      现有索引仅有单列 KEY `pid`，无法覆盖 status/menu_status 过滤。
--   2. CHAR 定长浪费：href 定义 char(150) 实测最长 26 字符、
--      title char(100) 实测最长 11、module char(50) 实测最长 7。
--      CHAR 定长补空格，行存储与索引体积均被无谓放大。
--   3. 索引与主键重复：本表存在 UNIQUE KEY `id`(`id`)，与 PRIMARY KEY(`id`)
--      完全等价，徒增写入放大。
--
-- 刻意不做的改动（避免过度设计与回归风险）：
--   * 不新增 (module,href,status) 索引：现有 UNIQUE KEY `href`(href,module,query)
--     的最左前缀已完全覆盖 AuthService.php:255-261 的 href+module 精确查询。
--   * 不重命名 `href` 索引：重命名需 DROP+ADD，会在无约束窗口内全量重建索引，
--     收益仅为命名美观，风险收益比不划算。
--   * 不合并 type 与 menu_status 字段：实测二者 100% 冗余
--     （type=1 ⟺ menu_status=1 共19行；type=0 ⟺ menu_status=0 共101行，零例外），
--     但 Index.php:38 依赖 type、AuthService.php:779/791 等多处依赖 menu_status，
--     删任一字段都会打断现有代码，须连同代码改造放入 Phase 2 并单独确认。
--   * 不加 CHECK 约束固化上述冗余不变量：MySQL 5.7 会解析但静默忽略 CHECK，
--     写入方会误以为已被强制校验；且插件若合法写入 type=0/menu_status=1 组合，
--     在 8.0 上将直接失败，属未经确认的行为变更。
--
-- 兼容性：MySQL 5.7+ / 8.0+。本表仅约120行，DDL 重建为瞬时操作。
-- 表前缀：默认 fun_，DB_PREFIX 不同时请先全局替换。
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 步骤 1：CHAR -> VARCHAR 前置数据清理
-- ----------------------------------------------------------------------------
-- CHAR 比较忽略尾部空格，VARCHAR 不忽略。若历史数据存在尾部空格，转换后
-- UNIQUE(href,module,query) 的唯一性判定与 href 精确匹配行为会发生变化。
-- 因此必须先 TRIM。本表使用 utf8mb4_unicode_ci（PAD SPACE 排序规则），
-- 转换后字符串比较仍按补空格语义处理，故等值查询行为保持一致。
UPDATE `fun_auth_rule`
SET `href`   = TRIM(TRAILING ' ' FROM `href`),
    `module` = TRIM(TRAILING ' ' FROM `module`),
    `title`  = TRIM(TRAILING ' ' FROM `title`);

-- ----------------------------------------------------------------------------
-- 步骤 2：CHAR -> VARCHAR（缩短行宽与索引体积，保留原长度上限与 NOT NULL 语义）
-- ----------------------------------------------------------------------------
ALTER TABLE `fun_auth_rule`
  MODIFY `module` varchar(50)  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'backend' COMMENT '模块',
  MODIFY `href`   varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '链接',
  MODIFY `title`  varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '名字';

-- ----------------------------------------------------------------------------
-- 步骤 3：以复合索引替换单列 pid 索引，覆盖菜单树 N+1 热路径
-- ----------------------------------------------------------------------------
-- 新索引最左前缀为 pid，因此原有 where('pid',$pid) 查询
-- （AuthService.php:819 getAllIdsBypid）同样受益，单列 pid 索引可安全移除。
ALTER TABLE `fun_auth_rule`
  ADD KEY `idx_pid_status_menu` (`pid`, `status`, `menu_status`);

ALTER TABLE `fun_auth_rule`
  DROP KEY `pid`;

-- ----------------------------------------------------------------------------
-- 步骤 4：移除与主键完全重复的唯一索引
-- ----------------------------------------------------------------------------
ALTER TABLE `fun_auth_rule`
  DROP KEY `id`;

-- ============================================================================
-- 变更后自检
-- ============================================================================
-- A. 索引结构应符合预期（PRIMARY KEY id / UNIQUE href / idx_pid_status_menu）
-- SHOW INDEX FROM `fun_auth_rule`;
--
-- B. 热路径应命中新索引，key 列须为 idx_pid_status_menu
-- EXPLAIN SELECT href,id FROM `fun_auth_rule`
--   WHERE status=1 AND menu_status=1 AND pid=1 LIMIT 1;
--
-- C. href 精确查询仍应命中 UNIQUE href 索引
-- EXPLAIN SELECT id FROM `fun_auth_rule`
--   WHERE href='auth.auth/index' AND module='backend' AND status=1;
--
-- D. 数据完整性：行数与唯一性不应因 TRIM 发生变化
-- SELECT COUNT(*) AS total,
--        COUNT(DISTINCT href, module, query) AS distinct_href
-- FROM `fun_auth_rule`;
