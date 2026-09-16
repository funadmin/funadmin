<?php

declare(strict_types=1);
require __DIR__ . '/ai_configuration_profile_test.php';

use app\console\controller\ai\Profiles;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\annotation\route\Pattern;

profileExpect(class_exists(Profiles::class), '缺少独立档案 API');
$app = new think\App(dirname(__DIR__));
$app->initialize();
$reflection = new ReflectionClass(Profiles::class);
$group = $reflection->getAttributes(Group::class)[0]->newInstance();
profileExpect($group->name === 'development/ai/profiles', '独立 API 路径');
$methods = ['index','read','create','update','delete','defaultRead','defaultSet','copy'];
foreach ($methods as $name) {
    $method = $reflection->getMethod($name);
    $route = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();
    $app->request->setMethod($route->method);
    $router = new think\Route($app);
    (new ReflectionProperty($router, 'request'))->setValue($router, $app->request);
    $router->group($group->name, function () use ($router, $reflection, $methods) {
        foreach ($methods as $action) {
            $method = $reflection->getMethod($action);
            $value = $method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF)[0]->newInstance();
            $rule = $router->rule($value->rule, 'ai.Profiles/' . $action, $value->method);
            foreach ($method->getAttributes(Pattern::class) as $attribute) { $p = $attribute->newInstance(); $rule->pattern([$p->name=>$p->value]); }
        }
    })->option($group->options);
    $match = $router->check(rtrim($group->name . '/' . str_replace(':id', '2', $route->rule), '/'));
    profileExpect($match && $match->getDispatch() === ['ai.Profiles', $name], '路由匹配 ' . $name);
}
$controller = $reflection->newInstanceWithoutConstructor();
$guard = $reflection->getMethod('authorize');
foreach ([[0,true,401],[7,false,403]] as [$id,$can,$expected]) {
    try { $guard->invoke($controller, $id, $can); throw new LogicException('权限应拒绝'); }
    catch (RuntimeException $e) { profileExpect($e->getCode() === $expected, '登录/configure 权限'); }
}
profileExpect($guard->invoke($controller, 7, true) === 7, '授权管理员');
$request = new think\Request();
(new ReflectionProperty(app\BaseController::class, 'request'))->setValue($controller, $request);
$request->withInput('{"name":"test","fallback_enabled":false,"max_iterations":3,"favorite_models":["a","b"]}');
$parsed = $reflection->getMethod('input')->invoke($controller);
profileExpect($parsed['fallback_enabled'] === false && $parsed['max_iterations'] === 3 && $parsed['favorite_models'] === ['a','b'], 'JSON 严格类型保真');
foreach (['[]','null','bad-json'] as $body) {
    $request->withInput($body);
    profileReject(fn () => $reflection->getMethod('input')->invoke($controller));
}
$sanitize = new ReflectionMethod(app\common\service\AdminLogService::class, 'sanitize');
$redacted = $sanitize->invoke(app\common\service\AdminLogService::instance(), ['api_key'=>'private-test-key']);
profileExpect($redacted['api_key'] === '[REDACTED]', '复用项目审计脱敏');
$source = file_get_contents($reflection->getFileName());
profileExpect(str_contains($source, "development/ai/configure") && str_contains($source, 'CheckAdminApiCsrf::class') && str_contains($source, 'CheckAdminApiRole::class'), '生产权限和 CSRF');
foreach ($methods as $name) profileExpect(str_contains($reflection->getMethod($name)->getDocComment() ?: '', '@profile'), '每个 API 标识契约');
$sql = file_get_contents(dirname(__DIR__) . '/database/migrations/archive/118_ai_profile_permissions.sql');
foreach ($methods as $name) profileExpect(str_contains($sql, "'" . strtolower($name) . "'"), '迁移登记 action');
echo "AI configuration profile API: PASS\n";
