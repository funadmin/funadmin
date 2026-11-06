<?php
$work = getenv('PERM_WORK') ?: sys_get_temp_dir() . '/funadmin-permtest';
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$routes = json_decode(file_get_contents($work . '/host-routes.json'), true);
$read = function (string $file): array { $rows = []; foreach (file($file, FILE_IGNORE_NEW_LINES) as $l) $rows[] = explode("\t", $l); return $rows; };
// before: permission rows (id, code, obj, act, status, is_public, type)
$beforePerm = []; foreach ($read($work . '/h-before-perm.txt') as [$id, $code, $obj, $act, $status, $public, $type]) if ($type === 'route') $beforePerm[$obj . '|' . $act] = ['id' => (int) $id, 'status' => (int) $status, 'public' => (int) $public];
$afterPerm = []; foreach ($read($work . '/h-after-perm.txt') as [$id, $code, $obj, $act, $status, $public, $type]) if ($code !== 'NULL') $afterPerm[$code] = ['obj' => $obj, 'act' => $act, 'status' => (int) $status, 'public' => (int) $public, 'type' => $type];
$beforeGrant = []; foreach ($read($work . '/h-before-grant.txt') as [$role, $obj, $act]) $beforeGrant[$role][$obj . '|' . $act] = true;
$afterGrant = []; foreach ($read($work . '/h-after-grant.txt') as [$role, $obj, $act]) $afterGrant[$role][$obj . '|' . $act] = true;
$roles = array_unique(array_merge(array_keys($beforeGrant), ['role:none']));
$checked = $mismatch = 0;
foreach ($routes as $key => $r) {
    $b = $beforePerm[$r['legacy']] ?? null; $a = $afterPerm[$r['derived']] ?? null;
    foreach ($roles as $role) {
        $had = $b !== null && $b['status'] === 1 && ($b['public'] === 1 || isset($beforeGrant[$role][$r['legacy']]));
        $has = $a !== null && $a['status'] === 1 && $a['type'] === 'route' && ($a['public'] === 1 || isset($afterGrant[$role][$a['obj'] . '|' . $a['act']]));
        $checked++;
        if ($had !== $has) { $mismatch++; if ($mismatch <= 10) echo "MISMATCH $role $key had=", var_export($had, true), " has=", var_export($has, true), "\n"; }
    }
}
echo "checked $checked (role, host route) pairs, mismatches: $mismatch\n";
$unreg = 0; foreach ($routes as $r) if (!isset($afterPerm[$r['derived']])) $unreg++;
echo "host routes without registered derived code after: $unreg\n";

exit($mismatch > 0 || $unreg > 0 ? 1 : 0);
