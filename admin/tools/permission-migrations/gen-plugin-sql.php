<?php
// 生成 MySQL 核心迁移 025_plugin_permission_per_method.sql（插件），与对应 PHP 迁移等价。
// 用法：php tools/permission-migrations/gen-plugin-sql.php [输出文件]；不传输出文件时覆盖迁移文件（迁移已执行后不要再覆盖）。
require dirname(__DIR__, 2) . '/tests/bootstrap.php';
$output = $argv[1] ?? null;
use app\admin\authorization\service\PermissionCode;
$map = require base_path() . '/database/permission-maps/plugin-per-method.php';
$keep = require base_path() . '/database/permission-maps/plugin-per-method-keep.php';
$menus = require base_path() . '/database/permission-maps/plugin-per-method-menus.php';
$sync = new \app\admin\authorization\service\PermissionSyncService();
$derived = [];
foreach (glob(app_path() . '/admin/plugins/*', GLOB_ONLYDIR) as $dir) foreach ($sync->pluginCodes(basename($dir)) as $code) $derived[$code] = basename($dir);
ksort($derived);
$q = static fn (string $v): string => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
$in = static fn (array $codes): string => implode(',', array_map($q, $codes));
$sql = "-- 插件后台权限按方法细分（与 PHP 迁移 034_plugin_permission_per_method.php 等价）。\n"
     . "-- 1) 登记每个插件后台方法推导出的权限码；2) 把角色持有的旧粗粒度权限展开为它覆盖的新权限；\n"
     . "-- 3) 撤销不再使用的旧授权并停用旧权限行（CMS 站点操作作为能力权限保留）；4) 插件菜单改绑到列表接口权限。\n"
     . "-- 升级前后每个角色能访问的插件接口集合不变，可重复执行。\n\n";
$rows = [];
foreach ($derived as $code => $plugin) {
    ['obj' => $obj, 'act' => $act] = PermissionCode::resource($code);
    $rows[] = "SELECT " . implode(',', [$q($code), $q($obj), $q($act), $q($sync->summary($code) ?? $code), $q($plugin)]);
}
$sql .= "INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`sort_order`)\n"
      . "SELECT 0,'admin',d.code,d.obj,d.act,d.name,'route',1,0,'plugin',d.plugin,999 FROM (\n  "
      . implode("\n  UNION ALL ", $rows)
      . "\n) AS d (`code`,`obj`,`act`,`name`,`plugin`)\nWHERE NOT EXISTS (SELECT 1 FROM `fun_permission` e WHERE e.`code` = d.`code`)\n"
      . "  AND (d.`plugin` IN ('example', 'market') OR EXISTS (SELECT 1 FROM `fun_plugin` i WHERE i.`code` = d.`plugin` AND i.`deleted_at` IS NULL));\n\n";
$sql .= "UPDATE `fun_permission` SET `resource_type` = 'route' WHERE `source_type` = 'plugin' AND `code` IN (" . $in(array_keys($derived)) . ");\n\n";
$named = array_filter(array_keys($derived), fn ($c) => $sync->summary($c) !== null);
if ($named) {
    $sql .= "-- 未命名（名称仍是权限码）的推导码补上默认名称（方法 OpenAPI summary）。\n";
    foreach ($named as $code) $sql .= "UPDATE `fun_permission` SET `name` = " . $q($sync->summary($code)) . " WHERE `code` = " . $q($code) . " AND `name` = `code`;\n";
    $sql .= "\n";
}
$pairs = [];
foreach ($map as $old => $targets) foreach ($targets as $new) {
    ['obj' => $obj, 'act' => $act] = PermissionCode::resource($new);
    $pairs[] = "SELECT " . implode(',', [$q($old), $q($obj), $q($act)]);
}
$sql .= "-- 展开授权：持有旧权限的每个 (角色, 域) 获得映射到的全部新权限。\n"
      . "INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)\n"
      . "SELECT 'p', r.`v0`, r.`v1`, m.`obj`, m.`act`, '', '', SHA2(CONCAT_WS(CHAR(31), 'p', r.`v0`, r.`v1`, m.`obj`, m.`act`), 256)\n"
      . "FROM (\n  " . implode("\n  UNION ALL ", $pairs) . "\n) AS m (`old_code`,`obj`,`act`)\n"
      . "JOIN `fun_permission` o ON o.`code` = m.`old_code`\n"
      . "JOIN `fun_casbin_rule` r ON r.`ptype` = 'p' AND r.`v2` = o.`obj` AND r.`v3` = o.`act`;\n\n";
$newCodes = array_unique(array_merge(...array_values($map)));
$retired = array_values(array_diff(array_keys($map), $newCodes, $keep));
$newResources = array_map(static fn ($c) => PermissionCode::resource($c), $newCodes);
$targetsOf = [];
foreach ($map as $old => $targets) foreach ($targets as $t) $targetsOf[$t][] = $old;
$sql .= "-- 停用状态随之迁移：一个新码的全部来源旧码都已停用时，新码同样停用（插件启用时会重新启用）。\n";
foreach ($targetsOf as $t => $olds) {
    $sql .= "UPDATE `fun_permission` n JOIN (SELECT MAX(o.`status`) AS `s`, COUNT(*) AS `c` FROM `fun_permission` o WHERE o.`code` IN (" . $in($olds) . ") AND EXISTS (SELECT 1 FROM `fun_casbin_rule` r WHERE r.`ptype` = 'p' AND r.`v2` = o.`obj` AND r.`v3` = o.`act`)) src ON src.`c` > 0 AND src.`s` = 0 SET n.`status` = 0 WHERE n.`code` = " . $q($t) . ";\n";
}
$sql .= "\n";
$sql .= "-- 撤销不再使用的旧授权（仍被新权限复用的 obj/act 保留），并停用旧权限行。\n"
      . "DELETE r FROM `fun_casbin_rule` r JOIN `fun_permission` o ON r.`ptype` = 'p' AND r.`v2` = o.`obj` AND r.`v3` = o.`act`\n"
      . "WHERE o.`code` IN (" . $in($retired) . ")\n"
      . "  AND (o.`obj`, o.`act`) NOT IN (" . implode(',', array_map(static fn ($r) => '(' . $q($r['obj']) . ',' . $q($r['act']) . ')', $newResources)) . ");\n"
      . "UPDATE `fun_permission` SET `status` = 0 WHERE `code` IN (" . $in($retired) . ");\n\n";
$sql .= "-- 插件菜单改绑：绑定旧权限码的菜单改为绑定对应列表接口的推导码，并同步 query 中的 permission 参数。\n";
foreach ($menus as $old => $new) {
    $sql .= "UPDATE `fun_admin_menu` m JOIN `fun_permission` o ON o.`id` = m.`permission_id` AND o.`code` = " . $q($old)
          . " JOIN `fun_permission` n ON n.`code` = " . $q($new)
          . " SET m.`permission_id` = n.`id`, m.`query` = REGEXP_REPLACE(REGEXP_REPLACE(m.`query`, " . $q('(^|&)permission=' . preg_quote(rawurlencode($old), '/') . '(&|$)') . ", " . $q('$1permission=' . rawurlencode($new) . '$2') . "), "
          . $q('(^|&)permission=' . preg_quote($old, '/') . '(&|$)') . ", " . $q('$1permission=' . $new . '$2') . ");\n";
}
file_put_contents($output ?? base_path() . '/database/core/migrations/archive/baseline-2026-11-06/025_plugin_permission_per_method.sql', $sql);
echo count($rows), " plugin codes, ", count($pairs), " expansion pairs, ", count($retired), " retired, ", count($named), " named\n";
