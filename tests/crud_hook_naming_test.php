<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\traits\Crud;
use app\console\controller\system\SystemMemberGroup;
use app\console\controller\system\SystemMemberLevel;

function hookExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$expectedHooks = [
    'payload', 'validatePayload', 'transformData', 'resourceName', 'searchFields',
    'exactFilters', 'rangeFilters', 'sortFields', 'primaryKey', 'primaryKeyType',
    'primaryKeyPattern', 'query', 'baseQuery', 'order', 'importFields',
    'exportFields', 'importPayload', 'importLimit', 'exportLimit', 'beforeDelete',
    'afterSave', 'applyFilters', 'mapImportRow',
];

$trait = new ReflectionClass(Crud::class);
$protectedHooks = array_map(
    static fn (ReflectionMethod $method): string => $method->getName(),
    $trait->getMethods(ReflectionMethod::IS_PROTECTED)
);
sort($expectedHooks);
sort($protectedHooks);
hookExpect($protectedHooks === $expectedHooks, 'Crud Trait 受保护扩展点名称不符合约定');
hookExpect(
    array_filter($protectedHooks, static fn (string $name): bool => str_starts_with($name, 'crud')) === [],
    '受保护扩展点不得保留 crud 前缀'
);

foreach ([SystemMemberGroup::class, SystemMemberLevel::class] as $controller) {
    $reflection = new ReflectionClass($controller);
    foreach (['payload', 'validatePayload', 'transformData', 'resourceName'] as $hook) {
        $method = $reflection->getMethod($hook);
        hookExpect($method->isProtected(), $controller . '::' . $hook . ' 必须保持 protected');
    }
}

$generator = (string) file_get_contents(dirname(__DIR__) . '/app/common/crud/ProductionTemplateContext.php');
hookExpect(!preg_match('/protected function crud[A-Z]/', $generator), '生成器不得输出带 crud 前缀的 protected hook');
hookExpect(str_contains($generator, 'baseQuery as private crudUnscopedBaseQuery'), '生成器必须保留私有基础查询 alias');

echo "crud hook naming tests: PASS\n";
