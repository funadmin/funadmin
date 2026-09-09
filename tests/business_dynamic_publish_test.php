<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\model\BusinessModule;
use app\console\model\Form;
use app\console\model\FormSchemaVersion;
use app\console\service\FormDesignerService;
use app\console\service\FormMigrationException;
use app\console\service\FormPublishService;
use app\console\service\FormSchemaRepository;
use think\App;
use think\facade\Db;

function dynamicPublishExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dynamicPublishSchema(string $source = 'created'): array
{
    return [
        'schemaVersion' => 2,
        'key' => 'orders',
        'title' => '订单',
        'database' => ['connection' => 'mysql', 'table' => 'fun_orders', 'source' => $source],
        'nodes' => [[
            'id' => 'node_title',
            'kind' => 'field',
            'type' => 'input',
            'field' => 'title',
            'title' => '标题',
            'database' => ['columnType' => 'varchar(255)', 'nullable' => false],
            'children' => [],
        ]],
    ];
}

$root = dirname(__DIR__) . '/';
$serviceSource = (string) file_get_contents($root . 'app/console/service/FormPublishService.php');
$designerSource = (string) file_get_contents($root . 'app/console/service/FormDesignerService.php');
$dataSource = (string) file_get_contents($root . 'app/console/service/FormDataService.php');
$designerController = (string) file_get_contents($root . 'app/console/controller/form/Designer.php');

preg_match('/public function previewDynamic\([^}]+\n    }/s', $serviceSource, $previewDynamicMatch);
preg_match('/public function publishDynamic\([\s\S]+?\n    }\n\n    public function status/s', $serviceSource, $publishDynamicMatch);
$dynamicMethods = ($previewDynamicMatch[0] ?? '') . ($publishDynamicMatch[0] ?? '');
dynamicPublishExpect($dynamicMethods !== '', '动态发布方法边界不存在');
foreach (['FormCrudDefinitionFactory', 'DevCrudService', 'preflightGeneration(', '->generate('] as $forbidden) {
    dynamicPublishExpect(!str_contains($dynamicMethods, $forbidden), '纯动态方法体不得调用源码生成能力：' . $forbidden);
}
dynamicPublishExpect(str_contains($serviceSource, 'previewDynamic('), '动态发布服务缺少 previewDynamic');
dynamicPublishExpect(str_contains($serviceSource, 'publishDynamic('), '动态发布服务缺少 publishDynamic');
dynamicPublishExpect(str_contains($serviceSource, 'public function preview(') && str_contains($serviceSource, 'public function publish('), '完整静态发布必须与动态发布共存');
dynamicPublishExpect(str_contains($designerController, 'previewDynamic(') && str_contains($designerController, 'publishDynamic('), '发布控制器必须调用纯动态 API');
preg_match('/public function previewPublish\([\s\S]+?\n    }\n\n[\s\S]*?public function publish\([\s\S]+?\n    }\n\n/s', $designerController, $dynamicControllerMatch);
$dynamicControllerMethods = $dynamicControllerMatch[0] ?? '';
dynamicPublishExpect($dynamicControllerMethods !== '', '动态发布控制器方法边界不存在');
dynamicPublishExpect(!str_contains($dynamicControllerMethods, "post('confirmToken'") && !str_contains($dynamicControllerMethods, "post('allowOverwrite'"), '动态发布控制器不得接受源码覆盖参数');
foreach (['->preview(', '->publish(', '->generation(', '->retryResources('] as $legacyCall) {
    dynamicPublishExpect(!str_contains($dynamicControllerMethods, $legacyCall), '动态发布控制器不得调用完整发布或已删除方法：' . $legacyCall);
}
dynamicPublishExpect(str_contains($dataSource, 'BusinessModule'), '运行态必须通过 BusinessModule 解析已发布 Schema');
dynamicPublishExpect(str_contains($dataSource, "where('schema_hash', \$publishedHash)"), '运行态必须按 BusinessModule 发布 hash 精确锁定不可变快照');
dynamicPublishExpect(!str_contains($dataSource, "where('schema_hash', (string) \$form->published_schema_hash)"), '运行态不得再以 Form 草稿模型的发布 hash 为权威来源');
dynamicPublishExpect(str_contains($dataSource, '077_business_development_center'), '077 未执行时必须返回清晰错误');
dynamicPublishExpect(str_contains($designerSource, 'public function applyDynamicDdl('), '生产设计器服务必须提供动态 DDL 专用入口');
$dynamicDdlMethod = new ReflectionMethod(FormDesignerService::class, 'applyDynamicDdl');
$dynamicDdlLines = file($dynamicDdlMethod->getFileName());
dynamicPublishExpect(is_array($dynamicDdlLines), '无法读取动态 DDL 生产源码');
$dynamicDdlSource = implode('', array_slice(
    $dynamicDdlLines,
    $dynamicDdlMethod->getStartLine() - 1,
    $dynamicDdlMethod->getEndLine() - $dynamicDdlMethod->getStartLine() + 1
));
dynamicPublishExpect(str_contains($dynamicDdlSource, 'function applyDynamicDdl('), 'Reflection 未精确提取动态 DDL 方法边界');
foreach (['migrationPath(', 'file_put_contents(', 'mkdir(', 'SystemMigration', 'database/generated', 'applyMigration(', 'CrudGeneration', 'AtomicWriter'] as $forbidden) {
    dynamicPublishExpect(!str_contains($dynamicDdlSource, $forbidden), '动态 DDL 方法本身严禁源码写入、迁移登记或生成审计：' . $forbidden);
}
dynamicPublishExpect(str_contains($dynamicDdlSource, 'previewMigration('), '动态 DDL 执行前必须基于实际 Schema 二次 preflight');
dynamicPublishExpect(str_contains($dynamicDdlSource, 'hash('), '动态 DDL 执行前必须复核 DDL hash');
dynamicPublishExpect(str_contains($designerSource, 'assertDynamicForwardSql('), '动态 DDL 必须经过 forward-only 白名单');
dynamicPublishExpect(str_contains($serviceSource, 'applyDynamicDdl'), '动态发布默认 applyDdl 必须使用无文件副作用入口');
dynamicPublishExpect(str_contains($serviceSource, 'Db::transaction(') && str_contains($serviceSource, 'BusinessModule::lock(true)'), 'BusinessModule 与 Form 最终发布状态必须在锁定事务内切换');
foreach (['business_module_id', 'publish_mode', 'publish_status', 'published_schema_hash', 'published_at'] as $atomicField) {
    dynamicPublishExpect(str_contains($serviceSource, "'{$atomicField}'"), '最终发布事务缺少 Form 原子字段：' . $atomicField);
}
dynamicPublishExpect(str_contains($serviceSource, 'published_schema_version') && str_contains($serviceSource, 'FORM_SCHEMA_CONFLICT'), '最终发布事务必须以版本/hash 基线防止旧请求覆盖新发布');
dynamicPublishExpect(!str_contains($serviceSource, "setStatus(\$formId, 'dynamic_published'"), 'dynamic_published 不得在最终事务外重复写入');

