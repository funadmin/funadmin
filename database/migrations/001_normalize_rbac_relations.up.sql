-- ============================================================================
-- 迁移 001：RBAC 多对多关系规范化（UP）
-- ----------------------------------------------------------------------------
-- 目标：消除 fun_admin.group_id / fun_auth_group.rules / fun_member.group_id
--       三处逗号字符串存储，改为符合第一范式的关联表。
--
-- 安全特性：
--   1. 纯增量：只新建表，不修改、不删除任何现有列，现有代码零影响。
--   2. 幂等：全部使用 IF NOT EXISTS，可重复执行。
--   3. 外键类型与父表逐字节对齐，避免 MySQL errno 1215。
--   4. 数据迁移使用 JOIN + FIND_IN_SET，天然过滤孤儿引用
--      （例如 fun_admin.group_id='1,3' 而角色 3 不存在时，只写入 1）。
--
-- 执行前必须备份：
--   mysqldump --single-transaction --routines --triggers DB > backup.sql
--
-- 表前缀：脚本默认 fun_，若 .env 中 DB_PREFIX 不同，请先全局替换 "fun_"。
-- 兼容版本：MySQL 5.7+ / 8.0+（安装器要求最低 5.7）。
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- 1. 管理员 ↔ 角色（替代 fun_admin.group_id varchar(8)）
--    父表类型：fun_admin.id = INT(有符号)；fun_auth_group.id = INT UNSIGNED
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fun_admin_auth_group` (
  `admin_id`    int          NOT NULL                COMMENT '管理员ID，关联 fun_admin.id',
  `group_id`    int UNSIGNED NOT NULL                COMMENT '角色ID，关联 fun_auth_group.id',
  `operator_id` int          NOT NULL DEFAULT 0      COMMENT '授权操作人管理员ID，0=系统初始化',
  `create_time` int UNSIGNED NOT NULL DEFAULT 0      COMMENT '授权时间',
  PRIMARY KEY (`admin_id`, `group_id`),
  KEY `idx_group_id` (`group_id`),
  CONSTRAINT `fk_admin_auth_group_admin`
    FOREIGN KEY (`admin_id`) REFERENCES `fun_admin` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_admin_auth_group_group`
    FOREIGN KEY (`group_id`) REFERENCES `fun_auth_group` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='管理员-角色关联表';

-- ----------------------------------------------------------------------------
-- 2. 角色 ↔ 权限节点（替代 fun_auth_group.rules longtext）
--    父表类型：fun_auth_group.id = INT UNSIGNED；fun_auth_rule.id = MEDIUMINT UNSIGNED
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fun_auth_group_rule` (
  `group_id`    int UNSIGNED      NOT NULL           COMMENT '角色ID，关联 fun_auth_group.id',
  `rule_id`     mediumint UNSIGNED NOT NULL          COMMENT '权限节点ID，关联 fun_auth_rule.id',
  `operator_id` int               NOT NULL DEFAULT 0 COMMENT '授权操作人管理员ID，0=系统初始化',
  `create_time` int UNSIGNED      NOT NULL DEFAULT 0 COMMENT '授权时间',
  PRIMARY KEY (`group_id`, `rule_id`),
  KEY `idx_rule_id` (`rule_id`),
  CONSTRAINT `fk_auth_group_rule_group`
    FOREIGN KEY (`group_id`) REFERENCES `fun_auth_group` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_auth_group_rule_rule`
    FOREIGN KEY (`rule_id`) REFERENCES `fun_auth_rule` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='角色-权限节点关联表';

