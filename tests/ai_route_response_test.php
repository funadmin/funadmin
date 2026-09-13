<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\controller\ai\Ai;
use think\annotation\route\Group;
use think\annotation\route\Route;
use think\annotation\route\Pattern;

$reflection = new ReflectionClass(Ai::class);
$group = $reflection->getAttributes(Group::class)[0]->newInstance();
$failures = [];
// 使用真实控制器注解和 ThinkPHP 匹配器，覆盖所有 AI 路由，不能只检查注册列表。
foreach ($reflection->getMethods() as $target) {
    foreach ($target->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
        $expected = $attribute->newInstance();
        $app = new think\App(dirname(__DIR__));
        $app->initialize();
        $app->request->setMethod($expected->method);
        $router = new think\Route($app);
        (new ReflectionProperty($router, 'request'))->setValue($router, $app->request);
        $router->group($group->name, function () use ($router, $reflection): void {
            foreach ($reflection->getMethods() as $method) {
                foreach ($method->getAttributes(Route::class, ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                    $route = $attribute->newInstance();
                    $item = $router->rule($route->rule, 'ai.Ai/' . $method->name, $route->method)->option($route->options);
                    foreach ($method->getAttributes(Pattern::class) as $pattern) {
                        $value = $pattern->newInstance();
                        $item->pattern([$value->name => $value->value]);
                    }
                }
            }
        })->option($group->options);
        $path = $group->name . '/' . str_replace([':id', ':stream'], ['2', 'stdout'], $expected->rule);
        $actual = $router->check($path);
        $dispatch = $actual ? $actual->getDispatch() : false;
        if ($dispatch !== ['ai.Ai', $target->name]) $failures[] = "$expected->method $path: " . json_encode($dispatch) . ' != ' . $target->name;
    }
}
// 响应协议本身只包装一次；HTTP 应只解一层 data。
$controller = $reflection->newInstanceWithoutConstructor();
$run = $reflection->getMethod('run');
foreach ([['id' => 2, 'title' => '测试'], [['id' => 2]], []] as $data) {
    $response = $run->invoke($controller, static fn () => $data);
    $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    if ($body['code'] !== 200 || $body['data'] !== $data) $failures[] = '真实 AI 响应包装改变数据形状';
}
if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo "AI route and response: PASS\n";