$schemas = new FormSchemaRepository();
$forms = new FormDesignerService($root, $schemas);
$generatedBefore = glob($root . 'database/generated/*') ?: [];
$adoptedDefinition = [
    'form_key' => 'orders',
    'name' => '订单',
    'table_name' => 'fun_orders',
    'connection' => 'mysql',
    'source_type' => 'adopted',
    'fields' => [[
        'field_name' => 'title',
        'label' => '标题',
        'type' => 'input',
        'column_type' => 'varchar(255)',
        'relation_type' => 'none',
    ]],
    'dynamic_ddl_hash' => hash('sha256', ''),
];
$adoptedDdl = $forms->applyDynamicDdl($adoptedDefinition);
dynamicPublishExpect($adoptedDdl['mode'] === 'none' && $adoptedDdl['applied'] === false, '生产动态 DDL 入口必须保持 adopted 零 DDL');
dynamicPublishExpect((glob($root . 'database/generated/*') ?: []) === $generatedBefore, '生产动态 DDL 入口不得写任何 migration 文件');

$effects = ['save' => 0, 'ddl' => 0, 'verify' => 0, 'metadata' => 0];
$statuses = [];
$metadataCommitted = false;
$service = new FormPublishService(
    $forms,
    $schemas,
    static function (array $payload) use (&$effects): array {
        $effects['save']++;
        return ['form' => ['id' => 7] + $payload, 'fields' => $payload['fields']];
    },
    static fn (array $payload): array => ['mode' => $payload['source_type'] === 'created' ? 'create' : 'none', 'sql' => 'CREATE TABLE `fun_orders` (`id` bigint);'],
    static function (array $payload) use (&$effects): array {
        $effects['ddl']++;
        return ['mode' => 'create', 'sql' => 'CREATE TABLE `fun_orders` (`id` bigint);', 'applied' => true];
    },
    static function () use (&$effects): void {
        $effects['verify']++;
    },
    static function (array $saved, object $compiled, string $operator) use (&$effects, &$metadataCommitted): array {
        $effects['metadata']++;
        $metadataCommitted = true;
        return ['id' => 3, 'published_schema_version' => 2, 'published_schema_hash' => $compiled->hash(), 'operator' => $operator];
    },
    static function (int $formId, string $status, array $extra = [], ?array $baseline = null) use (&$statuses): void {
        dynamicPublishExpect($baseline === ['exists' => false, 'hash' => '', 'version' => 0], '每个 injected 中间状态必须收到同一 attempt baseline');
        $statuses[] = $status;
    },
    static fn (): array => ['exists' => false, 'hash' => '', 'version' => 0],
    static function (int $formId) use (&$metadataCommitted): array {
        dynamicPublishExpect($metadataCommitted, '最终 Form 必须在元数据事务完成后重读');
        return ['id' => $formId, 'publish_status' => 'published', 'published_schema_hash' => 'committed'];
    }
);

