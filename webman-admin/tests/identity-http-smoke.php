<?php

declare(strict_types=1);

// 独立进程与内存数据库；不加载 .env、不连接业务库、不启动项目其他 worker。
require __DIR__ . '/bootstrap.php';

use app\admin\authentication\service\AdminSessionService;
use Illuminate\Database\Schema\Blueprint;
use Psr\Log\NullLogger;
use support\Db;
use support\Request;
use Webman\App;
use Webman\Route;
use Workerman\Worker;

if (Db::connection()->getDriverName() !== 'sqlite' || Db::connection()->getDatabaseName() !== ':memory:') {
    throw new RuntimeException('HTTP 冒烟仅允许使用独立内存数据库');
}

if (($argv[1] ?? '') === '--server') {
    $port = filter_var($argv[2] ?? null, FILTER_VALIDATE_INT);
    $password = getenv('IDENTITY_SMOKE_PASSWORD');
    if (!$port || $port < 1024 || !is_string($password) || strlen($password) < 24) throw new RuntimeException('冒烟服务参数无效');
    (new \app\common\service\database\MigrationService())->run();
    (new AdminSessionService())->createAdmin('smoke_admin', $password);
    Db::schema()->create('scope', static function (Blueprint $table): void {
        $table->id(); $table->integer('tenant_id'); $table->string('name');
        $table->string('description')->default(''); $table->text('claims');
        $table->boolean('is_builtin')->default(false); $table->boolean('status')->default(true);
        $table->timestamps(); $table->unique(['tenant_id', 'name']);
    });
    Db::table('scope')->insert(['tenant_id' => 2, 'name' => 'other_tenant', 'claims' => '[]']);
    Route::load([config_path()]);
    $app = new App(Request::class, new NullLogger(), app_path(), public_path());
    $worker = new Worker('http://127.0.0.1:' . $port);
    $worker->count = 1;
    $worker->onWorkerStart = [$app, 'onWorkerStart'];
    $worker->onMessage = [$app, 'onMessage'];
    $argv = [$argv[0], 'start'];
    Worker::runAll();
    exit;
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if (!$socket) throw new RuntimeException('无法分配冒烟端口');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$password = bin2hex(random_bytes(24));
putenv('IDENTITY_SMOKE_PASSWORD=' . $password);
$process = proc_open([PHP_BINARY, __FILE__, '--server', (string) $port], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) throw new RuntimeException('无法启动冒烟服务');
fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
$cookie = '';
$csrf = '';
$checks = 0;

function identitySmokeCall(string $method, string $path, array $data = [], bool $authenticated = true, bool $withCsrf = true): array
{
    global $port, $cookie, $csrf;
    $headers = "Content-Type: application/json\r\nConnection: close\r\n";
    if ($authenticated) $headers .= "Cookie: $cookie\r\n";
    if ($withCsrf && $csrf !== '') $headers .= "X-CSRF-TOKEN: $csrf\r\n";
    $body = file_get_contents('http://127.0.0.1:' . $port . $path, false, stream_context_create(['http' => [
        'method' => $method, 'header' => $headers, 'content' => $method === 'GET' ? '' : json_encode($data, JSON_THROW_ON_ERROR),
        'ignore_errors' => true, 'timeout' => 5,
    ]]));
    $responseHeaders = http_get_last_response_headers();
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $matched);
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'Set-Cookie: WEBMAN_ADMIN_SESSION=') === 0) $cookie = explode(';', substr($header, strlen('Set-Cookie: ')))[0];
        if (stripos($header, 'X-CSRF-TOKEN: ') === 0) $csrf = substr($header, strlen('X-CSRF-TOKEN: '));
    }
    return [(int) ($matched[1] ?? 0), $body === false ? '' : $body];
}

function identitySmokeAssert(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('失败：' . $label);
    $checks++;
    echo '通过：' . $label . PHP_EOL;
}

try {
    $ready = false;
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $probe = @stream_socket_client('tcp://127.0.0.1:' . $port, $errorCode, $errorMessage, 0.1);
        if ($probe) { fclose($probe); $ready = true; break; }
        if (!proc_get_status($process)['running']) break;
        usleep(50000);
    }
    if (!$ready) throw new RuntimeException('冒烟服务启动失败：' . stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]));
    identitySmokeAssert(identitySmokeCall('GET', '/admin/identity/scopes', [], false)[0] === 401, '匿名访问由原生中间件拒绝');
    [$status, $body] = identitySmokeCall('GET', '/admin/auth/csrf');
    identitySmokeAssert($status === 200 && $cookie !== '' && $csrf !== '', '真实 HTTP 会话与 CSRF 引导');
    [$status, $body] = identitySmokeCall('POST', '/admin/auth/login', ['username' => 'smoke_admin', 'password' => $password]);
    identitySmokeAssert($status === 200 && json_decode($body, true)['code'] === 200, '真实 HTTP 登录');
    identitySmokeAssert(identitySmokeCall('POST', '/admin/identity/scopes', ['name' => 'blocked'], true, false)[0] === 403, '写接口拒绝缺少 CSRF');
    [$status, $body] = identitySmokeCall('POST', '/admin/identity/scopes', ['name' => 'http_test', 'claims' => ['email']]);
    $row = json_decode($body, true)['data'] ?? [];
    identitySmokeAssert($status === 200 && isset($row['id']), '原生注解创建 Scope');
    $id = $row['id'];
    [$status, $body] = identitySmokeCall('PUT', '/admin/identity/scopes/' . $id, ['description' => 'HTTP 修改']);
    identitySmokeAssert($status === 200 && json_decode($body, true)['data']['description'] === 'HTTP 修改', 'PUT 请求正文实际持久化');
    [$status, $body] = identitySmokeCall('GET', '/admin/identity/scopes');
    identitySmokeAssert($status === 200 && json_decode($body, true)['data']['total'] === 1, '列表隔离其他租户');
    identitySmokeAssert(identitySmokeCall('DELETE', '/admin/identity/scopes/1')[0] === 404, '跨租户删除返回安全 404');
    identitySmokeAssert(identitySmokeCall('PATCH', '/admin/identity/scopes/' . $id)[0] === 405, '错误请求方法返回 405');
    [$status, $body] = identitySmokeCall('DELETE', '/admin/identity/scopes/' . $id);
    identitySmokeAssert($status === 200 && json_decode($body, true)['code'] === 200, 'DELETE 实际删除 Scope');
    identitySmokeAssert(identitySmokeCall('POST', '/admin/auth/logout')[0] === 200, '退出撤销浏览器会话');
    identitySmokeAssert(identitySmokeCall('GET', '/admin/identity/scopes')[0] === 401, '退出后不能复用旧身份');
    echo 'HTTP 冒烟通过：' . $checks . " 项\n";
} finally {
    proc_terminate($process, SIGTERM);
    for ($attempt = 0; $attempt < 100 && proc_get_status($process)['running']; $attempt++) usleep(50000);
    foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
    proc_close($process);
    putenv('IDENTITY_SMOKE_PASSWORD');
}
