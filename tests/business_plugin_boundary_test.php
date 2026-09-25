<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\admin\development\service\BusinessTargetService;
use app\admin\development\service\BusinessModuleService;
use app\admin\development\service\FormCrudDefinitionFactory;
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

$app = new think\App(dirname(__DIR__));
think\Container::setInstance($app);
$app->config->set(['connections' => ['mysql' => ['prefix' => 'fun_']]], 'database');
boundaryExpect(class_exists(BusinessTargetService::class), '缺少接通业务目标的应用边界');
$authorized = true;
$state = [];
$policy = new BusinessTargetService(
    dirname(__DIR__),
    'mysql',
    static function () use (&$authorized): bool { return $authorized; },
    static function () use (&$state): array { return $state; },
    static fn (): array => [
        ['code' => 'sample', 'name' => '示例', 'scopes' => ['admin'], 'businessWritable' => true],
        ['code' => 'readonly', 'name' => '只读', 'scopes' => ['admin'], 'businessWritable' => false],
        ['code' => 'noconsole', 'name' => '无后台', 'scopes' => [], 'businessWritable' => true],
        ['code' => '../secret', 'name' => 'secret-content', 'scopes' => ['admin'], 'businessWritable' => true],
    ],
    static fn (): array => ['fun_admin' => 'core', 'fun_other_item' => 'other'],
    static fn (string $table): bool => $table === 'fun_legacy'
);
$target = BusinessModuleService::normalizeTarget(['type' => 'plugin', 'pluginCode' => 'sample'], 'adopted');
$candidates = $policy->candidates()['list'];
boundaryExpect(count($candidates) === 4, '安全的不可用插件也必须逐项返回');
$byCode = array_column($candidates, null, 'pluginCode');
boundaryExpect($byCode['sample']['available'] === true && $byCode['sample']['reason'] === null, '可用候选显式声明状态');
boundaryExpect($byCode['readonly']['available'] === false && $byCode['readonly']['reason'] === ['code' => 'BUSINESS_TARGET_READ_ONLY', 'message' => '插件目录或必要文件不可写'], '只读候选必须有安全原因');
boundaryExpect($byCode['noconsole']['reason']['code'] === 'BUSINESS_TARGET_ADMIN_MISSING', '缺少 console 必须有原因');
boundaryExpect(!str_contains(json_encode($candidates), 'secret'), '非法标识及内容不可泄露');
boundaryReject(fn () => $policy->assertSelection(['type' => 'plugin', 'pluginCode' => 'readonly'], 'mysql', 'fun_legacy'), 'BUSINESS_TARGET_UNAVAILABLE');
foreach ([
    ['recovery_token' => 'secret-recovery', 'reason' => 'BUSINESS_TARGET_RECOVERY_LOCKED'],
    ['operation_token' => 'secret-operation', 'reason' => 'BUSINESS_TARGET_OPERATION_LOCKED'],
    ['lifecycle_state' => 'updating', 'reason' => 'BUSINESS_TARGET_LIFECYCLE_BLOCKED'],
] as $locked) {
    $expectedReason = $locked['reason'];
    unset($locked['reason']);
    $state = ['sample' => $locked];
    $candidate = array_column($policy->candidates()['list'], null, 'pluginCode')['sample'];
    boundaryExpect($candidate['available'] === false && $candidate['reason']['code'] === $expectedReason, '锁状态必须有逐项原因');
    boundaryExpect(array_keys($candidate) === ['type', 'pluginCode', 'name', 'scope', 'available', 'reason'], 'DTO 必须白名单输出');
    boundaryExpect(!str_contains(json_encode($candidate), 'secret'), '不得输出锁令牌');
    boundaryReject(fn () => $policy->assertSelection($target, 'mysql', 'fun_legacy'), 'BUSINESS_TARGET_UNAVAILABLE');
}
$state = [];
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
boundaryExpect($definition->get('target') === ['type' => 'plugin', 'plugin' => 'sample', 'scope' => 'admin'], '插件目标必须派生');
boundaryExpect($definition->get('generationTargets') === null, '不得携带核心制品路径');
boundaryExpect($definition->get('formSchema') === $core->get('formSchema'), '必须保留完整表单语义');
boundaryExpect($definition->get('permissionPrefix') === 'sample:item', '插件权限派生');
boundaryExpect($definition->get('routePath') === '/plugin/sample/item', '插件路由派生');
// 采纳表的受管时间列必须保留真实 Schema，而不是套用新建表的 nullable 默认值。
$adoptedDocument = $schema->document();
$adoptedDocument['database']['source'] = 'adopted';
$adoptedSchema = (new FormSchemaCompiler(new FormSchemaValidator()))->compile($adoptedDocument);
foreach ([false, true] as $nullable) {
    $actualSchema = ['primaryKey' => ['id'], 'columns' => [
        ['name' => 'id', 'type' => 'bigint unsigned', 'nullable' => false],
        ['name' => 'title', 'type' => 'varchar(255)', 'nullable' => false],
    ]];
    foreach (['created_at', 'updated_at', 'deleted_at'] as $name) {
        $actualSchema['columns'][] = ['name' => $name, 'type' => 'datetime', 'nullable' => $nullable];
    }
    $adoptedDefinition = $factory->createFromSchema($adoptedSchema, [], [], $actualSchema);
    $requirement = \app\common\plugin\sdk\ExternalTableRequirements::fromDefinition($adoptedDefinition);
    $columns = array_column($requirement['columns'], null, 'name');
    foreach (['created_at', 'updated_at', 'deleted_at'] as $name) {
        boundaryExpect($columns[$name]['nullable'] === $nullable, '采纳时间字段丢失真实 nullable：' . $name);
    }
    (new \app\common\plugin\sdk\ExternalTableRequirements('mysql', static fn (): array => $actualSchema))->assertCompatible([$requirement]);
}
$coreFields = array_column($core->fields(), null, 'name');
boundaryExpect($coreFields['created_at']['nullable'] && !$coreFields['id']['nullable'], '新建表受管字段默认值不得改变');
$enforcer = new \Casbin\Enforcer(dirname(__DIR__) . '/config/casbin/rbac_model.conf');
$enforcer->addPolicy('role:42', 'default', 'admin/development.business', 'modules');
$enforcer->addGroupingPolicy('admin:42', 'role:42', 'default');
$resource = \app\admin\authorization\service\PermissionResource::fromParts('admin', 'development.Business', 'targets');
boundaryExpect($enforcer->enforce('admin:42', 'default', $resource['obj'], $resource['act']), '候选读取须复用业务列表授权');
$effects = 0;
$publisher = new \app\admin\form\service\FormPublishService(
    new \app\admin\form\service\FormDesignerService(dirname(__DIR__)),
    previewDdl: static function () use (&$effects): array { $effects++; return []; },
    moduleTargetReader: static fn (): array => $owned
);
foreach (['previewDynamic', 'publishDynamic'] as $method) {
    boundaryReject(fn () => $publisher->$method(['id' => 9], 'tester'), 'BUSINESS_PLUGIN_DYNAMIC_PUBLISH_FORBIDDEN');
}
boundaryExpect($effects === 0, '插件动态发布在编译、保存、DDL 之前拒绝');
// 运行默认授权分支：只隔离框架环境与数据库适配器，不替换授权服务。
function request(): \think\Request { return new \think\Request(); }
function app(string $name): object { return new class { public function getName(): string { return 'admin'; } }; }
function config(string $name, mixed $default = null): mixed {
    return match ($name) {
        'funadmin' => ['auth_on' => true], 'funadmin.superAdminId' => 1,
        default => $default,
    };
}
function session(string $name): int { return $GLOBALS['boundaryAdminId']; }
function db_cache(string $key, callable $reader): array { return []; }
$GLOBALS['boundaryAdminId'] = 42;
$enforcer->addPolicy('role:42', 'default', 'admin/development.devplugin', 'options');
$shared = new ReflectionProperty(\app\admin\authorization\service\CasbinService::class, 'sharedEnforcer');
$shared->setAccessible(true);
$shared->setValue(null, $enforcer);
$productionPolicy = new BusinessTargetService(dirname(__DIR__), 'mysql',
    states: static fn (): array => [],
    options: static fn (): array => [['code' => 'sample', 'name' => '示例', 'scopes' => ['admin'], 'businessWritable' => true]]);