$schema = dynamicPublishSchema();
$hash = $schemas->compile($schema)->hash();
$preview = $service->previewDynamic(['schema_document' => $schema, 'schemaHash' => $hash]);
dynamicPublishExpect($preview['formSchemaHash'] === $hash && $preview['publishStatus'] === 'ready', 'preview 必须返回 canonical hash 与动态就绪状态');
dynamicPublishExpect(!isset($preview['definition'], $preview['generationId'], $preview['plan'], $preview['sensitive']), 'preview 不得返回源码生成计划或确认令牌');
dynamicPublishExpect($effects === ['save' => 0, 'ddl' => 0, 'verify' => 0, 'metadata' => 0], 'preview 必须零写入、零生成副作用');

$result = $service->publishDynamic(['schema_document' => $schema, 'schemaHash' => $hash], 'tester');
dynamicPublishExpect($result['publishStatus'] === 'dynamic_published', '发布完成状态必须为 dynamic_published');
dynamicPublishExpect($result['routePath'] === '/development/business/runtime/orders', '发布响应路径必须精确命中稳定动态业务宿主路由');
dynamicPublishExpect(($result['form']['publish_status'] ?? null) === 'published', '发布响应必须返回元数据事务完成后重读的最终 Form 状态');
dynamicPublishExpect($statuses === ['validating', 'ddl_pending', 'publishing'], '最终 dynamic_published 只能由元数据事务写入，不得事务外重复写');
dynamicPublishExpect($effects === ['save' => 1, 'ddl' => 1, 'verify' => 1, 'metadata' => 1], 'created 发布必须保存、执行 forward-only DDL、验表并发布元数据各一次');

$adoptedEffects = ['ddl' => 0];
$adoptedStatuses = [];
$adoptedService = new FormPublishService(
    $forms,
    $schemas,
    static fn (array $payload): array => ['form' => ['id' => 8] + $payload, 'fields' => $payload['fields']],
    static fn (): array => ['mode' => 'none', 'sql' => '', 'message' => '采纳表禁止 DDL'],
    static function () use (&$adoptedEffects): array {
        $adoptedEffects['ddl']++;
        return [];
    },
    static function (): void {},
    static fn (array $saved, object $compiled): array => ['published_schema_version' => 1, 'published_schema_hash' => $compiled->hash()],
    static function (int $formId, string $status, array $extra = [], ?array $baseline = null) use (&$adoptedStatuses): void {
        $adoptedStatuses[] = $status;
    },
    static fn (): array => ['exists' => false, 'hash' => '', 'version' => 0],
    static fn (int $formId): array => ['id' => $formId, 'publish_status' => 'published']
);
$adoptedSchema = dynamicPublishSchema('adopted');
$adoptedHash = $schemas->compile($adoptedSchema)->hash();
$adoptedService->publishDynamic(['schema_document' => $adoptedSchema, 'schemaHash' => $adoptedHash], 'tester');
dynamicPublishExpect($adoptedEffects['ddl'] === 0, 'adopted 表发布严禁执行结构修改');

try {
    $service->publishDynamic(['schema_document' => $schema, 'schemaHash' => str_repeat('f', 64)], 'tester');
    dynamicPublishExpect(false, '请求 schemaHash 冲突必须拒绝');
} catch (InvalidArgumentException $exception) {
    dynamicPublishExpect($exception->getMessage() === 'FORM_SCHEMA_CONFLICT', '发布 hash 冲突必须提供稳定 409 语义');
}

$failureStatuses = [];
$saveFailure = new FormPublishService(
    $forms,
    $schemas,
    static function (): array { throw new RuntimeException('save failed'); },
    static fn (): array => ['mode' => 'create', 'sql' => 'CREATE TABLE `fun_orders` (`id` bigint);'],
    static fn (): array => [],
    static function (): void {},
    static fn (): array => [],
    static function (int $formId, string $status, array $extra = [], ?array $baseline = null) use (&$failureStatuses): void { $failureStatuses[] = $status; },
    static fn (): array => ['exists' => false, 'hash' => '', 'version' => 0]
);
try {
    $saveFailure->publishDynamic(['id' => 9, 'schema_document' => $schema, 'schemaHash' => $hash], 'tester');
    dynamicPublishExpect(false, 'DDL 前保存失败必须抛出');
} catch (RuntimeException) {
    dynamicPublishExpect(end($failureStatuses) === 'ddl_failed', 'DDL 前保存失败必须标记 ddl_failed');
}

$verifyStatuses = [];
$verifyFailure = new FormPublishService(
    $forms,
    $schemas,
    static fn (array $payload): array => ['form' => ['id' => 10] + $payload, 'fields' => $payload['fields']],
    static fn (): array => ['mode' => 'create', 'sql' => 'CREATE TABLE `fun_orders` (`id` bigint);'],
    static fn (): array => ['mode' => 'create', 'applied' => true],
    static function (): void { throw new RuntimeException('verify failed'); },
    static fn (): array => [],
    static function (int $formId, string $status, array $extra = [], ?array $baseline = null) use (&$verifyStatuses): void { $verifyStatuses[] = $status; },
    static fn (): array => ['exists' => false, 'hash' => '', 'version' => 0]
);
try {
    $verifyFailure->publishDynamic(['schema_document' => $schema, 'schemaHash' => $hash], 'tester');
    dynamicPublishExpect(false, 'DDL 成功后 verify 失败必须抛出');
} catch (RuntimeException) {
    dynamicPublishExpect(end($verifyStatuses) === 'metadata_partial', 'DDL 成功后 verify 失败必须标记 metadata_partial');
}

