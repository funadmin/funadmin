<?php
// 生成 MySQL 核心迁移 027_host_permission_per_method.sql（宿主），与对应 PHP 迁移等价。
// 用法：php tools/permission-migrations/gen-host-sql.php [输出文件]；不传输出文件时覆盖迁移文件（迁移已执行后不要再覆盖）。
require dirname(__DIR__, 2) . '/tests/bootstrap.php';
$output = $argv[1] ?? null;
use app\admin\authorization\service\PermissionCode;
$map = require base_path() . '/database/permission-maps/host-per-method.php';
$menus = require base_path() . '/database/permission-maps/host-menus.php';
$sync = new \app\admin\authorization\service\PermissionSyncService();
$derived = array_filter($sync->derivedCodes(), fn ($o) => $o['type'] === 'system');
$q = static fn (string $v): string => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $v) . "'";
$in = static fn (array $codes): string => implode(',', array_map($q, $codes));
$targets = array_values(array_unique(array_merge(...array_values($map))));
$sql = "-- 宿主后台权限按方法细分（与 PHP 迁移 036_host_permission_per_method.php 等价）。\n"
     . "-- 旧权限行原地改名为按方法推导的权限码（保留 ID、名称、菜单与字段授权）；持有旧权限的角色获得它守卫的全部方法；\n"
     . "-- 与新码同名的能力权限先停用并撤销授权；迁移前未登记的接口只登记、不授予；核心菜单绑定页面列表方法的推导码。\n"
     . "-- 升级前后每个角色能访问的宿主接口集合不变，可重复执行。\n\n";
$sql .= "-- 1) 与推导码同名的能力权限：撤销授权并释放权限码（保留行以便追溯）。\n"
      . "DELETE r FROM `fun_casbin_rule` r JOIN `fun_permission` c ON r.`ptype` = 'p' AND r.`v2` = c.`obj` AND r.`v3` = c.`act`\nWHERE c.`code` IN (" . $in($targets) . ") AND c.`resource_type` <> 'route';\n"
      . "UPDATE `fun_permission` SET `code` = NULL, `act` = CONCAT(`act`, '-retired'), `status` = 0 WHERE `code` IN (" . $in($targets) . ") AND `resource_type` <> 'route';\n\n";
$sql .= "-- 2) 逐个旧资源迁移。\n";
foreach ($map as $resource => $codes) {
    [$obj, $act] = explode('|', $resource, 2);
    $first = $codes[0];
    $legacy = "(SELECT MIN(`id`) AS `id` FROM `fun_permission` WHERE `obj` = {$q($obj)} AND `act` = {$q($act)} AND `resource_type` = 'route' AND `deleted_at` IS NULL)";
    $sql .= "-- {$obj} {$act} → " . implode(', ', $codes) . "\n";
    // 新码已存在（非旧行）：菜单、字段授权、子级改挂新行。
    foreach ([['fun_admin_menu', 'permission_id'], ['fun_permission_field', 'permission_id'], ['fun_permission', 'pid']] as [$table, $column]) {
        $sql .= "UPDATE `{$table}` t JOIN {$legacy} f ON t.`{$column}` = f.`id` JOIN `fun_permission` e ON e.`code` = {$q($first)} AND e.`id` <> f.`id` SET t.`{$column}` = e.`id`;\n";
    }
    // 菜单 query 中的 permission 参数改为新码。
    $sql .= "UPDATE `fun_admin_menu` m JOIN {$legacy} f JOIN `fun_permission` o ON o.`id` = f.`id` JOIN `fun_permission` k ON k.`id` = IF(EXISTS (SELECT 1 FROM `fun_permission` e WHERE e.`code` = {$q($first)} AND e.`id` <> o.`id`), (SELECT e.`id` FROM `fun_permission` e WHERE e.`code` = {$q($first)} LIMIT 1), o.`id`)\n"
          . "  SET m.`query` = REGEXP_REPLACE(REGEXP_REPLACE(m.`query`, CONCAT('(^|&)permission=', REPLACE(REPLACE(o.`code`, '.', '\\\\.'), ':', '%3A'), '(&|$)'), CONCAT('$1permission=', {$q(rawurlencode($first))}, '$2')),\n"
          . "    CONCAT('(^|&)permission=', REPLACE(o.`code`, '.', '\\\\.'), '(&|$)'), CONCAT('$1permission=', {$q($first)}, '$2'))\n"
          . "  WHERE m.`permission_id` = k.`id` AND o.`code` IS NOT NULL AND o.`code` <> {$q($first)};\n";
    foreach ($codes as $code) {
        ['obj' => $newObj, 'act' => $newAct] = PermissionCode::resource($code);
        $sql .= "INSERT IGNORE INTO `fun_casbin_rule` (`ptype`,`v0`,`v1`,`v2`,`v3`,`v4`,`v5`,`rule_hash`)\n"
              . "  SELECT 'p', r.`v0`, r.`v1`, {$q($newObj)}, {$q($newAct)}, '', '', SHA2(CONCAT_WS(CHAR(31), 'p', r.`v0`, r.`v1`, {$q($newObj)}, {$q($newAct)}), 256)\n"
              . "  FROM `fun_casbin_rule` r JOIN {$legacy} f ON f.`id` IS NOT NULL WHERE r.`ptype` = 'p' AND r.`v2` = {$q($obj)} AND r.`v3` = {$q($act)};\n";
    }
    $sql .= "DELETE r FROM `fun_casbin_rule` r JOIN {$legacy} f ON f.`id` IS NOT NULL WHERE r.`ptype` = 'p' AND r.`v2` = {$q($obj)} AND r.`v3` = {$q($act)};\n";
    foreach (array_slice($codes, 1) as $code) {
        ['obj' => $newObj, 'act' => $newAct] = PermissionCode::resource($code);
        $sql .= "INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`sort_order`)\n"
              . "  SELECT o.`pid`, o.`app_name`, {$q($code)}, {$q($newObj)}, {$q($newAct)}, o.`name`, 'route', o.`status`, o.`is_public`, o.`source_type`, o.`source_name`, o.`sort_order`\n"
              . "  FROM `fun_permission` o JOIN {$legacy} f ON o.`id` = f.`id` WHERE NOT EXISTS (SELECT 1 FROM (SELECT `code` FROM `fun_permission`) e WHERE e.`code` = {$q($code)});\n";
    }
    $sql .= "UPDATE `fun_permission` e JOIN {$legacy} f JOIN `fun_permission` o ON o.`id` = f.`id` SET e.`is_public` = GREATEST(e.`is_public`, o.`is_public`), e.`status` = LEAST(e.`status`, o.`status`) WHERE e.`code` = {$q($first)} AND e.`id` <> o.`id`;\n";
    ['obj' => $newObj, 'act' => $newAct] = PermissionCode::resource($first);
    $sql .= "UPDATE `fun_permission` o JOIN {$legacy} f ON o.`id` = f.`id` LEFT JOIN `fun_permission` e ON e.`code` = {$q($first)} AND e.`id` <> o.`id`\n"
          . "  SET o.`status` = IF(e.`id` IS NULL, o.`status`, 0), o.`code` = IF(e.`id` IS NULL, {$q($first)}, o.`code`), o.`obj` = IF(e.`id` IS NULL, {$q($newObj)}, o.`obj`), o.`act` = IF(e.`id` IS NULL, {$q($newAct)}, o.`act`);\n\n";
}
$sql .= "-- 3) 登记其余按方法推导的宿主权限码（只登记，不授予）。\n";
$rows = [];
foreach ($derived as $code => $owner) {
    ['obj' => $obj, 'act' => $act] = PermissionCode::resource($code);
    $rows[] = 'SELECT ' . implode(',', [$q($code), $q($obj), $q($act), $q($sync->summary($code) ?? $code)]);
}
$sql .= "INSERT INTO `fun_permission` (`pid`,`app_name`,`code`,`obj`,`act`,`name`,`resource_type`,`status`,`is_public`,`source_type`,`source_name`,`sort_order`)\n"
      . "SELECT 0,'admin',d.`code`,d.`obj`,d.`act`,d.`name`,'route',1,0,'system','',999 FROM (\n  " . implode("\n  UNION ALL ", $rows)
      . "\n) AS d (`code`,`obj`,`act`,`name`)\nWHERE NOT EXISTS (SELECT 1 FROM `fun_permission` e WHERE e.`code` = d.`code`);\n";
