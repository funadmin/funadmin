<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';

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
apiExpect(!str_contains((string) $tokenControllerSource, "->build(\$userData, config('api.access_token_ttl'"), '刷新 Access Token 不得把 TTL 作为 Token 类型传入');

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
apiExpect(str_contains((string) $routeSource, "Route::get('member', 'v2.member/show')"), '会员接口必须使用精简 REST 路由');
apiExpect(str_contains((string) $routeSource, '->middleware(MApi::class)'), '受保护接口必须在路由层注册认证中间件');
apiExpect(!str_contains((string) $routeSource, 'member/index'), '不得保留重复会员列表路由');
apiExpect(!str_contains((string) $routeSource, 'member/verify'), '不得保留无业务健康探针');
apiExpect(!str_contains((string) $routeSource, "'api/v2."), '应用内路由不得重复包含 api 应用名');
apiExpect(!str_contains((string) $routeSource, ':version'), 'API 版本不得直接参与控制器路径解析');
apiExpect(str_contains((string) $routeSource, 'Throttle::class'), '登录接口必须启用独立限流');

$apiControllerSource = file_get_contents(dirname(__DIR__) . '/app/common/controller/Api.php');
apiExpect(str_contains((string) $apiControllerSource, 'use JsonResponse;'), 'API 基类必须复用公共 JSON 响应 Trait');
apiExpect(!str_contains((string) $apiControllerSource, 'extends BaseController'), 'API 基类不得继承页面控制器初始化逻辑');
apiExpect(!str_contains((string) $apiControllerSource, 'ApiAuthentication'), 'API 基类不得动态注册认证中间件');
foreach (['modelClass', 'pageSize', 'searchFields', 'selectMap', 'relationSearch', 'joinSearch', 'dataLimit', 'exportFields', 'loadlang'] as $legacyMember) {
    apiExpect(!str_contains((string) $apiControllerSource, $legacyMember), "API 基类不得保留旧 CRUD 成员：{$legacyMember}");
}
apiExpect(!str_contains((string) $apiControllerSource, 'protected function ok('), 'API 基类不得重复实现 ok 响应');
apiExpect(!str_contains((string) $apiControllerSource, 'protected function fail('), 'API 基类不得重复实现 fail 响应');
apiExpect(!str_contains((string) $tokenControllerSource, '$this->success('), 'Token 控制器不得继续使用 success 响应');
apiExpect(!str_contains((string) $tokenControllerSource, '$this->error('), 'Token 控制器不得继续使用 error 响应');

$memberControllerSource = file_get_contents(dirname(__DIR__) . '/app/api/controller/v2/Member.php');
apiExpect(!str_contains((string) $memberControllerSource, 'noNeedLogin'), 'Member 控制器不得保留方法白名单');
apiExpect(!str_contains((string) $memberControllerSource, 'function index('), 'Member 控制器不得保留重复 index 方法');
apiExpect(!str_contains((string) $memberControllerSource, 'function verify('), 'Member 控制器不得保留无业务 verify 方法');
apiExpect(str_contains((string) $memberControllerSource, 'function show('), 'Member 控制器必须提供 REST 资源读取方法');

$middlewareSource = file_get_contents(dirname(__DIR__) . '/app/common/middleware/MApi.php');
apiExpect(!str_contains((string) $middlewareSource, 'use Apis;'), 'API 中间件不得通过异常式 Trait 返回错误');
apiExpect(str_contains((string) $middlewareSource, 'return $this->fail('), 'API 中间件必须返回 fail 响应');
apiExpect(!str_contains((string) $middlewareSource, '->mid'), 'API 中间件不得写入旧 mid 别名');

$exceptionSource = file_get_contents(dirname(__DIR__) . '/app/ExceptionHandle.php');
apiExpect(str_contains((string) $exceptionSource, 'instanceof HttpResponseException'), 'API 主动响应不得被异常处理器改写');
apiExpect(str_contains((string) $exceptionSource, "getName() !== 'api'"), '统一异常处理必须仅对 API 应用返回 JSON');

$apiRouteConfigSource = file_get_contents(dirname(__DIR__) . '/app/api/config/route.php');
apiExpect(str_contains((string) $apiRouteConfigSource, "'url_route_must'        => true"), 'API 必须强制使用显式路由');

$apiMiddlewareSource = file_get_contents(dirname(__DIR__) . '/app/api/middleware.php');
apiExpect(str_contains((string) $apiMiddlewareSource, 'return [];'), 'API 应用不得重复注册全局中间件');
apiExpect(!str_contains((string) $tokenControllerSource, "post('access_token'"), '刷新接口不得兼容旧 access_token 参数');
apiExpect(!str_contains((string) $tokenControllerSource, '::instance()'), 'Token 控制器必须使用构造器依赖注入');
apiExpect(!str_contains((string) $tokenControllerSource, 'App $app'), 'Token 控制器不得注入无用 App 实例');

$generatorSource = file_get_contents(dirname(__DIR__) . '/app/common/service/McpService.php');
$apiGeneratorSource = substr((string) $generatorSource, strpos((string) $generatorSource, 'private function generateApiContent'));
apiExpect(!str_contains($apiGeneratorSource, 'protected \\$noNeedLogin'), 'API 生成模板不得生成旧方法白名单');
apiExpect(!str_contains($apiGeneratorSource, 'protected \\$noNeedRight'), 'API 生成模板不得生成旧权限白名单');
apiExpect(!str_contains($apiGeneratorSource, 'protected \\$modelClass = null'), 'API 生成模板不得依赖已移除的基类模型属性');
apiExpect(str_contains($apiGeneratorSource, 'private readonly'), 'API 生成模板必须使用构造器属性提升');
apiExpect(str_contains($apiGeneratorSource, 'use think\\\\Response;'), 'API 生成模板必须声明响应类型');
apiExpect(str_contains((string) $generatorSource, 'app/{$module}/controller/v2/{$controllerClass}.php'), 'API 必须生成到 v2 控制器目录');
apiExpect(str_contains((string) $generatorSource, "if (strtolower(\$module) === 'api')"), '通用控制器生成器必须拒绝生成 API 控制器');
apiExpect(str_contains((string) $generatorSource, 'API_ROUTE_END'), 'API 生成器必须维护显式路由标记');
apiExpect(str_contains((string) $generatorSource, 'MApi::class'), 'API 生成路由必须绑定认证中间件');

echo "api auth tests: PASS\n";
