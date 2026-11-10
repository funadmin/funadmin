-- 006 操作日志彻底删除：登记 system:operation-log:force-delete（彻底删除）与 system:operation-log:clear-force（彻底清空）两个接口权限。
-- 与 PHP 迁移 database/migrations/006_operation_log_force_delete.php 终态一致；中文统一使用 UTF-8 十六进制字面量；全部语句可重复执行。
-- 只登记，不授予任何角色：真实删除不可恢复，由管理员按需授权；超级管理员按管理员 ID 放行（auth.super_admin_id），无需授权行。

-- 1. 登记接口权限：与软删除权限 system:operation-log:delete 同一父级与来源（obj/act 即 PermissionCode::resource 推导值）。
-- 只看未删除的行：回收站中的同名权限不阻止登记（唯一索引含 deleted_token）。
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT sibling.`pid`,'admin','system:operation-log:force-delete','system/operation-log','force-delete',CONVERT(X'E5BDBBE5BA95E588A0E999A4' USING utf8mb4),'route',1,0,sibling.`source_type`,sibling.`source_name`,NOW(),NOW(),30,NULL
FROM `fun_permission` AS sibling
WHERE sibling.`code`='system:operation-log:delete' AND sibling.`deleted_at` IS NULL
  AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='system:operation-log:force-delete' AND `deleted_at` IS NULL);

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT sibling.`pid`,'admin','system:operation-log:clear-force','system/operation-log','clear-force',CONVERT(X'E5BDBBE5BA95E6B885E7A9BA' USING utf8mb4),'route',1,0,sibling.`source_type`,sibling.`source_name`,NOW(),NOW(),40,NULL
FROM `fun_permission` AS sibling
WHERE sibling.`code`='system:operation-log:delete' AND sibling.`deleted_at` IS NULL
  AND NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='system:operation-log:clear-force' AND `deleted_at` IS NULL);

-- 兄弟权限缺失（非标准库）时挂在根下，归属宿主 system。
INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin','system:operation-log:force-delete','system/operation-log','force-delete',CONVERT(X'E5BDBBE5BA95E588A0E999A4' USING utf8mb4),'route',1,0,'system','',NOW(),NOW(),30,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='system:operation-log:force-delete' AND `deleted_at` IS NULL);

INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`created_at`,`updated_at`,`sort_order`,`deleted_at`)
SELECT 0,'admin','system:operation-log:clear-force','system/operation-log','clear-force',CONVERT(X'E5BDBBE5BA95E6B885E7A9BA' USING utf8mb4),'route',1,0,'system','',NOW(),NOW(),40,NULL
WHERE NOT EXISTS (SELECT 1 FROM `fun_permission` WHERE `code`='system:operation-log:clear-force' AND `deleted_at` IS NULL);

-- 2. 已由代码同步提前登记（pid=0、system）的未删除行对齐为同一终态；插件归属的同名行不动。
UPDATE `fun_permission` AS target
JOIN `fun_permission` AS sibling ON sibling.`code`='system:operation-log:delete' AND sibling.`deleted_at` IS NULL
SET target.`pid`=sibling.`pid`, target.`source_type`=sibling.`source_type`, target.`source_name`=sibling.`source_name`,
    target.`app_name`='admin', target.`obj`='system/operation-log', target.`act`='force-delete',
    target.`name`=CONVERT(X'E5BDBBE5BA95E588A0E999A4' USING utf8mb4), target.`resource_type`='route',
    target.`status`=1, target.`sort_order`=30, target.`updated_at`=NOW()
WHERE target.`code`='system:operation-log:force-delete' AND target.`deleted_at` IS NULL AND target.`source_type`<>'plugin';

UPDATE `fun_permission` AS target
JOIN `fun_permission` AS sibling ON sibling.`code`='system:operation-log:delete' AND sibling.`deleted_at` IS NULL
SET target.`pid`=sibling.`pid`, target.`source_type`=sibling.`source_type`, target.`source_name`=sibling.`source_name`,
    target.`app_name`='admin', target.`obj`='system/operation-log', target.`act`='clear-force',
    target.`name`=CONVERT(X'E5BDBBE5BA95E6B885E7A9BA' USING utf8mb4), target.`resource_type`='route',
    target.`status`=1, target.`sort_order`=40, target.`updated_at`=NOW()
WHERE target.`code`='system:operation-log:clear-force' AND target.`deleted_at` IS NULL AND target.`source_type`<>'plugin';
