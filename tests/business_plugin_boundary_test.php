<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\development\service\BusinessTargetService;
use app\console\development\service\BusinessModuleService;
use app\console\development\service\FormCrudDefinitionFactory;
use app\common\form\schema\FormSchemaCompiler;
use app\common\form\schema\FormSchemaValidator;

function boundaryExpect(bool $value, string $message): void
{
    if (!$value) throw new RuntimeException($message);
}
function boundaryReject(callable $operation, string $code): void
{
    try { $operation(); } catch (InvalidArgumentException $exception) {
        boundaryExpect($exception->getMessage() === $code, $exception->getMessage());
        return;
    }
    throw new RuntimeException('应拒绝：' . $code);
}

boundaryExpect(class_exists(BusinessTargetService::class), '缺少接通业务目标的应用边界');
$authorized = true;
$state = [];
$policy = new BusinessTargetService(
    dirname(__DIR__),
    'mysql',
    static function () use (&$authorized): bool { return $authorized; },
    static function () use (&$state): array { return $state; },
    static fn (): array => [
        ['code' => 'sample', 'name' => '示例', 'scopes' => ['console'], 'businessWritable' => true],
        ['code' => 'readonly', 'name' => '只读', 'scopes' => ['console'], 'businessWritable' => false],
    ],
    static fn (): array => ['fun_admin' => 'core', 'fun_other_item' => 'other'],
    static fn (string $table): bool => $table === 'fun_legacy'
);
$target = BusinessModuleService::normalizeTarget(['type' => 'plugin', 'pluginCode' => 'sample'], 'adopted');
boundaryExpect(count($policy->candidates()['list']) === 2, '仅返回核心与可写插件');
$policy->assertSelection($target, 'mysql', 'fun_legacy');
boundaryReject(fn () => $policy->assertSelection($target, 'archive', 'fun_legacy'), 'BUSINESS_DEFAULT_CONNECTION_ONLY');
boundaryReject(fn () => $policy->assertSelection($target, 'mysql', 'fun_admin'), 'BUSINESS_TABLE_FORBIDDEN');
boundaryReject(fn () => $policy->assertSelection($target, 'mysql', 'fun_other_item'), 'BUSINESS_TABLE_FORBIDDEN');
$state = ['sample' => ['lifecycle_state' => 'updating']];
boundaryReject(fn () => $policy->assertSelection($target, 'mysql', 'fun_legacy'), 'BUSINESS_TARGET_UNAVAILABLE');
$state = [];
$authorized = false;
boundaryExpect(count($policy->candidates()['list']) === 1, '无插件开发权限不泄露插件候选');
boundaryReject(fn () => $policy->assertSelection($target, 'mysql', 'fun_legacy'), 'BUSINESS_TARGET_FORBIDDEN');
$authorized = true;
$owned = BusinessModuleService::normalizeTarget(['type' => 'plugin', 'pluginCode' => 'sample'], 'created');
boundaryReject(fn () => $policy->assertSelection($owned, 'mysql', 'fun_legacy'), 'BUSINESS_TABLE_PREFIX_REQUIRED');
$policy->assertSelection($owned, 'mysql', 'fun_sample_item');
$schema = (new FormSchemaCompiler(new FormSchemaValidator()))->compile([
    'schemaVersion' => 2, 'key' => 'item', 'title' => '条目',
    'database' => ['connection' => 'mysql', 'table' => 'fun_sample_item', 'source' => 'created'],
    'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
        'database' => ['columnType' => 'varchar(255)', 'nullable' => false], 'children' => []]],
]);
$factory = new FormCrudDefinitionFactory();
$core = $factory->createFromSchema($schema, []);
$definition = $factory->forBusinessTarget($core, $owned);
boundaryExpect($definition->get('target') === ['type' => 'plugin', 'plugin' => 'sample', 'scope' => 'console'], '插件目标必须派生');
boundaryExpect($definition->get('generationTargets') === null, '不得携带核心制品路径');
boundaryExpect($definition->get('formSchema') === $core->get('formSchema'), '必须保留完整表单语义');
boundaryExpect($definition->get('permissionPrefix') === 'sample:item', '插件权限派生');
boundaryExpect($definition->get('routePath') === '/plugin/sample/item', '插件路由派生');
$enforcer = new \Casbin\Enforcer(dirname(__DIR__) . '/config/casbin/rbac_model.conf');
$enforcer->addPolicy('role:42', 'default', 'console/development.business', 'modules');
$enforcer->addGroupingPolicy('admin:42', 'role:42', 'default');
$resource = \app\console\authorization\service\PermissionResource::fromParts('console', 'development.Business', 'targets');
boundaryExpect($enforcer->enforce('admin:42', 'default', $resource['obj'], $resource['act']), '候选读取须复用业务列表授权');
$effects = 0;
$publisher = new \app\console\form\service\FormPublishService(
    new \app\console\form\service\FormDesignerService(dirname(__DIR__)),
    previewDdl: static function () use (&$effects): array { $effects++; return []; },
    moduleTargetReader: static fn (): array => $owned
);
foreach (['previewDynamic', 'publishDynamic'] as $method) {
    boundaryReject(fn () => $publisher->$method(['id' => 9], 'tester'), 'BUSINESS_PLUGIN_DYNAMIC_PUBLISH_FORBIDDEN');
}
boundaryExpect($effects === 0, '插件动态发布在编译、保存、DDL 之前拒绝');
// 运行默认授权分支：只隔离框架环境与数据库适配器，不替换授权服务。
function request(): \think\Request { return new \think\Request(); }
function app(string $name): object { return new class { public function getName(): string { return 'console'; } }; }
function config(string $name, mixed $default = null): mixed {
    return match ($name) {
        'funadmin' => ['auth_on' => true], 'funadmin.superAdminId' => 1,
        default => $default,
    };
}
function session(string $name): int { return $GLOBALS['boundaryAdminId']; }
function db_cache(string $key, callable $reader): array { return []; }
$GLOBALS['boundaryAdminId'] = 42;
$enforcer->addPolicy('role:42', 'default', 'console/development.devplugin', 'options');
$shared = new ReflectionProperty(\app\console\authorization\service\CasbinService::class, 'sharedEnforcer');
$shared->setAccessible(true);
$shared->setValue(null, $enforcer);
$productionPolicy = new BusinessTargetService(dirname(__DIR__), 'mysql',
    states: static fn (): array => [],
    options: static fn (): array => [['code' => 'sample', 'name' => '示例', 'scopes' => ['console'], 'businessWritable' => true]]);
boundaryExpect(count($productionPolicy->candidates()['list']) === 2, '默认授权必须使用普通角色的插件 options 权限');
$GLOBALS['boundaryAdminId'] = 43;
boundaryExpect(count($productionPolicy->candidates()['list']) === 1, '默认授权不得向未授权角色泄露候选');
echo "business plugin boundary tests: PASS\n";
