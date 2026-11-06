<?php
$work = getenv('PERM_WORK') ?: sys_get_temp_dir() . '/funadmin-permtest';
require dirname(__DIR__, 2) . '/tests/bootstrap.php';
$before = json_decode(file_get_contents(base_path() . '/tests/fixtures/auth-requirements-before.json'), true);
$roles = json_decode(file_get_contents($work . '/permtest-roles.json'), true);
$keep = require base_path() . '/database/permission-maps/plugin-per-method-keep.php';
$menus = require base_path() . '/database/permission-maps/plugin-per-method-menus.php';
$perms = [];
foreach (file($work . '/mysql-perms.txt', FILE_IGNORE_NEW_LINES) as $line) { [$code, $obj, $act, $status, $type, $id] = explode("\t", $line); $perms[$code] = ['obj' => $obj, 'act' => $act, 'status' => (int) $status, 'type' => $type, 'id' => (int) $id]; }
$permsBefore = [];
foreach (file($work . '/mysql-perms-before.txt', FILE_IGNORE_NEW_LINES) as $line) { [$code, , , $status] = explode("\t", $line); $permsBefore[$code] = (int) $status; }
$grants = [];
foreach (file($work . '/mysql-after.txt', FILE_IGNORE_NEW_LINES) as $line) { [, $role, , $obj, $act] = explode("\t", $line); $grants[$role][$obj . '|' . $act] = true; }
$checked = $mismatch = 0;
foreach ($before as $routeKey => $req) {
    if ($req['guard'] !== 'plugin') continue;
    preg_match('/=> (.+)::(.+)$/', $routeKey, $t);
    $new = \app\admin\authorization\service\PermissionCode::derive($t[1], $t[2]);
    $res = \app\admin\authorization\service\PermissionCode::resource($new);
    $active = ($perms[$new]['status'] ?? 0) === 1 && ($perms[$new]['type'] ?? '') === 'route';
    $old = array_values(array_filter((array) $req['permission'], fn ($c) => is_string($c) && !str_starts_with($c, '!')));
    foreach ($roles as $id => $codes) {
        $had = array_filter($old, fn ($c) => in_array($c, $codes, true) && ($permsBefore[$c] ?? 0) === 1) !== [];
        $has = $active && isset($grants['role:' . $id][$res['obj'] . '|' . $res['act']]);
        $checked++;
        if ($had !== $has) { $mismatch++; if ($mismatch <= 8) echo "MISMATCH role:$id [" . implode(',', $codes) . "] $routeKey had=" . var_export($had, true) . " has=" . var_export($has, true) . "\n"; }
    }
}
echo "checked $checked (role,route) pairs on MySQL, mismatches: $mismatch\n";
$bad = 0;
foreach ($keep as $op) {
    $role = array_search([$op], $roles, true);
    $p = $perms[$op] ?? null;
    if (!$p || $p['status'] !== 1 || !isset($grants['role:' . $role][$p['obj'] . '|' . $p['act']])) { $bad++; echo "KEEP FAIL $op\n"; }
}
echo "kept site operations: ", count($keep) - $bad, "/", count($keep), " active with grants\n";
$menuRows = [];
foreach (file($work . '/mysql-menus.txt', FILE_IGNORE_NEW_LINES) as $line) { [$name, $pid, $query] = explode("\t", $line); $menuRows[$name] = [(int) $pid, $query]; }
$ok = 0;
foreach ($menus as $old => $new) {
    [$pid, $query] = $menuRows[$old];
    if ($pid === $perms[$new]['id'] && $query === 'type=C&permission=' . rawurlencode($new)) $ok++; else echo "MENU FAIL $old => pid=$pid query=$query\n";
}
echo "menus rebound: $ok/", count($menus), "\n";

exit($mismatch > 0 || $bad > 0 || $ok !== count($menus) ? 1 : 0);