-- ----------------------------------------------------------------------------
-- 3. 会员 ↔ 会员组（替代 fun_member.group_id varchar(50)）
--    业务事实：app/backend/view/member/member/add.html 的 group_id 为 multiple=1，
--    确认是多对多，因此必须拆关系表，不能退化为整数外键。
--    父表类型：fun_member.id = MEDIUMINT UNSIGNED；fun_member_group.id = INT UNSIGNED
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `fun_member_group_access` (
  `member_id`   mediumint UNSIGNED NOT NULL          COMMENT '会员ID，关联 fun_member.id',
  `group_id`    int UNSIGNED       NOT NULL          COMMENT '会员组ID，关联 fun_member_group.id',
  `is_primary`  tinyint UNSIGNED   NOT NULL DEFAULT 0 COMMENT '是否主组，1=主组（用于兼容原单值语义）',
  `create_time` int UNSIGNED       NOT NULL DEFAULT 0 COMMENT '关联时间',
  PRIMARY KEY (`member_id`, `group_id`),
  KEY `idx_group_id` (`group_id`),
  CONSTRAINT `fk_member_group_access_member`
    FOREIGN KEY (`member_id`) REFERENCES `fun_member` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_member_group_access_group`
    FOREIGN KEY (`group_id`) REFERENCES `fun_member_group` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='会员-会员组关联表';

-- ============================================================================
-- 数据迁移：从逗号字符串回填关联表
-- ----------------------------------------------------------------------------
-- 使用 INSERT IGNORE 保证幂等（联合主键冲突即跳过）。
-- JOIN 目标表可自动排除不存在的ID，不会因孤儿引用导致外键失败。
--
-- 性能提示：以下语句复杂度约为 O(主表行数 x 目标表行数)。
--   fun_auth_rule 种子约120行、fun_auth_group 通常数十行，可一次性执行。
--   若 fun_member 超过 10 万行，请改用文末的分批模板执行第 3 段。
-- ============================================================================

-- 1) 管理员 ↔ 角色
INSERT IGNORE INTO `fun_admin_auth_group` (`admin_id`, `group_id`, `operator_id`, `create_time`)
SELECT a.`id`, g.`id`, 0, UNIX_TIMESTAMP()
FROM `fun_admin` a
JOIN `fun_auth_group` g ON FIND_IN_SET(g.`id`, REPLACE(a.`group_id`, ' ', '')) > 0
WHERE a.`group_id` IS NOT NULL AND a.`group_id` <> '';

-- 2) 角色 ↔ 权限节点
INSERT IGNORE INTO `fun_auth_group_rule` (`group_id`, `rule_id`, `operator_id`, `create_time`)
SELECT g.`id`, r.`id`, 0, UNIX_TIMESTAMP()
FROM `fun_auth_group` g
JOIN `fun_auth_rule` r ON FIND_IN_SET(r.`id`, REPLACE(g.`rules`, ' ', '')) > 0
WHERE g.`rules` IS NOT NULL AND g.`rules` <> '';

-- 3) 会员 ↔ 会员组（is_primary 标记原字符串中的第一个组，保留单值语义）
INSERT IGNORE INTO `fun_member_group_access` (`member_id`, `group_id`, `is_primary`, `create_time`)
SELECT m.`id`, g.`id`,
       CASE WHEN g.`id` = CAST(NULLIF(SUBSTRING_INDEX(REPLACE(m.`group_id`, ' ', ''), ',', 1), '') AS UNSIGNED)
            THEN 1 ELSE 0 END,
       UNIX_TIMESTAMP()
FROM `fun_member` m
JOIN `fun_member_group` g ON FIND_IN_SET(g.`id`, REPLACE(m.`group_id`, ' ', '')) > 0
WHERE m.`group_id` IS NOT NULL AND m.`group_id` <> '';

-- ============================================================================
-- 迁移结果自检（执行后请人工核对以下查询输出）
-- ============================================================================
-- A. 孤儿引用告警：应返回 0 行，若有输出说明原数据存在指向不存在角色的授权
-- SELECT a.id AS admin_id, a.group_id
-- FROM fun_admin a
-- WHERE a.group_id IS NOT NULL AND a.group_id <> ''
--   AND (SELECT COUNT(*) FROM fun_admin_auth_group x WHERE x.admin_id = a.id) = 0;
--
-- B. 数量对账：授权总数应与去重后的逗号串元素总数一致
-- SELECT
--   (SELECT COUNT(*) FROM fun_admin_auth_group)  AS admin_group_rows,
--   (SELECT COUNT(*) FROM fun_auth_group_rule)   AS group_rule_rows,
--   (SELECT COUNT(*) FROM fun_member_group_access) AS member_group_rows;
--
-- C. 每个会员有且仅有一个主组：应返回 0 行
-- SELECT member_id, COUNT(*) c FROM fun_member_group_access
-- WHERE is_primary = 1 GROUP BY member_id HAVING c <> 1;

-- ============================================================================
-- 附：fun_member 超过 10 万行时的分批模板（按需替换 :start / :end）
-- ============================================================================
-- INSERT IGNORE INTO `fun_member_group_access` (`member_id`,`group_id`,`is_primary`,`create_time`)
-- SELECT m.`id`, g.`id`,
--        CASE WHEN g.`id` = CAST(NULLIF(SUBSTRING_INDEX(REPLACE(m.`group_id`,' ',''),',',1),'') AS UNSIGNED)
--             THEN 1 ELSE 0 END,
--        UNIX_TIMESTAMP()
-- FROM `fun_member` m
-- JOIN `fun_member_group` g ON FIND_IN_SET(g.`id`, REPLACE(m.`group_id`,' ','')) > 0
-- WHERE m.`id` BETWEEN :start AND :end;
