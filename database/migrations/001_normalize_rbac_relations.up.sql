-- ============================================================================
-- 迁移 001：旧 CSV RBAC 迁移到 Casbin（UP）
-- ----------------------------------------------------------------------------
-- 目标：fun_casbin_rule 成为唯一授权关系数据源。
-- 资源规则：obj = module/controller（多级控制器保留点号），act = action。
-- 示例：backend/auth.admin/index -> obj=backend/auth.admin, act=index。
--
-- 执行前必须备份数据库。默认前缀为 fun_，其他前缀请先替换。
-- 本脚本兼容 MySQL 5.7+；先迁移并校验，不在本阶段删除旧字段/表。
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `fun_casbin_rule` (
  `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT,
  `ptype` varchar(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `v0` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `v1` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `v2` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `v3` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `v4` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `v5` varchar(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `rule_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rule_hash` (`rule_hash`),
  KEY `idx_subject_domain` (`ptype`,`v0`,`v1`),
  KEY `idx_object_action` (`ptype`,`v2`,`v3`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Casbin授权策略';

-- 管理员 -> 角色（g, admin:{id}, role:{id}, default）。
INSERT IGNORE INTO `fun_casbin_rule`
(`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT
  'g', CONCAT('admin:', a.`id`), CONCAT('role:', g.`id`), 'default', '', '', '',
  SHA2(CONCAT_WS(CHAR(31), 'g', CONCAT('admin:', a.`id`), CONCAT('role:', g.`id`), 'default'), 256)
FROM `fun_admin` a
JOIN `fun_auth_group` g ON FIND_IN_SET(g.`id`, COALESCE(a.`group_id`, '')) > 0
WHERE a.`delete_time` IS NULL AND g.`delete_time` IS NULL;

-- 角色 -> 权限（p, role:{id}, default, obj, act）。
-- 目录节点通常没有可执行 action，不生成 p 策略；其显示由有权子菜单决定。
INSERT IGNORE INTO `fun_casbin_rule`
(`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT
  'p', CONCAT('role:', g.`id`), 'default',
  CONCAT(LOWER(r.`module`), '/', LOWER(SUBSTRING_INDEX(TRIM(BOTH '/' FROM r.`href`), '/', 1))),
  LOWER(SUBSTRING_INDEX(TRIM(BOTH '/' FROM r.`href`), '/', -1)), '', '',
  SHA2(CONCAT_WS(CHAR(31), 'p', CONCAT('role:', g.`id`), 'default',
    CONCAT(LOWER(r.`module`), '/', LOWER(SUBSTRING_INDEX(TRIM(BOTH '/' FROM r.`href`), '/', 1))),
    LOWER(SUBSTRING_INDEX(TRIM(BOTH '/' FROM r.`href`), '/', -1))), 256)
FROM `fun_auth_group` g
JOIN `fun_auth_rule` r ON FIND_IN_SET(r.`id`, COALESCE(g.`rules`, '')) > 0
WHERE g.`delete_time` IS NULL
  AND r.`delete_time` IS NULL
  AND r.`status` = 1
  AND TRIM(BOTH '/' FROM r.`href`) LIKE '%/%'
  AND NOT EXISTS (
    SELECT 1 FROM `fun_auth_rule` child
    WHERE child.`pid` = r.`id` AND child.`delete_time` IS NULL
  );

-- 超级管理员角色绑定兜底；超级管理员请求仍由配置 superAdminId 直接放行。
INSERT IGNORE INTO `fun_casbin_rule`
(`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
VALUES
('g','admin:1','role:1','default','','','',SHA2(CONCAT_WS(CHAR(31),'g','admin:1','role:1','default'),256));

-- 校验 1：旧管理员-角色关系是否全部迁移，必须为 0。
SELECT COUNT(*) AS `missing_admin_role_policies`
FROM `fun_admin` a
JOIN `fun_auth_group` g ON FIND_IN_SET(g.`id`, COALESCE(a.`group_id`, '')) > 0
LEFT JOIN `fun_casbin_rule` c
  ON c.`ptype`='g'
 AND c.`v0`=CONCAT('admin:', a.`id`)
 AND c.`v1`=CONCAT('role:', g.`id`)
 AND c.`v2`='default'
WHERE a.`delete_time` IS NULL AND g.`delete_time` IS NULL AND c.`id` IS NULL;

-- 校验 2：旧角色-权限可执行节点是否全部迁移，必须为 0。
SELECT COUNT(*) AS `missing_role_permission_policies`
FROM `fun_auth_group` g
JOIN `fun_auth_rule` r ON FIND_IN_SET(r.`id`, COALESCE(g.`rules`, '')) > 0
LEFT JOIN `fun_casbin_rule` c
  ON c.`ptype`='p'
 AND c.`v0`=CONCAT('role:', g.`id`)
 AND c.`v1`='default'
 AND c.`v2`=CONCAT(LOWER(r.`module`), '/', LOWER(SUBSTRING_INDEX(TRIM(BOTH '/' FROM r.`href`), '/', 1)))
 AND c.`v3`=LOWER(SUBSTRING_INDEX(TRIM(BOTH '/' FROM r.`href`), '/', -1))
WHERE g.`delete_time` IS NULL
  AND r.`delete_time` IS NULL
  AND r.`status`=1
  AND TRIM(BOTH '/' FROM r.`href`) LIKE '%/%'
  AND NOT EXISTS (
    SELECT 1 FROM `fun_auth_rule` child
    WHERE child.`pid` = r.`id` AND child.`delete_time` IS NULL
  )
  AND c.`id` IS NULL;
