<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\backend\middleware\CheckAdminApiCsrf;
use app\backend\middleware\CheckAdminApiRole;
use app\backend\service\AuthService;
use think\App;
use think\Request;
use think\exception\HttpResponseException;
use think\facade\Session;

function gateExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function gateEnvelope(object $response, int $status): void
{
    gateExpect($response->getCode() === $status, "HTTP 状态应为 {$status}");
    $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    gateExpect(($body['code'] ?? null) === $status, "响应 code 应为 {$status}");
}

final class GateAuthService extends AuthService
{
    public function __construct(private readonly bool $loggedIn, private readonly bool $allowed)
    {
    }

    public function isLogin(): bool
    {
        return $this->loggedIn;
    }

    public function roleAccess(): bool
    {
        if (!$this->allowed) {
            throw new HttpResponseException(json(['code' => 403], 403));
        }
        return true;
    }
}

final class GateRequest extends Request
{
    public function __construct(private readonly string $requestMethod, private readonly array $requestHeaders = [], private readonly array $requestParams = [])
    {
    }

    public function method(bool $origin = false): string
    {
        return $this->requestMethod;
    }

    public function header(string $name = '', $default = null): mixed
    {
        return $this->requestHeaders[strtolower($name)] ?? $default;
    }

    public function param($name = '', $default = null, $filter = ''): mixed
    {
        return $this->requestParams[$name] ?? $default;
    }
}

$app = new App();
$app->initialize();
$nextCalls = 0;
$next = static function () use (&$nextCalls): object {
    $nextCalls++;
    return json(['code' => 200], 200);
};

$app->instance(AuthService::class, new GateAuthService(false, false));
gateEnvelope((new CheckAdminApiRole())->handle(new GateRequest('GET'), $next), 401);
gateExpect($nextCalls === 0, '未登录请求不得进入插件控制器');

$app->instance(AuthService::class, new GateAuthService(true, false));
gateEnvelope((new CheckAdminApiRole())->handle(new GateRequest('GET'), $next), 403);
gateExpect($nextCalls === 0, '无权限请求不得进入插件控制器');

$app->instance(AuthService::class, new GateAuthService(true, true));
gateEnvelope((new CheckAdminApiRole())->handle(new GateRequest('GET'), $next), 200);
gateExpect($nextCalls === 1, '授权请求必须进入插件控制器');

Session::set('__token__', 'known-token');
$csrf = new CheckAdminApiCsrf();
gateEnvelope($csrf->handle(new GateRequest('POST'), $next), 419);
gateEnvelope($csrf->handle(new GateRequest('POST', ['x-csrf-token' => 'wrong-token']), $next), 419);
gateEnvelope($csrf->handle(new GateRequest('POST', ['x-csrf-token' => 'known-token']), $next), 200);
gateEnvelope($csrf->handle(new GateRequest('GET'), $next), 200);
gateExpect($nextCalls === 3, 'CSRF 仅允许正确 token 的写请求并放行只读请求');
Session::delete('__token__');

echo "plugin admin gate tests: PASS\n";
