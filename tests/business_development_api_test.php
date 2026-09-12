<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\form\registry\FieldCapabilityRegistry;
use app\console\controller\base\AdminApiController;
use app\console\controller\development\Business;
use app\console\middleware\CheckAdminApiCsrf;
use app\console\middleware\CheckAdminApiRole;
use app\console\middleware\SystemLog;
use app\console\development\service\BusinessDevelopmentService;
use app\console\development\service\BusinessModuleService;
use app\console\development\service\DevCrudService;
use app\console\form\repository\FormSchemaRepository;
use app\console\form\service\FormDataService;
use app\console\form\service\FormDesignerService;
use app\console\form\service\FormPublishService;
use app\console\development\service\ManagedGenerationService;
use think\annotation\route\Get;
use think\annotation\route\Group;
use think\annotation\route\Pattern;
use think\annotation\route\Post;

function businessApiExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$root = dirname(__DIR__) . '/';
$controllerFile = $root . 'app/console/controller/development/Business.php';
$developmentFile = $root . 'app/console/development/service/BusinessDevelopmentService.php';
$moduleFile = $root . 'app/console/development/service/BusinessModuleService.php';

businessApiExpect(is_file($controllerFile), '缺少 Business 控制器');
businessApiExpect(is_file($developmentFile), '缺少 BusinessDevelopmentService');
businessApiExpect(is_file($moduleFile), '缺少 BusinessModuleService');
businessApiExpect(is_subclass_of(Business::class, AdminApiController::class), 'Business 必须继承 AdminApiController');

$controller = new ReflectionClass(Business::class);
$groups = $controller->getAttributes(Group::class);
businessApiExpect(count($groups) === 1 && $groups[0]->newInstance()->name === 'development/business', 'Business Group 必须为 development/business');
$middleware = $controller->getDefaultProperties()['middleware'] ?? [];
businessApiExpect($middleware === [CheckAdminApiRole::class, CheckAdminApiCsrf::class, SystemLog::class], 'Business 中间件顺序不正确');

$routes = [
    'modules' => [Get::class, 'modules'],
    'module' => [Get::class, 'modules/:id'],
    'createVisual' => [Post::class, 'modules/visual'],
    'inspectDatabase' => [Post::class, 'modules/from-database/inspect'],
    'createFromDatabase' => [Post::class, 'modules/from-database'],
    'validateSchema' => [Post::class, 'modules/:id/schema/validate'],
    'saveSchema' => [Post::class, 'modules/:id/schema/save'],
    'compileSchema' => [Post::class, 'modules/:id/schema/compile'],
    'exportSchema' => [Post::class, 'modules/:id/schema/export'],
    'schemaVersions' => [Get::class, 'modules/:id/schema/versions'],
    'schemaVersion' => [Get::class, 'modules/:id/schema/versions/:version'],
    'schemaDiff' => [Get::class, 'modules/:id/schema/diff'],
    'rollbackSchema' => [Post::class, 'modules/:id/schema/versions/:version/rollback'],
    'databaseTables' => [Get::class, 'database/tables'],
    'databaseTableSchema' => [Get::class, 'database/tables/:table/schema'],
    'previewPublish' => [Post::class, 'modules/:id/publish/preview'],
    'publish' => [Post::class, 'modules/:id/publish'],
    'runtimeMeta' => [Get::class, 'modules/:id/runtime-meta'],
    'previewFormalGeneration' => [Post::class, 'modules/:id/formal-generation/preview'],
    'formalGeneration' => [Post::class, 'modules/:id/formal-generation'],
    'generations' => [Get::class, 'generations'],
    'generation' => [Get::class, 'generations/:id'],
    'retryResources' => [Post::class, 'generations/:id/retry-resources'],
    'adoptResolvedBaseline' => [Post::class, 'modules/:id/baselines/adopt-resolved'],
    'fieldCapabilities' => [Get::class, 'field-capabilities'],
];
foreach ($routes as $method => [$attribute, $path]) {
    businessApiExpect($controller->hasMethod($method), '缺少控制器方法：' . $method);
    $attributes = $controller->getMethod($method)->getAttributes($attribute);
    businessApiExpect(count($attributes) === 1 && $attributes[0]->newInstance()->rule === $path, $method . ' 路由不匹配');
}
foreach (['module' => ['id'], 'compileSchema' => ['id'], 'exportSchema' => ['id'], 'schemaVersions' => ['id'], 'schemaVersion' => ['id', 'version'], 'schemaDiff' => ['id'], 'rollbackSchema' => ['id', 'version'], 'databaseTableSchema' => ['table']] as $method => $parameters) {
    $patterns = array_map(static fn (ReflectionAttribute $attribute): string => $attribute->newInstance()->name, $controller->getMethod($method)->getAttributes(Pattern::class));
    foreach ($parameters as $parameter) businessApiExpect(in_array($parameter, $patterns, true), $method . ' 缺少 Pattern：' . $parameter);
}