$sql .= "UPDATE `fun_permission` SET `resource_type` = 'route' WHERE `source_type` <> 'plugin' AND `code` IN (" . $in(array_keys($derived)) . ");\n\n";
$sql .= "-- 4) 核心菜单绑定页面列表方法的推导码。\n";
foreach ($menus as $name => $code) {
    $sql .= "UPDATE `fun_admin_menu` m JOIN `fun_permission` p ON p.`code` = {$q($code)} AND p.`status` = 1\n"
          . "  SET m.`permission_id` = p.`id`, m.`query` = IF(m.`query` REGEXP '(^|&)permission=', REGEXP_REPLACE(m.`query`, '(^|&)permission=[^&]*', CONCAT('$1permission=', {$q($code)})), CONCAT(m.`query`, IF(m.`query` = '', '', '&'), 'permission=', {$q($code)}))\n"
          . "  WHERE m.`source_type` = 'admin_web' AND m.`query` REGEXP " . $q('(^|&)name=' . $name . '(&|$)') . ";\n";
}
$sql .= "\n-- 5) 目录只是权限树里的分组节点：清掉目录行上遗留的权限码与资源；删除没有子权限、未被菜单或字段授权引用的空目录（逐层处理嵌套）。\n"
      . "UPDATE `fun_permission` SET `code` = NULL, `obj` = '', `act` = '' WHERE `resource_type` = 'group' AND (`code` IS NOT NULL OR `obj` <> '' OR `act` <> '');\n";
for ($level = 0; $level < 6; $level++) {
    $sql .= "DELETE g FROM `fun_permission` g LEFT JOIN `fun_permission` c ON c.`pid` = g.`id` LEFT JOIN `fun_admin_menu` m ON m.`permission_id` = g.`id` LEFT JOIN `fun_permission_field` f ON f.`permission_id` = g.`id`\n"
          . "  WHERE g.`resource_type` = 'group' AND c.`id` IS NULL AND m.`id` IS NULL AND f.`id` IS NULL;\n";
}
file_put_contents($output ?? base_path() . '/database/core/migrations/archive/baseline-2026-11-06/027_host_permission_per_method.sql', $sql);
echo count($map), " legacy resources, ", count($derived), " derived host codes, ", count($menus), " core menus\n";
