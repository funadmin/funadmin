<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use think\App;
use think\event\RouteLoaded;

function legacyGoneExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
foreach ([
    '/app/admin/controller/development/DevCrud.php',
    '/app/admin/controller/form/Designer.php',
    '/app/admin/controller/form/FullPublish.php',
    '/app/admin/service/FormFullPublishService.php',
] as $retired) {
    legacyGoneExpect(!is_file($root . $retired), '旧实现不得恢复：' . $retired);
}

// 加载真实注解路由，不执行控制器或访问业务数据库。
$app = new App($root . '/');
$app->http->name('console');
$app->setAppPath($root . '/app/admin/');
$app->setNamespace('app\\console');
$app->initialize();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
});
$app->event->trigger(RouteLoaded::class);

$routes = [];
$retiredRoutes = [];
foreach ($app->route->getRuleList() as $route) {
    $rule = trim((string) ($route['rule'] ?? ''), '/');
    $rule = preg_replace('/<([a-zA-Z_][a-zA-Z0-9_]*)>/', ':$1', $rule);
    $method = strtolower((string) ($route['method'] ?? ''));
    $target = $route['route'] ?? '';
    $routes[$method . ' ' . $rule] = $target;
    foreach (['form/designer', 'form/full-publish', 'development/crud'] as $prefix) {
        if ($rule === $prefix || str_starts_with($rule, $prefix . '/')) {
            $retiredRoutes[] = $method . ' ' . $rule;
        }
    }
    legacyGoneExpect(!is_string($target) || !str_contains($target, 'LegacyWriteApi'), '旧控制器不得注册路由：' . $rule);
}
legacyGoneExpect($retiredRoutes === [], '旧写入口不得注册：' . implode(', ', $retiredRoutes));
legacyGoneExpect(!is_file($root . '/app/admin/controller/legacy/LegacyWriteApi.php'), '旧控制器文件必须删除');
legacyGoneExpect(!class_exists('app\\admin\\controller\\legacy\\LegacyWriteApi'), '旧控制器不得再自动加载');

foreach ([
    'get development/business/modules' => 'development.Business/modules',
    'post development/business/modules/visual' => 'development.Business/createVisual',
    'post development/business/modules/:id/schema/save' => 'development.Business/saveSchema',
    'post development/business/modules/:id/publish' => 'development.Business/publish',
    'post development/business/modules/:id/formal-generation' => 'development.Business/formalGeneration',
    'post development/business/generations/:id/recover' => 'development.Business/recoverGeneration',
] as $route => $target) {
    legacyGoneExpect(($routes[$route] ?? null) === $target, '新业务路由必须存在且目标正确：' . $route);
}

echo "legacy write API gone tests: PASS\n";