$controllerSource = (string) file_get_contents($controllerFile);
foreach (['BusinessModule::', 'CrudGeneration::', 'GeneratedFileBaseline::', 'Db::'] as $forbidden) {
    businessApiExpect(!str_contains($controllerSource, $forbidden), 'Controller 零 DB 违规：' . $forbidden);
}
foreach ([BusinessDevelopmentService::class, BusinessModuleService::class] as $service) {
    businessApiExpect(class_exists($service), '服务不可加载：' . $service);
}
$developmentSource = (string) file_get_contents($developmentFile);
foreach ([FormDesignerService::class, FormSchemaRepository::class, FormPublishService::class, ManagedGenerationService::class, FormDataService::class, DevCrudService::class] as $dependency) {
    businessApiExpect(str_contains($developmentSource, basename(str_replace('\\', '/', $dependency))), 'BusinessDevelopmentService 未编排：' . $dependency);
}
businessApiExpect(substr_count($developmentSource, '$this->assertSchemaIdentity(') >= 3, '动态发布必须再次绑定当前业务模块 Schema identity');
businessApiExpect(str_contains($developmentSource, "if (!is_array(\$publishConfig))"), '动态发布 publish_config 必须校验类型');
businessApiExpect(str_contains($developmentSource, 'saveCompiledVersionIfCurrentHash('), 'Schema 乐观锁比较与保存必须处于同一锁定事务');
foreach (['compileSchema', 'exportSchema', 'schemaVersions', 'schemaVersion', 'schemaDiff', 'rollbackSchema', 'databaseTables', 'databaseTableSchema'] as $method) {
    businessApiExpect((new ReflectionClass(BusinessDevelopmentService::class))->hasMethod($method), 'BusinessDevelopmentService 缺少能力：' . $method);
}
businessApiExpect(str_contains($developmentSource, '$this->schemas->rollback($formId,'), 'rollback 必须先通过 module id 解析 form id 并创建新版本');
businessApiExpect(str_contains($developmentSource, '$this->crud->tables(') && str_contains($developmentSource, '$this->crud->inspect('), '数据库表与表结构元数据必须复用只读 DevCrudService');
businessApiExpect(str_contains($developmentSource, '不支持独立重试'), 'managed 资源原子提交后不得伪装独立重试成功');

$registry = new FieldCapabilityRegistry();
$capabilities = BusinessDevelopmentService::fieldCapabilityPayload($registry, []);
businessApiExpect($capabilities['registryVersion'] === $registry->version(), '字段能力 registryVersion 错误');
businessApiExpect($capabilities['schemaVersion'] === 2, '字段能力 schemaVersion 错误');
businessApiExpect($capabilities['registryHash'] === $registry->hash(), '字段能力 registryHash 错误');
businessApiExpect($capabilities['capabilities'] === array_values($registry->definitions()), '字段能力 capabilities 错误');
businessApiExpect($capabilities['diagnostics'] === [], '字段能力 diagnostics 错误');

foreach ([0, -1] as $invalidId) {
    try {
        BusinessDevelopmentService::assertPositiveId($invalidId);
        businessApiExpect(false, '非法 ID 必须拒绝');
    } catch (InvalidArgumentException) {
    }
}
foreach (['', '1abc', 'ABC', 'has-dash', str_repeat('a', 62)] as $invalidCode) {
    try {
        BusinessDevelopmentService::assertCode($invalidCode);
        businessApiExpect(false, '非法 code 必须拒绝');
    } catch (InvalidArgumentException) {
    }
}
foreach (['', 'ABC', str_repeat('a', 63)] as $invalidHash) {
    try {
        BusinessDevelopmentService::assertHash($invalidHash);
        businessApiExpect(false, '非法 hash 必须拒绝');
    } catch (InvalidArgumentException) {
    }
}
businessApiExpect(BusinessDevelopmentService::pagination(0, 500) === [1, 100], '分页必须限制在 page>=1/pageSize<=100');

$serviceReflection = new ReflectionClass(BusinessDevelopmentService::class);
$serviceWithoutDependencies = $serviceReflection->newInstanceWithoutConstructor();
$creationPayload = $serviceReflection->getMethod('creationPayload');
foreach ([
    ['code' => 'sample', 'name' => '示例', 'status' => 2],
    ['code' => 'sample', 'name' => '示例', 'listConfig' => 'not-an-array'],
    ['code' => 'sample', 'name' => '示例', 'formConfig' => 'not-an-array'],
    ['code' => 'sample', 'name' => '示例', 'connection' => '../mysql'],
    ['code' => 'sample', 'name' => '示例', 'remark' => str_repeat('x', 1001)],
] as $invalidCreation) {
    try {
        $creationPayload->invoke($serviceWithoutDependencies, $invalidCreation, 'created', []);
        businessApiExpect(false, '创建输入白名单值必须严格校验');
    } catch (InvalidArgumentException) {
    }
}

