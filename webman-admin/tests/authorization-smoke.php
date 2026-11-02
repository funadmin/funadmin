<?php

declare(strict_types=1);

if (getenv('AUTH_HTTP_TEST') !== '1' || !is_file('/sandbox/public/install.lock')) throw new RuntimeException('仅允许隔离授权验收');
define('BASE_PATH', '/sandbox');
require '/app/vendor/autoload.php';
Dotenv\Dotenv::createUnsafeMutable('/sandbox')->load();
Webman\Config::load('/app/config', ['route']);
if (config('database.connections.mysql.host') !== 'funadmin-az-db') throw new RuntimeException('隔离数据库检查失败');
support\Db::table('login_attempts')->where('key', hash('sha256', '127.0.0.1'))->delete();
$password = 'Az-' . bin2hex(random_bytes(16));
$suffix = bin2hex(random_bytes(4));
support\Db::table('admin')->where('id', 1)->update(['password' => password_hash($password, PASSWORD_DEFAULT)]);
$cookie = '';
$csrf = '';
function azAssert(bool $ok, string $label): void
{
    if (!$ok) throw new RuntimeException($label);
    echo '通过：' . $label . PHP_EOL;
}
function azCall(string $path, string $method = 'GET', array $data = [], bool $withCsrf = true): array
{
    global $cookie, $csrf;
    $headers = "Content-Type: application/json\r\nConnection: close\r\nCookie: $cookie\r\n";
    if ($withCsrf) $headers .= "X-CSRF-TOKEN: $csrf\r\n";
    $body = file_get_contents('http://127.0.0.1:8787' . $path, false, stream_context_create(['http' => [
        'method' => $method, 'header' => $headers, 'content' => $method === 'GET' ? '' : json_encode($data), 'ignore_errors' => true, 'timeout' => 20,
    ]]));
    $wire = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $wire[0], $status);
    foreach ($wire as $header) {
        if (stripos($header, 'X-CSRF-TOKEN: ') === 0) $csrf = substr($header, 14);
        if (stripos($header, 'Set-Cookie: WEBMAN_ADMIN_SESSION=') === 0) $cookie = str_contains($header, 'Max-Age=0') ? '' : explode(';', substr($header, 12))[0];
    }
    return [(int) $status[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
function azLogin(string $username, string $password): void
{
    azAssert(azCall('/admin/auth/csrf')[0] === 200, 'CSRF 初始化');
    azAssert(azCall('/admin/auth/login', 'POST', compact('username', 'password'))[0] === 200, '验收账号登录');
}
azAssert(azCall('/admin/system/role')[0] === 401, '匿名请求不能读取角色');
azLogin('installer', $password);
$superCookie = $cookie;
$superCsrf = $csrf;
azAssert(azCall('/admin/system/role', 'POST', ['name' => '拒绝请求', 'code' => 'denied'], false)[0] === 403, '缺少 CSRF 不能创建角色');
[$status, $role] = azCall('/admin/system/role', 'POST', ['name' => '验收角色' . $suffix, 'code' => 'az_reader_' . $suffix, 'level' => 10, 'dataScope' => 'self']);
azAssert($status === 200 && $role['code'] === 200, '创建角色：' . ($role['msg'] ?? ''));
$roleId = $role['data']['id'];
[$status, $copy] = azCall('/admin/system/role', 'POST', ['name' => '复制角色' . $suffix, 'code' => 'az_copy_' . $suffix, 'level' => 20, 'dataScope' => 'self']);
azAssert($copy['code'] === 200, '创建授权复制目标');
$copyId = $copy['data']['id'];
[$status, $permission] = azCall('/admin/system/permission', 'POST', ['name' => '验收能力', 'resourceType' => 'capability', 'object' => 'az/demo' . $suffix, 'action' => 'read']);
azAssert($permission['code'] === 200, '创建能力权限');
$permissionId = $permission['data']['id'];
$fieldId = support\Db::table('permission_field')->insertGetId(['permission_id' => $permissionId, 'resource' => 'az/demo' . $suffix, 'field' => 'title', 'name' => '标题', 'status' => 1]);
[$status, $department] = azCall('/admin/system/dept', 'POST', ['name' => '验收部门' . $suffix, 'parentId' => 0, 'status' => 1]);
azAssert($department['code'] === 200, '创建部门');
$deptId = $department['data']['id'];
$listPermission = support\Db::table('permission')->where('code', 'systemrole:index')->value('id');
$allPermission = support\Db::table('permission')->where('code', 'systemrole:all')->value('id');
$grant = ['permissionIds' => [$permissionId, $listPermission, $allPermission], 'fieldPermissions' => [['fieldId' => $fieldId, 'view' => true, 'edit' => false]], 'dataScope' => 'custom', 'departmentIds' => [$deptId]];
azAssert(azCall("/admin/system/role/$roleId/authorization", 'PUT', $grant)[1]['code'] === 200, '保存功能、字段和数据范围');
[$status, $detail] = azCall("/admin/system/role/$roleId/authorization");
azAssert($detail['code'] === 200 && in_array($permissionId, $detail['data']['effectivePermissionIds'], true) && count(array_filter($detail['data']['fields'], static fn (array $field): bool => $field['id'] === $fieldId && $field['view'])) === 1, '授权详情回读完整');
azAssert(azCall("/admin/system/role/$copyId/authorization/copy", 'POST', ['sourceRoleId' => $roleId])[1]['code'] === 200, '复制整套授权');
[$status, $menu] = azCall('/admin/system/menu', 'POST', ['name' => '验收能力页面', 'path' => '/admin/az-demo-' . $suffix, 'routeName' => 'AzDemo' . $suffix, 'component' => 'webman/management', 'type' => 'C', 'permissionId' => $permissionId]);
azAssert($menu['code'] === 200, '创建菜单并绑定能力');
foreach (['menu/tree', 'menu/permission-options', 'permission/tree', 'dept/tree', 'role/all', 'role/parent-options'] as $path) {
    [$status, $result] = azCall('/admin/system/' . $path);
    azAssert($status === 200 && $result['code'] === 200, '读取 ' . $path);
}
[$status, $user] = azCall('/admin/system/user', 'POST', ['username' => 'az_operator_' . $suffix, 'nickname' => '普通操作员', 'password' => $password, 'roleIds' => [$roleId], 'deptId' => $deptId]);
azAssert($user['code'] === 200, '创建普通管理员');
azCall('/admin/auth/logout', 'POST');
azLogin('az_operator_' . $suffix, $password);
[$status, $info] = azCall('/admin/auth/me');
azAssert($status === 200 && in_array('system:role:list', $info['data']['permissions'], true) && !in_array('*', $info['data']['permissions'], true), '普通账号下发精确权限与前端别名');
azAssert(azCall('/admin/system/role')[1]['code'] === 200, '普通账号已授予的读取允许');
azAssert(azCall('/admin/system/permission/tree')[0] === 403, '普通账号不能维护权限资源');
azAssert(azCall('/admin/system/role', 'POST', ['name' => '越权', 'code' => 'bad'])[0] === 403, '未授予写权限拒绝');
[$status, $menus] = azCall('/admin/auth/menus');
azAssert($status === 200 && str_contains(json_encode($menus), 'AzDemo'), '已授予能力菜单可见');
$ordinaryCookie = $cookie;
$ordinaryCsrf = $csrf;
$cookie = $superCookie;
$csrf = $superCsrf;
// 前面退出已撤销旧超级会话，重新引导登录后撤销普通角色权限。
azLogin('installer', $password);
azAssert(azCall("/admin/system/role/$roleId/authorization", 'PUT', ['permissionIds' => [], 'fieldPermissions' => [], 'dataScope' => 'self', 'departmentIds' => []])[1]['code'] === 200, '撤销授权');
$cookie = $ordinaryCookie;
$csrf = $ordinaryCsrf;
azAssert(azCall('/admin/auth/me')[0] === 200, '撤权后旧登录仍有效');
azAssert(azCall('/admin/system/role')[0] === 403, '撤权后旧会话立即拒绝读取');
azAssert(!str_contains(json_encode(azCall('/admin/auth/menus')[1]), 'AzDemo' . $suffix), '撤权后能力菜单立即消失');
echo '隔离授权 HTTP 验收完成' . PHP_EOL;
