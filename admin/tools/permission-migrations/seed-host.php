<?php
$work = getenv('PERM_WORK') ?: sys_get_temp_dir() . '/funadmin-permtest';
// 输出：宿主路由 → 旧 obj|act；需要在 MySQL 中按 obj/act 查行并为每行建一个角色。
require dirname(__DIR__, 2) . '/tests/bootstrap.php';
use app\admin\authorization\service\PermissionResource;
$before = json_decode(file_get_contents(base_path() . '/tests/fixtures/auth-requirements-before.json'), true);
$routes = [];
foreach ($before as $key => $req) {
    if ($req['guard'] !== 'admin') continue;
    preg_match('/=> (.+)::(.+)$/', $key, $t);
    $requirement = (new \app\common\auth\AuthRequirementResolver())->for($t[1], $t[2]);
    if (!$requirement->login || !$requirement->permission) continue;
    $r = new stdClass(); $r->controller = $t[1]; $r->action = $t[2];
    $legacy = PermissionResource::fromRequest($r);
    $routes[$key] = ['legacy' => $legacy['obj'] . '|' . $legacy['act'], 'derived' => app\admin\authorization\service\PermissionCode::derive($t[1], $t[2])];
}
file_put_contents($work . '/host-routes.json', json_encode($routes));
$pairs = array_values(array_unique(array_column($routes, 'legacy')));
$sql = "DROP TABLE IF EXISTS tmp_pairs; CREATE TABLE tmp_pairs (obj varchar(190), act varchar(100));\n";
foreach (array_chunk($pairs, 100) as $chunk) $sql .= "INSERT INTO tmp_pairs VALUES " . implode(',', array_map(fn ($p) => "('" . str_replace("'", "''", explode('|', $p)[0]) . "','" . str_replace("'", "''", explode('|', $p)[1]) . "')", $chunk)) . ";\n";
// 每个守卫宿主路由的权限行一个角色：role:(8000+id)
$sql .= "INSERT IGNORE INTO fun_casbin_rule (ptype,v0,v1,v2,v3,v4,v5,rule_hash) SELECT 'p', CONCAT('role:', 80000 + p.id), 'default', p.obj, p.act, '', '', SHA2(CONCAT_WS(CHAR(31),'p',CONCAT('role:', 80000 + p.id),'default',p.obj,p.act),256) FROM fun_permission p JOIN tmp_pairs t ON t.obj = p.obj AND t.act = p.act WHERE p.resource_type = 'route' AND p.deleted_at IS NULL;\n";
// 只持有能力权限的角色（同名冲突检查）
$sql .= "INSERT IGNORE INTO fun_casbin_rule (ptype,v0,v1,v2,v3,v4,v5,rule_hash) SELECT 'p', 'role:99999', 'default', obj, act, '', '', SHA2(CONCAT_WS(CHAR(31),'p','role:99999','default',obj,act),256) FROM fun_permission WHERE resource_type = 'capability' AND deleted_at IS NULL AND source_type <> 'plugin';\n";
// 升级前已被停用（仍有授权）的权限：升级后必须保持停用。
$sql .= "UPDATE fun_permission SET status = 0 WHERE obj = 'admin/systemconfig' AND act IN ('index','delete');\n";
// 空目录（含嵌套）与带权限码的目录：迁移后目录不带码、空目录被删除。
$sql .= "INSERT INTO fun_permission (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,sort_order) VALUES (0,'admin',NULL,'','','空目录-上级','group',1,0,'admin_web','verify',999);\n";
$sql .= "INSERT INTO fun_permission (pid,app_name,code,obj,act,name,resource_type,status,is_public,source_type,source_name,sort_order) SELECT id,'admin',NULL,'','','空目录-下级','group',1,0,'admin_web','verify',999 FROM fun_permission WHERE name='空目录-上级';\n";
if (!is_dir($work)) mkdir($work, 0700, true);
file_put_contents($work . '/host-seed.sql', $sql);
echo count($routes), " host routes, ", count($pairs), " legacy pairs\n";
