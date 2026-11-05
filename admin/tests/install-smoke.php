<?php

declare(strict_types=1);

if (getenv('INSTALL_HTTP_TEST') !== '1') throw new RuntimeException('仅供隔离测试容器执行');
$base = getenv('INSTALL_HTTP_BASE') ?: 'http://127.0.0.1:8787';
$cookie = [];
$csrf = '';
function installationCall(string $path, string $method = 'GET', array $data = [], bool $token = true): array
{
    global $base, $cookie, $csrf;
    $headers = "Content-Type: application/json\r\nConnection: close\r\n";
    if ($cookie) $headers .= 'Cookie: ' . implode('; ', $cookie) . "\r\n";
    if ($token && $csrf !== '') $headers .= "X-CSRF-TOKEN: $csrf\r\n";
    $body = file_get_contents($base . $path, false, stream_context_create(['http' => [
        'method' => $method, 'header' => $headers, 'content' => $method === 'GET' ? '' : json_encode($data),
        'ignore_errors' => true, 'timeout' => 30,
    ]]));
    $responseHeaders = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $responseHeaders[0], $match);
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Set-Cookie: ') === 0) {
            $value = explode(';', substr($header, 12))[0];
            $cookie[explode('=', $value, 2)[0]] = $value;
        }
    }
    return [(int) $match[1], json_decode($body, true, 512, JSON_THROW_ON_ERROR)];
}
function installationCheck(bool $passed, string $label): void
{
    if (!$passed) throw new RuntimeException($label);
    echo '通过：' . $label . PHP_EOL;
}

[$status, $environment] = installationCall('/install/index/step2');
installationCheck($status === 200 && !$environment['data']['installed'], '空库环境检测不依赖数据库');
foreach ($environment['data']['checks'] as $check) installationCheck(!$check['required'] || $check['passed'], '环境要求：' . $check['key']);
$csrf = $environment['data']['csrfToken'];
installationCheck(installationCall('/install/index/step3', 'POST', [], false)[0] === 403, '缺少 CSRF 拒绝安装');
installationCheck(installationCall('/install/index/step3', 'GET')[0] !== 200, 'GET 不执行安装');
$password = 'Install-' . bin2hex(random_bytes(16));
$database = 'funadmin_install_' . bin2hex(random_bytes(4));
$input = [
    'hostname' => getenv('INSTALL_DB_HOST') ?: 'funadmin-install-db', 'port' => '3306', 'database' => $database, 'prefix' => 'custom_',
    'username' => 'root', 'password' => getenv('INSTALL_DB_PASSWORD') ?: '',
    'adminUserName' => 'installer', 'adminPassword' => $password, 'rePassword' => $password,
    'email' => 'installer@example.com', 'app_debug' => 0,
];
[$status, $result] = installationCall('/install/index/step3', 'POST', $input);
installationCheck($status === 200, '全新数据库安装成功：' . ($result['msg'] ?? '未知响应'));
installationCheck(!isset($result['data']['password']), '安装响应不包含明文密码');
installationCheck(installationCall('/install/index/step3', 'POST', $input)[0] === 409, '安装锁拒绝重复安装');
[$status, $environment] = installationCall('/install/index/step2');
installationCheck($environment['data'] === ['installed' => true], '安装后不泄露环境信息');
installationCheck(installationCall('/install/index/step4', 'POST')[0] === 200, '完成安装并清理安装 Cookie');
[$status, $bootstrap] = installationCall('/admin/auth/csrf');
installationCheck($status === 200, '安装后无需重启即可初始化登录会话');
$csrf = $bootstrap['data']['csrfToken'];
[$status, $result] = installationCall('/admin/auth/login', 'POST', ['username' => 'installer', 'password' => $password]);
installationCheck($status === 200, '安装后管理员 Cookie 登录成功');
for ($i = 0; $i < 12; $i++) {
    [$status, $me] = installationCall('/admin/auth/me');
    installationCheck($status === 200 && $me['data']['permissions'] === ['*'], '跨 worker 超级管理员身份 ' . ($i + 1));
}
[$status, $menus] = installationCall('/admin/auth/menus');
installationCheck($status === 200 && count($menus['data']) > 0, '安装后菜单可用');
installationCheck(installationCall('/admin/system/user')[0] === 200, '安装后管理员列表可用');
installationCheck(installationCall('/admin/management/users')[0] === 404, '旧管理原型接口不再注册');
echo '隔离测试库：' . $database . PHP_EOL;
