-- 002 代码生成器：核心菜单「业务开发」改名「代码生成」；登记表结构同步与生成代码下载两个接口权限并按原有权限授予。
-- 与 PHP 迁移 database/migrations/002_code_generator.php 终态一致；中文统一使用 UTF-8 十六进制字面量；全部语句可重复执行。
-- 超级管理员按管理员 ID 放行（auth.super_admin_id），无需授权行；BusinessVisual/BusinessDatabase/BusinessRecords 菜单在基线中已隐藏。

-- 1. 菜单改名：按来源与路径匹配核心菜单，管理员改过名称（不再是「业务开发」）的不动。
UPDATE `fun_admin_menu`
SET `name`=CONVERT(X'E4BBA3E7A081E7949FE68890' USING utf8mb4), `updated_at`=NOW()
WHERE `app_name`='admin' AND `source_type`='admin_web' AND `source_name`='business_development'
  AND `href`='/development/business/mine' AND HEX(`name`)='E4B89AE58AA1E5BC80E58F91';

-- 2. 登记接口权限：与 development:business:preview-formal-generation 同一父级与来源（obj/act 即 PermissionCode::resource 推导值）。
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT sibling.`pid`,'admin','development:business:database-sync','development/business','database-sync',CONVERT(X'E5908CE6ADA5E8A1A8E7BB93E69E84' USING utf8mb4),'route',1,0,sibling.`source_type`,sibling.`source_name`,NOW(),NOW(),33,NULL
FROM `fun_permission` AS sibling
WHERE sibling.`code`='development:business:preview-formal-generation'
  AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:business:database-sync');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT sibling.`pid`,'admin','development:business:formal-generation-archive','development/business','formal-generation-archive',CONVERT(X'E4B88BE8BDBDE7949FE68890E4BBA3E7A081' USING utf8mb4),'route',1,0,sibling.`source_type`,sibling.`source_name`,NOW(),NOW(),52,NULL
FROM `fun_permission` AS sibling
WHERE sibling.`code`='development:business:preview-formal-generation'
  AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:business:formal-generation-archive');

-- 兄弟权限缺失（非标准库）时挂在根下，归属宿主 system。
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin','development:business:database-sync','development/business','database-sync',CONVERT(X'E5908CE6ADA5E8A1A8E7BB93E69E84' USING utf8mb4),'route',1,0,'system','',NOW(),NOW(),33,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:business:database-sync');

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin','development:business:formal-generation-archive','development/business','formal-generation-archive',CONVERT(X'E4B88BE8BDBDE7949FE68890E4BBA3E7A081' USING utf8mb4),'route',1,0,'system','',NOW(),NOW(),52,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='development:business:formal-generation-archive');

-- 已由代码同步提前登记（pid=0、system）的行对齐为同一终态；插件归属的同名行不动。
UPDATE `fun_permission` AS target
JOIN `fun_permission` AS sibling ON sibling.`code`='development:business:preview-formal-generation'
SET target.`pid`=sibling.`pid`, target.`source_type`=sibling.`source_type`, target.`source_name`=sibling.`source_name`,
    target.`app_name`='admin', target.`obj`='development/business', target.`act`='database-sync',
    target.`name`=CONVERT(X'E5908CE6ADA5E8A1A8E7BB93E69E84' USING utf8mb4), target.`resource_type`='route',
    target.`status`=1, target.`sort_order`=33, target.`deleted_at`=NULL, target.`updated_at`=NOW()
WHERE target.`code`='development:business:database-sync' AND target.`source_type`<>'plugin';

UPDATE `fun_permission` AS target
JOIN `fun_permission` AS sibling ON sibling.`code`='development:business:preview-formal-generation'
SET target.`pid`=sibling.`pid`, target.`source_type`=sibling.`source_type`, target.`source_name`=sibling.`source_name`,
    target.`app_name`='admin', target.`obj`='development/business', target.`act`='formal-generation-archive',
    target.`name`=CONVERT(X'E4B88BE8BDBDE7949FE68890E4BBA3E7A081' USING utf8mb4), target.`resource_type`='route',
    target.`status`=1, target.`sort_order`=52, target.`deleted_at`=NULL, target.`updated_at`=NOW()
WHERE target.`code`='development:business:formal-generation-archive' AND target.`source_type`<>'plugin';

-- 3. 授权：持有 inspect-database 的角色获得 database-sync；持有 generate 能力的角色获得 formal-generation-archive。
-- rule_hash 与 CasbinService::ruleRow() 一致（sha256(ptype \x1f v0 \x1f v1 \x1f obj \x1f act)）。
INSERT INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT policy.`ptype`,policy.`v0`,policy.`v1`,'development/business','database-sync','','',
       SHA2(CONCAT_WS(CHAR(31),policy.`ptype`,policy.`v0`,policy.`v1`,'development/business','database-sync'),256)
FROM `fun_casbin_rule` AS policy
WHERE policy.`ptype`='p' AND policy.`v2`='development/business' AND policy.`v3`='inspect-database'
  AND NOT EXISTS (
    SELECT 1 FROM `fun_casbin_rule` AS granted
    WHERE granted.`ptype`=policy.`ptype` AND granted.`v0`=policy.`v0` AND granted.`v1`=policy.`v1`
      AND granted.`v2`='development/business' AND granted.`v3`='database-sync' AND granted.`v4`='' AND granted.`v5`=''
  );

INSERT INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)
SELECT DISTINCT policy.`ptype`,policy.`v0`,policy.`v1`,'development/business','formal-generation-archive','','',
       SHA2(CONCAT_WS(CHAR(31),policy.`ptype`,policy.`v0`,policy.`v1`,'development/business','formal-generation-archive'),256)
FROM `fun_casbin_rule` AS policy
WHERE policy.`ptype`='p' AND policy.`v2`='development/business' AND policy.`v3`='generate'
  AND NOT EXISTS (
    SELECT 1 FROM `fun_casbin_rule` AS granted
    WHERE granted.`ptype`=policy.`ptype` AND granted.`v0`=policy.`v0` AND granted.`v1`=policy.`v1`
      AND granted.`v2`='development/business' AND granted.`v3`='formal-generation-archive' AND granted.`v4`='' AND granted.`v5`=''
  );
