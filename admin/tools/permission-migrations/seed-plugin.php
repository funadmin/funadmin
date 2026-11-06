<?php
$work = getenv('PERM_WORK') ?: sys_get_temp_dir() . '/funadmin-permtest';
// 升级前状态：按冻结映射登记全部旧插件权限码与 CMS 站点操作；每个旧权限码一个独立角色；插件菜单绑定旧码。
require dirname(__DIR__, 2) . '/tests/bootstrap.php';
$map = require base_path() . '/database/permission-maps/plugin-per-method.php';
$keep = require base_path() . '/database/permission-maps/plugin-per-method-keep.php';
$menus = require base_path() . '/database/permission-maps/plugin-per-method-menus.php';
$q = static fn (string $v): string => "'" . str_replace("'", "''", $v) . "'";
$sql = '';
foreach (array_unique(array_merge(array_keys($map), $keep)) as $code) {
    $seg = explode(':', $code); $ns = array_shift($seg); $act = array_pop($seg); $obj = implode('/', array_merge([$ns], $seg));
    $sql .= "INSERT IGNORE INTO fun_permission (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,sort_order) SELECT 0,'admin',{$q($code)},{$q($obj)},{$q($act)},{$q($code)},'capability',1,0,'plugin',{$q($ns)},999 FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM fun_permission WHERE code={$q($code)});\n";
}
$roleId = 7000; $roles = [];
foreach (array_unique(array_merge(array_keys($map), $keep)) as $old) $roles[++$roleId] = [$old];
$roles[++$roleId] = ['market:order:view', 'cms:site:list'];
foreach ($roles as $id => $codes) foreach ($codes as $code) {
    $sql .= "INSERT IGNORE INTO fun_casbin_rule (ptype,v0,v1,v2,v3,v4,v5,rule_hash) SELECT 'p','role:$id','default',obj,act,'','',SHA2(CONCAT_WS(CHAR(31),'p','role:$id','default',obj,act),256) FROM fun_permission WHERE code={$q($code)};\n";
}
$i = 0;
foreach ($menus as $old => $new) {
    $i++; $ns = explode(':', $old)[0];
    $sql .= "INSERT INTO fun_admin_menu (pid,permission_id,app_name,name,href,query,status,source_type,source_name) SELECT 0,id,'admin',{$q($old)},{$q('/plugin/test/m' . $i)},{$q('type=C&permission=' . rawurlencode($old))},1,'plugin',{$q($ns)} FROM fun_permission WHERE code={$q($old)};\n";
}
// 已安装的插件（025 只为已安装插件登记推导码）；另停用两个仍有授权的旧码，验证停用状态被保留。
$sql .= "INSERT INTO fun_plugin (name, code) VALUES ('CMS','cms'),('QA','qa');\n";
$sql .= "UPDATE fun_permission SET status = 0 WHERE code IN ('cms:category:save','qa:moderation:list');\n";
if (!is_dir($work)) mkdir($work, 0700, true);
file_put_contents($work . '/permtest-seed.sql', $sql);
file_put_contents($work . '/permtest-roles.json', json_encode($roles));
echo count($roles), " roles, ", count($menus), " menus seeded\n";