$migrationStatuses = [];
$migrationFailure = new FormPublishService(
    $forms,
    $schemas,
    static fn (array $payload): array => ['form' => ['id' => 11] + $payload, 'fields' => $payload['fields']],
    static fn (): array => ['mode' => 'create', 'sql' => 'CREATE TABLE `fun_orders` (`id` bigint);'],
    static function (): never { throw new FormMigrationException('post ddl failure', true); },
    static function (): void {},
    static fn (): array => [],
    static function (int $formId, string $status, array $extra = [], ?array $baseline = null) use (&$migrationStatuses): void { $migrationStatuses[] = $status; },
    static fn (): array => ['exists' => false, 'hash' => '', 'version' => 0]
);
try {
    $migrationFailure->publishDynamic(['schema_document' => $schema, 'schemaHash' => $hash], 'tester');
    dynamicPublishExpect(false, 'ddlApplied=true 的迁移异常必须抛出');
} catch (FormMigrationException) {
    dynamicPublishExpect(end($migrationStatuses) === 'metadata_partial', 'ddlApplied=true 的迁移异常必须标记 metadata_partial');
}

$baselineObserved = null;
$concurrentStatuses = [];
$concurrentService = new FormPublishService(
    $forms,
    $schemas,
    static fn (array $payload): array => ['form' => ['id' => 12] + $payload, 'fields' => $payload['fields']],
    static fn (): array => ['mode' => 'create', 'sql' => 'CREATE TABLE `fun_orders` (`id` bigint);'],
    static fn (): array => ['mode' => 'create', 'applied' => true],
    static function (): void {},
    static function (array $saved, object $compiled, string $operator, array $baseline) use (&$baselineObserved): never {
        $baselineObserved = $baseline;
        throw new InvalidArgumentException('FORM_SCHEMA_CONFLICT');
    },
    static function (int $formId, string $status, array $extra = [], ?array $baseline = null) use (&$concurrentStatuses): void { $concurrentStatuses[] = $status; },
    static fn (): array => ['exists' => true, 'hash' => str_repeat('a', 64), 'version' => 4]
);
try {
    $concurrentService->publishDynamic(['schema_document' => $schema, 'schemaHash' => $hash], 'tester');
    dynamicPublishExpect(false, '并发旧发布必须被稳定拒绝');
} catch (InvalidArgumentException $exception) {
    dynamicPublishExpect($exception->getMessage() === 'FORM_SCHEMA_CONFLICT', '并发旧发布必须返回稳定 FORM_SCHEMA_CONFLICT');
    dynamicPublishExpect($baselineObserved === ['exists' => true, 'hash' => str_repeat('a', 64), 'version' => 4], 'publish 开始时必须记录当前 module 发布 hash/version 基线');
    dynamicPublishExpect(end($concurrentStatuses) === 'publishing', '并发旧发布冲突不得覆盖新发布的最终状态');
}

$metadataStatuses = [];
$metadataFailure = new FormPublishService(
    $forms,
    $schemas,
    static fn (array $payload): array => ['form' => ['id' => 10] + $payload, 'fields' => $payload['fields']],
    static fn (): array => ['mode' => 'create', 'sql' => 'CREATE TABLE `fun_orders` (`id` bigint);'],
    static fn (): array => ['mode' => 'create', 'applied' => true],
    static function (): void {},
    static function (): array { throw new RuntimeException('metadata failed'); },
    static function (int $formId, string $status, array $extra = [], ?array $baseline = null) use (&$metadataStatuses): void { $metadataStatuses[] = $status; },
    static fn (): array => ['exists' => false, 'hash' => '', 'version' => 0]
);
try {
    $metadataFailure->publishDynamic(['schema_document' => $schema, 'schemaHash' => $hash], 'tester');
    dynamicPublishExpect(false, 'DDL 后元数据失败必须抛出');
} catch (RuntimeException) {
    dynamicPublishExpect(end($metadataStatuses) === 'metadata_partial', 'DDL 后元数据失败必须标记 metadata_partial 且不得反向 DDL');
}

$app = new App($root);
$app->http->name('console');
$app->setAppPath($root . 'app/console/');
$app->setNamespace('app\\console');
$app->initialize();
$originalDatabaseConfig = (array) config('database');
$mysql = (array) ($originalDatabaseConfig['connections']['mysql'] ?? []);
$temporaryDatabase = 'funadmin_dynamic_publish_' . bin2hex(random_bytes(6));
$identifier = static function (string $value): string {
    dynamicPublishExpect((bool) preg_match('/^[a-z0-9_]+$/', $value), '隔离数据库标识符不安全');
    return '`' . $value . '`';
};
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => true,
];
$server = new PDO(
    'mysql:host=' . ($mysql['hostname'] ?? '127.0.0.1') . ';port=' . ($mysql['hostport'] ?? '3306') . ';charset=' . ($mysql['charset'] ?? 'utf8mb4'),
    (string) ($mysql['username'] ?? ''),
    (string) ($mysql['password'] ?? ''),
    $pdoOptions
);
$database = null;

