<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
Webman\Config::load(config_path(), ['route']);
date_default_timezone_set(config('app.default_timezone'));

use app\service\AuthService;
use support\Db;

$username = 'browser_' . bin2hex(random_bytes(5));
$password = bin2hex(random_bytes(24));
$admin = (new AuthService())->createAdmin($username, $password);
Db::table('admins')->where('id', $admin['id'])->update(['is_super_admin' => true]);
Db::table('login_attempts')->where('key', hash('sha256', '127.0.0.1'))->delete();
$cookie = '';
$csrf = '';
$created = [];

function browserCall(string $path, string $method = 'GET', array $data = [], bool $withCsrf = true): array
{
    global $cookie, $csrf;
    $headers = "Content-Type: application/json\r\nCookie: $cookie\r\n";
    if ($withCsrf) $headers .= "X-CSRF-TOKEN: $csrf\r\n";
    $body = file_get_contents('http://127.0.0.1:8787' . $path, false, stream_context_create(['http' => [
        'method' => $method, 'header' => $headers, 'content' => $method === 'GET' ? '' : json_encode($data), 'ignore_errors' => true, 'timeout' => 10,
    ]]));
    $responseHeaders = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches);
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Set-Cookie: WEBMAN_ADMIN_SESSION=') === 0) {
            $cookie = explode(';', substr($header, strlen('Set-Cookie: ')))[0];
            if (!str_contains($header, 'HttpOnly') || !str_contains($header, 'SameSite=Lax')) throw new RuntimeException('Cookie 安全标记缺失');
        }
        if (stripos($header, 'X-CSRF-TOKEN: ') === 0) $csrf = substr($header, strlen('X-CSRF-TOKEN: '));
    }
    return [(int) $matches[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
function verify(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
    echo "通过：$label\n";
}

try {
    [$status, $result] = browserCall('/admin/auth/csrf');
    verify($status === 200 && $result['code'] === 200 && $csrf !== '', '登录页 CSRF 引导及安全 Cookie');
    $oldCookie = $cookie;
    verify(browserCall('/admin/auth/login', 'POST', compact('username', 'password'), false)[0] === 403, '无 CSRF 不允许登录');
    [$status, $result] = browserCall('/admin/auth/login', 'POST', compact('username', 'password'));
    verify($status === 200 && $result['data'] === ['authenticated' => true] && $cookie !== $oldCookie, '登录协议兼容且轮换会话');
    verify(!str_contains(json_encode($result), 'access_token'), '登录响应不暴露 Token');
    verify(browserCall('/admin/auth/me')[1]['data']['roles'] === ['super_admin'], '身份和权限初始化');
    verify(browserCall('/admin/auth/menus')[0] === 200, '登录后菜单初始化');
    $cookieBefore = $cookie;
    $cookie = $oldCookie;
    verify(browserCall('/admin/auth/me')[0] === 401, '旧匿名会话不能复用');
    $cookie = $cookieBefore;
    verify(browserCall('/admin/management/roles', 'POST', ['name' => 'CSRF拒绝', 'code' => 'denied'], false)[0] === 403, '后台写操作校验 CSRF');
    [$status, $permission] = browserCall('/admin/management/permissions', 'POST', ['name' => '测试查看', 'code' => 'smoke:' . $username]);
    verify($status === 200, '创建功能权限');
    $created[] = ['permissions', $permission['data']['id']];
    [$status, $role] = browserCall('/admin/management/roles', 'POST', ['name' => '测试角色', 'code' => $username, 'permissionIds' => [$permission['data']['id']]]);
    verify($status === 200, '创建角色并分配权限');
    $created[] = ['roles', $role['data']['id']];
    [$status, $user] = browserCall('/admin/management/users', 'POST', ['username' => $username . '_u', 'password' => $password, 'roleIds' => [$role['data']['id']]]);
    verify($status === 200 && !isset($user['data']['password']), '创建管理员并关联角色');
    $created[] = ['users', $user['data']['id']];
    verify(browserCall('/admin/management/users/' . $user['data']['id'], 'PUT', ['nickname' => '更新昵称'])[0] === 200, '更新管理员');
    verify(browserCall('/admin/management/users?pageSize=100000')[1]['data']['pageSize'] === 100, '管理列表分页上限');
    foreach (array_reverse($created) as [$resource, $id]) verify(browserCall("/admin/management/$resource/$id", 'DELETE')[0] === 200, '删除 ' . $resource);
    $created = [];
    verify(browserCall('/admin/auth/logout', 'POST', [], false)[0] === 403, '无 CSRF 不允许退出');
    verify(browserCall('/admin/auth/logout', 'POST')[0] === 200, '浏览器退出');
    $cookie = $cookieBefore;
    verify(browserCall('/admin/auth/me')[0] === 401, '退出后旧 Cookie 被撤销');
} finally {
    foreach (array_reverse($created) as [$resource, $id]) {
        $table = $resource === 'users' ? 'admins' : $resource;
        Db::table($table)->where('id', $id)->delete();
    }
    Db::table('admins')->where('id', $admin['id'])->delete();
}
