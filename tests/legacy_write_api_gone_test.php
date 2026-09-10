<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\controller\legacy\LegacyWriteApi;
use think\annotation\route\Group;
use think\annotation\route\Post;

function legacyGoneExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__);
foreach ([
    '/app/console/controller/development/DevCrud.php',
    '/app/console/controller/form/Designer.php',
    '/app/console/controller/form/FullPublish.php',
    '/app/console/service/FormFullPublishService.php',
] as $retired) {
    legacyGoneExpect(!is_file($root . $retired), '旧实现不得恢复：' . $retired);
}

legacyGoneExpect(class_exists(LegacyWriteApi::class), '必须提供不含旧业务逻辑的 410 tombstone 控制器');
$controller = new ReflectionClass(LegacyWriteApi::class);
$groups = $controller->getAttributes(Group::class);
legacyGoneExpect(count($groups) === 1 && $groups[0]->newInstance()->name === '', 'tombstone 必须位于 console 根路径');

$expected = [
    'formDesignerWrite' => 'form/designer/:action',
    'formFullPublishWrite' => 'form/full-publish/:action',
    'developmentCrudWrite' => 'development/crud/:action',
];
foreach ($expected as $method => $route) {
    legacyGoneExpect($controller->hasMethod($method), '缺少 tombstone：' . $method);
    $attributes = $controller->getMethod($method)->getAttributes(Post::class);
    legacyGoneExpect(count($attributes) === 1 && $attributes[0]->newInstance()->rule === $route, 'tombstone 路由不匹配：' . $method);
}

$source = (string) file_get_contents($controller->getFileName());
legacyGoneExpect(str_contains($source, "code: 410") && str_contains($source, "'/development/business'"), '旧写 API 必须统一返回 410 与新入口');
foreach (['DevCrudService', 'FormDesignerService', 'FormFullPublishService', 'allowOverwrite', 'definition(', 'generate('] as $forbidden) {
    legacyGoneExpect(!str_contains($source, $forbidden), 'tombstone 禁止恢复旧功能：' . $forbidden);
}

echo "legacy write API gone tests: PASS\n";
