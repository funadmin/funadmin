<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\TokenService;
use think\App;

function apiExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$app = new App();
$app->config->set([
    'jwt_secret' => str_repeat('a', 64),
    'refresh_jwt_secret' => str_repeat('b', 64),
    'access_token_ttl' => 7200,
    'refresh_token_ttl' => 2592000,
    'issuer' => 'funadmin.com',
    'audience' => 'funadmin',
], 'api');

$service = new TokenService();
$accessToken = $service->build(['id' => 7]);
$accessPayload = $service->validateToken($accessToken);
apiExpect($accessPayload === ['id' => 7], 'Access Token 只应返回会员标识');
apiExpect($service->validateToken($accessToken, TokenService::TYPE_REFRESH) === false, 'Access Token 不得作为 Refresh Token 使用');

$refreshToken = $service->build(['id' => 7], TokenService::TYPE_REFRESH);
$refreshPayload = $service->validateToken($refreshToken, TokenService::TYPE_REFRESH);
apiExpect($refreshPayload === ['id' => 7], 'Refresh Token 应使用独立类型和配置');
apiExpect($service->validateToken($refreshToken) === false, 'Refresh Token 不得作为 Access Token 使用');
apiExpect($service->validateToken($refreshToken . 'invalid', TokenService::TYPE_REFRESH) === false, '篡改后的 Token 必须验证失败');

$tokenControllerSource = file_get_contents(dirname(__DIR__) . '/app/api/controller/v2/Token.php');
apiExpect(!str_contains((string) $tokenControllerSource, "header('Access-Control"), '控制器不得直接设置 CORS Header');
apiExpect(str_contains((string) $tokenControllerSource, 'MemberAuthService'), '登录数据库查询必须下沉到认证服务');
apiExpect(!str_contains((string) $tokenControllerSource, '::where('), 'Token 控制器不得直接查询数据库');
apiExpect(!str_contains((string) $tokenControllerSource, "config('api.access_token_ttl'"), '刷新 Access Token 不得把 TTL 作为 Token 类型传入');

$middlewareSource = file_get_contents(dirname(__DIR__) . '/app/common/middleware/MApi.php');
apiExpect(!str_contains((string) $middlewareSource, 'ReflectionClass'), 'API 认证中间件不得反射控制器');
apiExpect(!str_contains((string) $middlewareSource, "input('access_token')"), '认证令牌只允许通过 Authorization Header 传递');

$authServiceSource = file_get_contents(dirname(__DIR__) . '/app/common/service/MemberAuthService.php');
apiExpect(str_contains((string) $authServiceSource, 'where(function'), '多账号字段查询必须通过闭包分组');
apiExpect(str_contains((string) $authServiceSource, "where('status', 1)"), '登录必须校验会员状态');

$configSource = file_get_contents(dirname(__DIR__) . '/config/api.php');
apiExpect(str_contains((string) $configSource, "Env::get('API_JWT_SECRET'"), 'JWT 密钥必须来自环境变量');
apiExpect(str_contains((string) $configSource, "'refresh_token_ttl'"), 'Refresh Token TTL 配置名必须统一');

$routeSource = file_get_contents(dirname(__DIR__) . '/app/api/route/api.php');
apiExpect(str_contains((string) $routeSource, "Route::group('v2'"), 'API v2 路由必须显式注册');
apiExpect(!str_contains((string) $routeSource, ':version'), 'API 版本不得直接参与控制器路径解析');
apiExpect(str_contains((string) $routeSource, 'Throttle::class'), '登录接口必须启用独立限流');

$responseTraitSource = file_get_contents(dirname(__DIR__) . '/app/common/traits/Apis.php');
apiExpect(str_contains((string) $responseTraitSource, 'Response::create($result, $type, $code)'), 'API 响应必须设置真实 HTTP 状态码');

$exceptionSource = file_get_contents(dirname(__DIR__) . '/app/ExceptionHandle.php');
apiExpect(str_contains((string) $exceptionSource, 'instanceof HttpResponseException'), 'API 主动响应不得被异常处理器改写');
apiExpect(str_contains((string) $exceptionSource, "getName() !== 'api'"), '统一异常处理必须仅对 API 应用返回 JSON');

$funadminConfigSource = file_get_contents(dirname(__DIR__) . '/config/funadmin.php');
apiExpect(str_contains((string) $funadminConfigSource, "'api_login_url'=>'/api/v2/token'"), '登录 URL 配置必须与显式路由一致');

echo "api auth tests: PASS\n";
