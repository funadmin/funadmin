<?php

declare(strict_types=1);

// 在测试 API 容器中运行，不输出密码或令牌。
require dirname(__DIR__) . '/vendor/autoload.php';
Webman\Config::load(config_path(), ['route']);
date_default_timezone_set(config('app.default_timezone'));
$username = 'smoke_' . bin2hex(random_bytes(5));
$password = bin2hex(random_bytes(24));
$admin = (new app\admin\authentication\service\AdminSessionService())->createAdmin($username, $password);

function callApi(string $path, string $method = 'GET', array $data = [], string $token = ''): array
{
    $headers = "Content-Type: application/json\r\n";
    if ($token !== '') {
        $headers .= "Authorization: Bearer $token\r\n";
    }
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => $headers,
        'content' => $method === 'POST' ? json_encode($data) : '',
        'ignore_errors' => true,
        'timeout' => 10,
    ]]);
    $body = file_get_contents('http://127.0.0.1:8787' . $path, false, $context);
    $responseHeaders = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches);
    return [(int) $matches[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}

function check(bool $condition, string $description): void
{
    if (!$condition) {
        throw new RuntimeException($description);
    }
    echo "通过：$description\n";
}

try {
    [$status, $health] = callApi('/health');
    check($status === 200 && $health['status'] === 'ok', '框架健康检查');
    check(callApi('/api/auth/me')[0] === 401, '匿名身份请求被拒绝');
    [$status, $login] = callApi('/api/auth/login', 'POST', compact('username', 'password'));
    check($status === 200 && !isset($login['data']['admin']['password']), '登录成功且不泄露密码');
    $token = $login['data']['access_token'];
    for ($i = 0; $i < 8; $i++) {
        [$status, $me] = callApi('/api/auth/me', 'GET', [], $token);
        check($status === 200 && $me['data']['id'] === $admin['id'], '跨进程身份校验 ' . ($i + 1));
        check(callApi('/api/auth/me')[0] === 401, '匿名请求不继承上次身份 ' . ($i + 1));
    }
    check(callApi('/auth/me')[0] === 404, '默认控制器路由已禁用');
    check(callApi('/api/auth/logout', 'POST', [], $token)[0] === 200, '退出登录');
    check(callApi('/api/auth/me', 'GET', [], $token)[0] === 401, '退出后令牌失效');
    $cookie = '';
    $csrf = '';
    $browser = static function (string $path, string $method = 'GET', array $data = [], bool $withCsrf = true) use (&$cookie, &$csrf): array {
        $headers = "Content-Type: application/json\r\nCookie: $cookie\r\n";
        if ($host = getenv('BROWSER_API_HOST')) $headers .= "Host: $host\r\n";
        if ($withCsrf) $headers .= "X-CSRF-TOKEN: $csrf\r\n";
        $body = file_get_contents((getenv('BROWSER_API_BASE') ?: 'http://127.0.0.1:8787') . $path, false, stream_context_create(['http' => [
            'method' => $method, 'header' => $headers, 'content' => $method === 'GET' ? '' : json_encode($data),
            'ignore_errors' => true, 'timeout' => 10,
        ]]));
        $responseHeaders = http_get_last_response_headers();
        preg_match('/\s(\d{3})\s/', $responseHeaders[0], $matches);
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Set-Cookie: WEBMAN_ADMIN_SESSION=') === 0) {
                check(str_contains($header, 'HttpOnly') && str_contains($header, 'SameSite=Lax'), '浏览器 Cookie 安全标记');
                $cookie = explode(';', substr($header, strlen('Set-Cookie: ')))[0];
            }
            if (stripos($header, 'X-CSRF-TOKEN: ') === 0) $csrf = substr($header, strlen('X-CSRF-TOKEN: '));
        }
        return [(int) $matches[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
    };
    check($browser('/admin/auth/csrf')[0] === 200 && $csrf !== '', '浏览器 CSRF 初始化');
    $anonymousCookie = $cookie;
    check($browser('/admin/auth/login', 'POST', compact('username', 'password'), false)[0] === 403, '缺少 CSRF 拒绝登录');
    [$status, $result] = $browser('/admin/auth/login', 'POST', compact('username', 'password'));
    check($status === 200 && $result['data']['authenticated'] && $cookie !== $anonymousCookie, '浏览器登录并轮换会话');
    check(!isset($result['data']['access_token']), '浏览器不返回访问令牌');
    for ($i = 0; $i < 3; $i++) {
        [$status, $me] = $browser('/admin/auth/me');
        check($status === 200 && $me['data']['id'] === $admin['id'], '刷新后恢复浏览器身份');
    }
    check($browser('/admin/auth/menus')[0] === 200, '加载现有数据库菜单树');
    check($browser('/admin/auth/logout', 'POST', [], false)[0] === 403, '缺少 CSRF 拒绝退出');
    $authenticatedCookie = $cookie;
    check($browser('/admin/auth/logout', 'POST')[0] === 200, '浏览器退出成功');
    $cookie = $authenticatedCookie;
    check($browser('/admin/auth/me')[0] === 401, '退出后旧会话失效');
} finally {
    if (isset($authenticatedCookie)) {
        $value = substr($authenticatedCookie, strpos($authenticatedCookie, '=') + 1);
        support\Db::table('browser_sessions')->where('session_hash', hash('sha256', $value))->delete();
    }
    app\admin\authentication\model\Admin::where('id', $admin['id'])->delete();
}