boundaryExpect(count($productionPolicy->candidates()['list']) === 2, '默认授权必须使用普通角色的插件 options 权限');
$GLOBALS['boundaryAdminId'] = 43;
boundaryExpect(count($productionPolicy->candidates()['list']) === 1, '默认授权不得向未授权角色泄露候选');
// 真实磁盘候选只使用本次独占的隔离根，不接触项目插件。
$fixtureRoot = dirname(__DIR__) . '/runtime/business-candidates-' . bin2hex(random_bytes(6));
$scaffolder = new \app\common\plugin\sdk\PluginScaffolder($fixtureRoot . '/plugins');
$scaffolder->scaffold('emptyplugin', '无后台插件', false, false, false);
$scaffolder->scaffold('noweb', '无前端插件', false, true, false);
$scaffolder->scaffold('diskreadonly', '磁盘只读插件', false, true, true);
chmod($fixtureRoot . '/plugins/diskreadonly/plugin.json', 0444);
$scaffolder->scaffold('unsafe', 'secret-symlink', false, true, true);
symlink($fixtureRoot . '/plugins/noweb/plugin.json', $fixtureRoot . '/plugins/unsafe/secret');
$diskPolicy = new BusinessTargetService($fixtureRoot, authorized: static fn (): bool => true, states: static fn (): array => []);
$diskCandidates = array_column($diskPolicy->candidates()['list'], null, 'pluginCode');
boundaryExpect(($diskCandidates['emptyplugin']['reason']['code'] ?? '') === 'BUSINESS_TARGET_ADMIN_MISSING', '无任何应用目录的本地插件也应返回缺 console 原因');
boundaryExpect(($diskCandidates['noweb']['reason']['code'] ?? '') === 'BUSINESS_TARGET_ADMIN_WEB_MISSING', '无 admin-web 必须给出安全原因');
boundaryExpect(($diskCandidates['diskreadonly']['reason']['code'] ?? '') === 'BUSINESS_TARGET_READ_ONLY', '真实磁盘只读必须被识别');
boundaryExpect(!isset($diskCandidates['unsafe']) && !str_contains(json_encode($diskCandidates), 'secret'), '非法符号链接插件不能出现在候选中');
$noEnumeration = new BusinessTargetService($fixtureRoot, authorized: static fn (): bool => false,
    states: static function (): array { throw new RuntimeException('无权限不得读取状态'); },
    options: static function (): array { throw new RuntimeException('无权限不得枚举插件'); });
boundaryExpect(count($noEnumeration->candidates()['list']) === 1, '无权限只返回核心目标');
echo "business plugin boundary tests: PASS\n";
