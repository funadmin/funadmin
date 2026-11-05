<?php

declare(strict_types=1);

// 此测试只允许运行在隔离安装验收容器。
if (getenv('AUTH_HTTP_TEST') !== '1' || !is_file('/sandbox/public/install.lock')) throw new RuntimeException('仅允许隔离认证测试环境');
define('BASE_PATH', '/sandbox');
require '/app/vendor/autoload.php';
Dotenv\Dotenv::createUnsafeMutable('/sandbox')->load();
Webman\Config::load('/app/config', ['route']);

$database = (string) config('database.connections.mysql.database');
if (!str_starts_with($database, 'funadmin_install_') || config('database.connections.mysql.host') !== 'funadmin-auth-check-db') throw new RuntimeException('隔离数据库保护检查失败');
$username = 'auth_' . bin2hex(random_bytes(4));
$password = 'Auth-' . bin2hex(random_bytes(16));
$nextPassword = 'New-' . bin2hex(random_bytes(16));
$admin = (new app\admin\authentication\service\AdminSessionService())->createAdmin($username, $password);
$cookie = '';
$csrf = '';
function authCheck(bool $passed, string $label): void
{
    if (!$passed) throw new RuntimeException($label);
    echo '通过：' . $label . PHP_EOL;
}
function authCall(string $path, string $method = 'GET', array $data = [], ?string $bearer = null, bool $withCsrf = true): array
{
    global $cookie, $csrf;
    $headers = "Content-Type: application/json\r\nConnection: close\r\n";
    if ($bearer !== null) $headers .= "Authorization: Bearer $bearer\r\n";
    else $headers .= "Cookie: $cookie\r\n";
    if ($withCsrf) $headers .= "X-CSRF-TOKEN: $csrf\r\n";
    $body = file_get_contents('http://127.0.0.1:8787' . $path, false, stream_context_create(['http' => [
        'method' => $method, 'header' => $headers, 'content' => $method === 'GET' ? '' : json_encode($data), 'ignore_errors' => true, 'timeout' => 10,
    ]]));
    $wire = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $wire[0], $status);
    foreach ($wire as $header) {
        if (stripos($header, 'X-CSRF-TOKEN: ') === 0) $csrf = substr($header, 14);
        if (stripos($header, 'Set-Cookie: WEBMAN_ADMIN_SESSION=') === 0) {
            // 模拟浏览器语义：Max-Age=0 删除，而不是手工继续发送。
            $cookie = str_contains($header, 'Max-Age=0') ? '' : explode(';', substr($header, 12))[0];
        }
    }
    return [(int) $status[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
try {
    authCheck(authCall('/admin/profile')[0] === 401, '匿名请求不能读取资料');
    authCheck(authCall('/admin/auth/csrf')[0] === 200 && $cookie !== '', '浏览器持久保留匿名会话 Cookie');
    $anonymousCookie = $cookie;
    authCheck(authCall('/admin/auth/login', 'POST', compact('username', 'password'), null, false)[0] === 403, '缺少 CSRF 拒绝登录');
    authCheck(authCall('/admin/auth/login', 'POST', compact('username', 'password'))[0] === 200 && $cookie !== $anonymousCookie, '登录并轮换 Cookie');
    $activeCookie = $cookie;
    [$status, $api] = authCall('/api/auth/login', 'POST', compact('username', 'password'), '');
    authCheck($status === 200, 'API Token 登录成功');
    $token = $api['data']['access_token'];
    authCheck(authCall('/api/auth/me', 'GET', [], substr($cookie, strpos($cookie, '=') + 1))[0] === 401, '浏览器凭据不能用作 API Token');
    for ($i = 0; $i < 8; $i++) authCheck(authCall('/admin/auth/me')[0] === 200, '跨 worker 恢复身份 ' . ($i + 1));
    authCheck(authCall('/admin/profile', 'PUT', ['nickname' => '错误请求'], null, false)[0] === 403, '缺少 CSRF 拒绝修改资料');
    [$status, $result] = authCall('/admin/profile', 'PUT', ['nickname' => '认证验收', 'email' => 'auth@example.com']);
    authCheck($status === 200 && $result['data']['nickname'] === '认证验收', '保存并返回资料');
    authCheck(authCall('/admin/profile/password', 'POST', ['oldPassword' => 'wrong', 'newPassword' => $nextPassword])[0] === 422, '原密码错误可读且不撤销会话');
    authCheck(authCall('/admin/profile')[0] === 200, '错误改密后仍可读取资料');
    authCheck(authCall('/admin/profile/password', 'POST', ['oldPassword' => $password, 'newPassword' => $nextPassword])[0] === 200 && $cookie === '', '改密成功并删除 Cookie');
    $cookie = $activeCookie;
    authCheck(authCall('/admin/auth/me')[0] === 401, '旧浏览器会话失效');
    authCheck(authCall('/api/auth/me', 'GET', [], $token)[0] === 401, '旧 API Token 同时撤销');
    authCall('/admin/auth/csrf');
    authCheck(authCall('/admin/auth/login', 'POST', ['username' => $username, 'password' => $nextPassword])[0] === 200, '新密码可重新登录');
    $activeCookie = $cookie;
    authCheck(authCall('/admin/auth/logout', 'POST')[0] === 200 && $cookie === '', '退出正确删除 Cookie');
    $cookie = $activeCookie;
    authCheck(authCall('/admin/profile')[0] === 401, '退出后旧会话不能复用');
} finally {
    (new app\admin\authentication\service\AdminSessionService())->revokeAll($admin['id']);
    support\Db::table('admin')->where('id', $admin['id'])->delete();
}