try {
    $server->exec('CREATE DATABASE ' . $identifier($temporaryDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database = new PDO(
        'mysql:host=' . ($mysql['hostname'] ?? '127.0.0.1') . ';port=' . ($mysql['hostport'] ?? '3306') . ';dbname=' . $temporaryDatabase . ';charset=' . ($mysql['charset'] ?? 'utf8mb4'),
        (string) ($mysql['username'] ?? ''),
        (string) ($mysql['password'] ?? ''),
        $pdoOptions
    );
    $database->exec(<<<'SQL'
CREATE TABLE `fun_form` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `business_module_id` bigint unsigned NULL,
  `publish_mode` varchar(20) NOT NULL DEFAULT 'dynamic',
  `form_key` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `connection` varchar(50) NOT NULL DEFAULT 'mysql',
  `source_type` varchar(20) NOT NULL DEFAULT 'created',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `list_config` json DEFAULT NULL,
  `form_config` json DEFAULT NULL,
  `schema_version` int NOT NULL DEFAULT 1,
  `schema_document` json DEFAULT NULL,
  `schema_hash` char(64) NULL,
  `schema_origin` varchar(20) NOT NULL DEFAULT 'designer',
  `publish_config` json DEFAULT NULL,
  `publish_status` varchar(32) NOT NULL DEFAULT 'draft',
  `published_at` datetime NULL,
  `published_schema_hash` char(64) NULL,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_form_key` (`form_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_form_field` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint unsigned NOT NULL,
  `field_name` varchar(64) NOT NULL,
  `label` varchar(100) NOT NULL DEFAULT '',
  `type` varchar(30) NOT NULL DEFAULT 'input',
  `column_type` varchar(100) NOT NULL DEFAULT '',
  `nullable` tinyint(1) NOT NULL DEFAULT 1,
  `default_value` varchar(255) NOT NULL DEFAULT '',
  `comment` varchar(255) NOT NULL DEFAULT '',
  `unsigned` tinyint(1) NOT NULL DEFAULT 0,
  `index_type` varchar(10) NOT NULL DEFAULT 'none',
  `placeholder` varchar(100) NOT NULL DEFAULT '',
  `options_source` json DEFAULT NULL,
  `control_props` json DEFAULT NULL,
  `validate_rules` json DEFAULT NULL,
  `link_rules` json DEFAULT NULL,
  `relation_type` varchar(20) NOT NULL DEFAULT 'none',
  `relation_table` varchar(100) NOT NULL DEFAULT '',
  `relation_label_field` varchar(64) NOT NULL DEFAULT '',
  `relation_value_field` varchar(64) NOT NULL DEFAULT 'id',
  `relation_multiple` tinyint(1) NOT NULL DEFAULT 0,
  `relation_on_delete` varchar(20) NOT NULL DEFAULT 'restrict',
  `list_show` tinyint(1) NOT NULL DEFAULT 1,
  `list_sort` tinyint(1) NOT NULL DEFAULT 0,
  `list_filter` varchar(20) NOT NULL DEFAULT '',
  `list_formatter` varchar(30) NOT NULL DEFAULT '',
  `list_width` int NOT NULL DEFAULT 0,
  `form_show` tinyint(1) NOT NULL DEFAULT 1,
  `form_required` tinyint(1) NOT NULL DEFAULT 0,
  `form_group` varchar(50) NOT NULL DEFAULT '',
  `form_span` int NOT NULL DEFAULT 24,
  `form_readonly` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_field_form_name` (`form_id`,`field_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_form_schema_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint unsigned NOT NULL,
  `version` int unsigned NOT NULL,
  `schema_version` int unsigned NOT NULL DEFAULT 2,
  `schema_hash` char(64) NOT NULL,
  `schema_document` json NOT NULL,
  `origin` varchar(20) NOT NULL DEFAULT 'designer',
  `parent_version_id` bigint unsigned NULL,
  `change_summary` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_form_schema_version` (`form_id`,`version`),
  UNIQUE KEY `uk_form_schema_hash` (`form_id`,`schema_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_business_module` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `form_id` bigint unsigned NULL,
  `origin` varchar(20) NOT NULL DEFAULT 'visual',
  `connection_name` varchar(64) NOT NULL DEFAULT 'mysql',
  `table_name` varchar(190) NOT NULL DEFAULT '',
  `runtime_route` varchar(255) NOT NULL DEFAULT '',
  `module_route` varchar(255) NOT NULL DEFAULT '',
  `lifecycle_status` varchar(32) NOT NULL DEFAULT 'draft',
  `published_schema_hash` char(64) NULL,
  `published_schema_version` int unsigned NULL,
  `generation_status` varchar(32) NOT NULL DEFAULT 'idle',
  `metadata` json DEFAULT NULL,
  `created_at` datetime NULL,
  `updated_at` datetime NULL,
  `deleted_at` datetime NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_business_module_code` (`code`),
  UNIQUE KEY `uk_business_module_form` (`form_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fun_orders` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `title` varchar(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);

    $isolatedConfig = $originalDatabaseConfig;
    $isolatedConfig['connections']['mysql']['database'] = $temporaryDatabase;
    $app->config->set($isolatedConfig, 'database');
    Db::connect('mysql', true);

    $database->exec('CREATE TABLE `fun_dynamic_parent` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $productionForms = new FormDesignerService($root, $schemas);
    foreach (['restrict', 'cascade'] as $onDelete) {
        $relationDefinition = [
            'form_key' => 'dynamic_' . $onDelete,
            'name' => '动态外键 ' . $onDelete,
            'table_name' => 'fun_dynamic_' . $onDelete,
            'connection' => 'mysql',
            'source_type' => 'created',
            'fields' => [[
                'field_name' => 'parent_id',
                'label' => '父记录',
                'type' => 'relation',
                'column_type' => 'bigint',
                'unsigned' => 1,
                'nullable' => 1,
                'relation_type' => 'belongs_to',
                'relation_table' => 'fun_dynamic_parent',
                'relation_label_field' => 'id',
                'relation_value_field' => 'id',
                'relation_on_delete' => $onDelete,
            ]],
        ];
        $relationPreview = $productionForms->previewMigration($relationDefinition);
        dynamicPublishExpect(
            str_contains($relationPreview['sql'], 'FOREIGN KEY (`parent_id`) REFERENCES `fun_dynamic_parent` (`id`) ON DELETE ' . strtoupper($onDelete)),
            'created Schema 必须生成 belongs_to 外键及 ON DELETE ' . strtoupper($onDelete)
        );
        $relationDefinition['dynamic_ddl_hash'] = hash('sha256', $relationPreview['sql']);
        $relationDdl = $productionForms->applyDynamicDdl($relationDefinition);
        dynamicPublishExpect($relationDdl['applied'] === true, '动态 DDL 必须允许外键 ON DELETE ' . strtoupper($onDelete));
    }

    $assertDynamicForwardSql = new ReflectionMethod(FormDesignerService::class, 'assertDynamicForwardSql');
    $assertDynamicForwardSql->setAccessible(true);
    foreach ([
        'DELETE FROM `fun_dynamic_restrict`',
        'DROP TABLE `fun_dynamic_restrict`',
        'CREATE TABLE IF NOT EXISTS `fun_dynamic_extra` (`id` bigint); DELETE FROM `fun_dynamic_restrict`',
        'ALTER TABLE `fun_dynamic_restrict` CHANGE `parent_id` `owner_id` bigint unsigned',
        'ALTER TABLE `fun_dynamic_restrict` MODIFY `parent_id` bigint unsigned NOT NULL',
    ] as $destructiveSql) {
        try {
            $assertDynamicForwardSql->invoke($productionForms, $destructiveSql);
            dynamicPublishExpect(false, '动态 DDL 必须拒绝破坏性 SQL：' . $destructiveSql);
        } catch (ReflectionException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            dynamicPublishExpect($exception instanceof InvalidArgumentException, '破坏性 SQL 必须由 forward-only 守卫拒绝');
        }
    }

    $productionService = new FormPublishService($productionForms, $schemas);
    $createdSchema = dynamicPublishSchema('created');
    $createdSchema['key'] = 'dynamic_probe';
    $createdSchema['title'] = '动态探针';
    $createdSchema['database']['table'] = 'fun_dynamic_probe';
    $createdHash = $schemas->compile($createdSchema)->hash();
    $generatedBeforeProbe = glob($root . 'database/generated/*') ?: [];
    $createdResult = $productionService->publishDynamic([
        'schema_document' => $createdSchema,
        'schemaHash' => $createdHash,
        'schema_actor' => 'integration-tester',
    ], 'integration-tester');
    $createdFormId = (int) $createdResult['form']['id'];
    dynamicPublishExpect(in_array('fun_dynamic_probe', Db::connect()->getTables(), true), '真实生产动态 DDL 探针必须创建业务表');
    dynamicPublishExpect(FormSchemaVersion::where('form_id', $createdFormId)->where('schema_hash', $createdHash)->count() === 1, '真实 created 发布必须保存不可变 FormSchemaVersion');
    dynamicPublishExpect((glob($root . 'database/generated/*') ?: []) === $generatedBeforeProbe, '真实 created 动态发布不得写 database/generated');

    $productionSchema = dynamicPublishSchema('adopted');
    $productionHash = $schemas->compile($productionSchema)->hash();
    try {
        $productionResult = $productionService->publishDynamic([
            'schema_document' => $productionSchema,
            'schemaHash' => $productionHash,
            'schema_actor' => 'integration-tester',
        ], 'integration-tester');
    } catch (Throwable $exception) {
        throw new RuntimeException($exception->getMessage() . "\n" . $exception->getTraceAsString(), 0, $exception);
    }
    $productionFormId = (int) $productionResult['form']['id'];
    dynamicPublishExpect($productionFormId > 0, '生产 publishDynamic 必须创建 Form');
    dynamicPublishExpect(FormSchemaVersion::where('form_id', $productionFormId)->where('schema_hash', $productionHash)->count() === 1, 'saveForm 必须在元数据发布前保存不可变 FormSchemaVersion');
    dynamicPublishExpect((string) Form::find($productionFormId)->publish_status === 'published', '生产 publishDynamic 必须原子完成 Form 发布状态');
    dynamicPublishExpect((string) BusinessModule::where('form_id', $productionFormId)->value('published_schema_hash') === $productionHash, '生产 publishDynamic 必须发布同一不可变 Schema hash');
    dynamicPublishExpect((string) BusinessModule::where('form_id', $productionFormId)->value('runtime_route') === '/development/business/runtime/orders', 'BusinessModule 运行时路径必须与隐藏宿主精确一致');

    $persistMetadata = new ReflectionMethod(FormPublishService::class, 'persistPublishedMetadata');
    $persistMetadata->setAccessible(true);
    $compiled = $schemas->compile($productionSchema);
    $saved = (new FormDesignerService($root, $schemas))->detail($productionFormId);
    $module = BusinessModule::where('form_id', $productionFormId)->find();
    $oldHash = str_repeat('a', 64);
    $module->save(['published_schema_hash' => $oldHash, 'published_schema_version' => 7, 'lifecycle_status' => 'published']);
    Form::where('id', $productionFormId)->update(['business_module_id' => (int) $module->id, 'published_schema_hash' => $oldHash, 'publish_status' => 'published']);
    $database->exec("CREATE TRIGGER fail_final_form_update BEFORE UPDATE ON fun_form FOR EACH ROW BEGIN IF NEW.published_schema_hash='{$productionHash}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='injected final form failure'; END IF; END");
    try {
        $persistMetadata->invoke($productionService, $saved, $compiled, 'integration-tester', ['exists' => true, 'hash' => $oldHash, 'version' => 7]);
        dynamicPublishExpect(false, 'Form 最终更新失败必须抛出并回滚 BusinessModule');
    } catch (Throwable $exception) {
        dynamicPublishExpect(str_contains($exception->getMessage(), 'injected final form failure'), '必须命中真实数据库 Form 最终更新失败注入');
    }
    $database->exec('DROP TRIGGER fail_final_form_update');
    $rolledBackModule = BusinessModule::find((int) $module->id);
    $rolledBackForm = Form::find($productionFormId);
    dynamicPublishExpect((string) $rolledBackModule->published_schema_hash === $oldHash && (int) $rolledBackModule->published_schema_version === 7, 'Form 最终更新失败必须回滚同事务 BusinessModule');
    dynamicPublishExpect((string) $rolledBackForm->published_schema_hash === $oldHash && (int) $rolledBackForm->business_module_id === (int) $module->id, 'Form 最终更新失败不得留下半切换元数据');

    $latestHash = str_repeat('b', 64);
    $module->save(['published_schema_hash' => $latestHash, 'published_schema_version' => 8, 'lifecycle_status' => 'published']);
    try {
        $persistMetadata->invoke($productionService, $saved, $compiled, 'stale-publisher', ['exists' => true, 'hash' => $oldHash, 'version' => 7]);
        dynamicPublishExpect(false, '两个 baseline 不一致必须稳定冲突');
    } catch (InvalidArgumentException $exception) {
        dynamicPublishExpect($exception->getMessage() === 'FORM_SCHEMA_CONFLICT', '真实数据库 CAS 必须返回稳定 FORM_SCHEMA_CONFLICT');
    }
    $module->refresh();
    dynamicPublishExpect((string) $module->published_schema_hash === $latestHash && (int) $module->published_schema_version === 8, 'CAS 冲突不得覆盖最新 BusinessModule');

    $conflictSchema = $productionSchema;
    $conflictSchema['title'] = '并发草稿';
    $conflictHash = $schemas->compile($conflictSchema)->hash();
    $newestHash = str_repeat('c', 64);
    $conflictService = new FormPublishService(
        new FormDesignerService($root, $schemas),
        $schemas,
        null,
        null,
        null,
        static function () use ($productionFormId, $module, $newestHash): void {
            BusinessModule::where('id', (int) $module->id)->update([
                'published_schema_hash' => $newestHash,
                'published_schema_version' => 9,
                'lifecycle_status' => 'dynamic_published',
            ]);
            Form::where('id', $productionFormId)->update([
                'published_schema_hash' => $newestHash,
                'publish_status' => 'published',
            ]);
        }
    );
    try {
        $conflictService->publishDynamic([
            'id' => $productionFormId,
            'schema_document' => $conflictSchema,
            'schemaHash' => $conflictHash,
            'expected_schema_hash' => $productionHash,
        ], 'stale-publisher');
        dynamicPublishExpect(false, '并发发布基线变化必须冲突');
    } catch (InvalidArgumentException $exception) {
        dynamicPublishExpect($exception->getMessage() === 'FORM_SCHEMA_CONFLICT', '并发发布必须保持稳定冲突语义');
    }
    $conflictedForm = Form::find($productionFormId);
    dynamicPublishExpect((string) $conflictedForm->publish_status === 'published' && (string) $conflictedForm->published_schema_hash === $newestHash, 'CAS 冲突后绝不能把新发布 Form 改回 publishing 或 partial');

    $createOrReload = new ReflectionMethod(FormPublishService::class, 'createOrReloadBusinessModule');
    $createOrReload->setAccessible(true);
    try {
        $createOrReload->invoke($productionService, $productionFormId, 'orders', ['exists' => false, 'hash' => '', 'version' => 0]);
        dynamicPublishExpect(false, '新模块并发唯一冲突不得覆盖已创建模块');
    } catch (InvalidArgumentException $exception) {
        dynamicPublishExpect($exception->getMessage() === 'FORM_SCHEMA_CONFLICT', '新模块唯一冲突必须安全重读并返回稳定冲突');
    }
    dynamicPublishExpect((string) BusinessModule::find((int) $module->id)->published_schema_hash === $newestHash, '新模块唯一冲突安全重读不得覆盖竞争者数据');

    $readBaseline = new ReflectionMethod(FormPublishService::class, 'publishedBaseline');
    $readBaseline->setAccessible(true);
    $persistStatus = new ReflectionMethod(FormPublishService::class, 'persistStatus');
    $persistStatus->setAccessible(true);
    $staleAttemptBaseline = $readBaseline->invoke($productionService, $productionFormId, 'orders');
    $successfulSchema = $conflictSchema;
    $successfulSchema['title'] = '并发成功发布';
    $successfulHash = $schemas->compile($successfulSchema)->hash();
    $successfulResult = $productionService->publishDynamic([
        'id' => $productionFormId,
        'schema_document' => $successfulSchema,
        'schemaHash' => $successfulHash,
        'expected_schema_hash' => $conflictHash,
    ], 'newer-publisher');
    dynamicPublishExpect($successfulResult['publishStatus'] === 'dynamic_published', 'A 必须真实成功发布新 hash');
    foreach (['ddl_pending', 'publishing', 'ddl_failed', 'metadata_partial', 'validation_failed'] as $staleStatus) {
        $affected = $persistStatus->invoke($productionService, $productionFormId, $staleStatus, [], $staleAttemptBaseline);
        dynamicPublishExpect($affected === 0, 'B 持旧 baseline 写入 ' . $staleStatus . ' 必须影响 0 行');
        $publishedForm = Form::find($productionFormId);
        dynamicPublishExpect(
            (string) $publishedForm->publish_status === 'published'
                && (string) $publishedForm->published_schema_hash === $successfulHash,
            'B 的旧中间或失败状态不得覆盖 A 的 published/hash'
        );
    }

    $editedSchema = $successfulSchema;
    $editedSchema['title'] = '正常编辑后发布';
    $editedHash = $schemas->compile($editedSchema)->hash();
    $editedResult = $productionService->publishDynamic([
        'id' => $productionFormId,
        'schema_document' => $editedSchema,
        'schema_hash' => $successfulHash,
        'schemaHash' => $editedHash,
        'schema_actor' => 'integration-tester',
    ], 'integration-tester');
    dynamicPublishExpect($editedResult['publishStatus'] === 'dynamic_published', '正常编辑请求必须用旧 schema_hash 作为 expected baseline 并成功发布');
    $editedForm = Form::find($productionFormId);
    dynamicPublishExpect((string) $editedForm->schema_hash === $editedHash, '正常编辑发布必须保存新的 canonical schema hash');

    $staleEditedSchema = $editedSchema;
    $staleEditedSchema['title'] = '错误旧基线';
    $staleEditedHash = $schemas->compile($staleEditedSchema)->hash();
    try {
        $productionService->publishDynamic([
            'id' => $productionFormId,
            'schema_document' => $staleEditedSchema,
            'schema_hash' => $successfulHash,
            'schemaHash' => $staleEditedHash,
            'schema_actor' => 'stale-integration-tester',
        ], 'stale-integration-tester');
        dynamicPublishExpect(false, '错误旧 schema_hash baseline 必须冲突');
    } catch (InvalidArgumentException $exception) {
        dynamicPublishExpect(str_contains($exception->getMessage(), '修改'), '错误旧 schema_hash baseline 必须返回乐观锁冲突');
    }
    dynamicPublishExpect((string) Form::find($productionFormId)->schema_hash === $editedHash, '旧 baseline 冲突不得覆盖当前 schema hash');
} finally {
    $database = null;
    $app->config->set($originalDatabaseConfig, 'database');
    Db::connect('mysql', true);
    if (str_starts_with($temporaryDatabase, 'funadmin_dynamic_publish_')) {
        $server->exec('DROP DATABASE IF EXISTS ' . $identifier($temporaryDatabase));
    }
}

echo "business dynamic publish tests: PASS\n";
