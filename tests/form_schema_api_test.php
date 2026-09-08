<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaException;
use app\common\form\schema\FormSchemaValidator;
use app\console\service\FormSchemaRepository;

function schemaApiExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function schemaApiDefinition(string $title = '报名表'): array
{
    return [
        'schemaVersion' => 2,
        'key' => 'registration',
        'title' => $title,
        'nodes' => [[
            'id' => 'node_name',
            'kind' => 'field',
            'type' => 'input',
            'field' => 'name',
            'title' => '姓名',
            'children' => [],
        ]],
    ];
}

$repository = new FormSchemaRepository();
$compiled = $repository->compile(schemaApiDefinition());
$compilePayload = $repository->compilePayload(schemaApiDefinition());

schemaApiExpect($compilePayload['document'] === $compiled->document(), 'compile 必须返回 canonical document');
schemaApiExpect($compilePayload['hash'] === $compiled->hash(), 'compile 必须返回 canonical hash');
schemaApiExpect($compilePayload['projection'] === $compiled->fieldProjection(), 'compile 必须返回字段投影');

$exported = $repository->export(schemaApiDefinition());
$imported = $repository->import($exported);
schemaApiExpect($imported->document() === $compiled->document(), 'export/import 必须保持 canonical document');
schemaApiExpect($imported->hash() === $compiled->hash(), 'export/import 必须保持 canonical hash');

try {
    $unsupported = schemaApiDefinition();
    $unsupported['schemaVersion'] = 99;
    $repository->compile($unsupported);
    throw new RuntimeException('未知 schemaVersion 必须被拒绝');
} catch (FormSchemaException $exception) {
    schemaApiExpect($exception->errorCode() === 'FORM_SCHEMA_VERSION_UNSUPPORTED', '未知版本错误必须提供 code');
    schemaApiExpect($exception->schemaPath() === '/schemaVersion', '未知版本错误必须提供 path');
}

try {
    $repository->import('{invalid json');
    throw new RuntimeException('非法 JSON 导入必须被拒绝');
} catch (FormSchemaException $exception) {
    schemaApiExpect($exception->errorCode() === 'FORM_SCHEMA_IMPORT_INVALID', '导入错误必须提供 code');
    schemaApiExpect($exception->schemaPath() === '/', '导入错误必须提供 path');
}

foreach ([2.1, 'v2'] as $unknownVersion) {
    foreach ([$repository, new FormSchemaCompiler(new FormSchemaValidator())] as $compiler) {
        try {
            $unsupported = schemaApiDefinition();
            $unsupported['schemaVersion'] = $unknownVersion;
            $compiler->compile($unsupported);
            throw new RuntimeException('非精确 schemaVersion 2 必须被拒绝');
        } catch (FormSchemaException $exception) {
            schemaApiExpect($exception->errorCode() === 'FORM_SCHEMA_VERSION_UNSUPPORTED', '未知版本必须提供统一错误 code');
            schemaApiExpect($exception->schemaPath() === '/schemaVersion', '未知版本必须定位 schemaVersion');
        }
    }
}

$updated = schemaApiDefinition('新版报名表');
$updated['nodes'][0]['title'] = '真实姓名';
$updated['nodes'][] = [
    'id' => 'node_mobile',
    'kind' => 'field',
    'type' => 'input',
    'field' => 'mobile',
    'title' => '手机号',
    'children' => [],
];
$diff = $repository->diffDocuments(schemaApiDefinition(), $updated);
schemaApiExpect($diff['fromHash'] === $repository->compile(schemaApiDefinition())->hash(), 'diff 必须返回起始版本 hash');
schemaApiExpect($diff['toHash'] === $repository->compile($updated)->hash(), 'diff 必须返回目标版本 hash');
schemaApiExpect(in_array('/title', array_column($diff['changes'], 'path'), true), 'diff 必须报告顶层属性变化');
schemaApiExpect(in_array('/nodes/0/title', array_column($diff['changes'], 'path'), true), 'diff 必须报告嵌套属性变化');
schemaApiExpect(in_array('/nodes/1', array_column($diff['changes'], 'path'), true), 'diff 必须报告新增节点');

$catalog = $repository->componentCatalog();
schemaApiExpect(($catalog['schemaVersion'] ?? null) === 2, '组件目录必须声明 schemaVersion');
schemaApiExpect(in_array('input', array_column($catalog['components'], 'type'), true), '组件目录必须包含内置组件');
schemaApiExpect(count($catalog['components']) === count(array_unique(array_column($catalog['components'], 'type'))), '组件目录不得包含重复类型');
$coreInput = array_values(array_filter($catalog['components'], static fn (array $item): bool => ($item['type'] ?? '') === 'input'))[0] ?? [];
foreach (['defaultValue', 'defaultProps', 'propertySchema', 'codec', 'allowedAttrs', 'allowedEvents', 'renderer'] as $capability) {
    schemaApiExpect(array_key_exists($capability, $coreInput), '核心组件目录缺少能力：' . $capability);
}
schemaApiExpect(($coreInput['namespace'] ?? '') === 'core', '核心组件目录必须使用 core 命名空间');

$repositoryMethods = get_class_methods(FormSchemaRepository::class);
foreach (['versions', 'findVersion', 'diff', 'rollback'] as $method) {
    schemaApiExpect(in_array($method, $repositoryMethods, true), '版本仓库缺少方法：' . $method);
}
$repositorySource = (string) file_get_contents(dirname(__DIR__) . '/app/console/service/FormSchemaRepository.php');
schemaApiExpect(str_contains($repositorySource, "'rollback'"), 'rollback 新版本必须记录 rollback origin');
schemaApiExpect(str_contains($repositorySource, "'rolledBackFromVersion'"), 'rollback 文档必须记录来源版本以产生新的不可变 hash');
schemaApiExpect(str_contains($repositorySource, 'saveVersion($formId'), 'rollback 必须通过 saveVersion 创建新版本');

$controllerSource = (string) file_get_contents(dirname(__DIR__) . '/app/console/controller/form/Designer.php');
foreach (['compile', 'import', 'export', 'versions', 'version', 'diff', 'rollback', 'component-catalog'] as $route) {
    schemaApiExpect(str_contains($controllerSource, "'{$route}"), 'Designer 缺少 API 路由：' . $route);
}
schemaApiExpect(str_contains($controllerSource, 'errorCode()') && str_contains($controllerSource, 'schemaPath()'), 'Schema API 错误响应必须包含 code/path');

$migrations = array_map('basename', glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: []);
sort($migrations, SORT_STRING);
$permissionMigrationName = '076_form_schema_api_permissions.sql';
schemaApiExpect(end($migrations) === $permissionMigrationName, '新增权限迁移必须使用当前不冲突的 076 编号');
$permissionMigration = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/' . $permissionMigrationName);
foreach (['compile', 'import', 'export', 'versions', 'version', 'diff', 'rollback', 'componentCatalog'] as $action) {
    schemaApiExpect(str_contains($permissionMigration, "'console/form.designer:{$action}'"), '权限迁移缺少动作：' . $action);
}
schemaApiExpect(str_contains($permissionMigration, 'INSERT IGNORE'), '权限迁移必须幂等');
schemaApiExpect(!preg_match('/\\b(?:DROP|TRUNCATE|DELETE|UPDATE)\\b/i', $permissionMigration), '权限迁移必须 forward-only');

echo "form schema API contract tests: PASS\n";