$managedSource = (string) file_get_contents($root . 'app/console/development/service/ManagedGenerationService.php');
$stateRepositorySource = (string) file_get_contents($root . 'app/console/development/repository/DatabaseGenerationStateRepository.php');
businessApiExpect(str_contains($managedSource, 'public function adoptResolvedBaseline('), 'ManagedGenerationService 缺少严格 adopt-resolved');
businessApiExpect(str_contains($managedSource, "'conflict-no-base'") && str_contains($managedSource, 'remoteHash') && str_contains($managedSource, 'localHash'), 'adopt-resolved 必须严格校验最近 conflict-no-base 的 Local/Remote hash');
businessApiExpect(str_contains($managedSource, 'PathGuard::resolve(') && str_contains($managedSource, 'is_link('), 'adopt-resolved 必须拒绝路径逃逸与符号链接');
businessApiExpect(!str_contains($managedSource, "'metadata' => ['adoptedBy'"), 'baseline 表无 metadata 字段，不得写入不存在字段');
businessApiExpect(
    str_contains($managedSource, "'status' => \$blocked ? 'conflict' : 'planned'")
        && str_contains($managedSource, "\$manifest['plan'] = \$publicPlan"),
    'blocked preview 必须保存可供严格采纳的冲突审计'
);
businessApiExpect(str_contains($stateRepositorySource, "(string) \$generation->status !== 'conflict'"), 'baseline 仓储必须二次确认 generation 为 conflict');
businessApiExpect(str_contains($stateRepositorySource, 'array_intersect_key($record, array_flip('), 'baseline 仓储必须对白名单字段持久化');
$moduleServiceSource = (string) file_get_contents($root . 'app/console/development/service/BusinessModuleService.php');
businessApiExpect(str_contains($moduleServiceSource, "'availableActions'") && str_contains($moduleServiceSource, "'recover'"), 'generation DTO 必须根据恢复状态返回可用操作');
businessApiExpect(str_contains($moduleServiceSource, "'recoveryStatus'") && str_contains($moduleServiceSource, "'generationMode'"), 'generation DTO 必须提供前端统一 camelCase 字段');
$schemaRepositorySource = (string) file_get_contents($root . 'app/console/form/repository/FormSchemaRepository.php');
businessApiExpect(str_contains($schemaRepositorySource, 'public function saveCompiledVersionIfCurrentHash(') && str_contains($schemaRepositorySource, 'Form::lock(true)') && str_contains($schemaRepositorySource, "InvalidArgumentException('FORM_SCHEMA_CONFLICT')"), 'Schema CAS 必须锁定 Form 后比较当前 hash');

$controllerMethods = implode("\n", array_map(static function (ReflectionMethod $method) use ($controllerFile): string {
    $lines = file($controllerFile);
    return is_array($lines) ? implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1)) : '';
}, $controller->getMethods(ReflectionMethod::IS_PUBLIC)));
$errorMapperSource = (string) file_get_contents($root . 'app/console/development/http/BusinessApiErrorMapper.php');
businessApiExpect(str_contains($controllerSource, 'BusinessApiErrorMapper::map('), '控制器必须委托专用错误映射器');
businessApiExpect(str_contains($errorMapperSource, 'FORM_SCHEMA_CONFLICT') && str_contains($errorMapperSource, '409'), '错误映射器缺少 409 映射');
businessApiExpect(str_contains($errorMapperSource, '410') && str_contains($errorMapperSource, '422') && str_contains($errorMapperSource, '500'), '错误映射器缺少 410/422/500 映射');
businessApiExpect(str_contains($controllerSource, "nodeAccess('development/business/generate')"), 'managed preview sensitive 必须仅由 generate 权限控制');
businessApiExpect(substr_count($controllerSource, "nodeAccess('development/business/generate')") >= 2, 'managed execute 必须同时检查 generate 权限');
businessApiExpect(str_contains($controllerSource, "nodeAccess('development/business/apply-resources')"), 'managed execute 必须额外检查 resource apply 权限');

// 加载真实注解路由并仅检查调度目标，不执行控制器或访问业务数据库。
$app = new \think\App($root);
$app->http->name('console');
$app->setAppPath($root . 'app/console/');
$app->setNamespace('app\\console');
$app->initialize();
set_exception_handler(static function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
});
$app->event->trigger(\think\event\RouteLoaded::class);
(new ReflectionProperty(\think\Route::class, 'request'))->setValue($app->route, $app->request);
foreach ($routes as $action => [$attribute, $path]) {
    $httpMethod = strtoupper((new ReflectionClass($attribute))->getShortName());
    $uri = 'development/business/' . str_replace([':id', ':version', ':table'], ['42', '3', 'fun_example'], $path);
    $app->request->setMethod($httpMethod);
    $dispatch = $app->route->check(str_replace('/', '|', $uri));
    $actual = $dispatch === false ? null : $dispatch->getDispatch();
    if (is_array($actual)) $actual = implode('/', $actual);
    $expected = 'development.Business/' . $action;
    businessApiExpect($actual === $expected, $httpMethod . ' ' . $uri . ' 应匹配 ' . $expected . '，实际为 ' . var_export($actual, true));
}
foreach (['modules/invalid', 'modules/42/unknown', 'generations/42/unknown', 'database/tables/fun_example/unknown'] as $path) {
    $app->request->setMethod('GET');
    businessApiExpect($app->route->check(str_replace('/', '|', 'development/business/' . $path)) === false, '无效路径不应被前缀路由截获：' . $path);
}

echo "business development API tests: PASS\n";
