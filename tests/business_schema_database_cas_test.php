<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\crud\CrudDefinition;
use app\common\crud\SchemaInspector;
use app\common\form\registry\FieldCapabilityRegistry;
use app\console\service\BusinessDevelopmentService;
use app\console\service\BusinessModuleService;
use app\console\service\DevCrudService;
use app\console\service\FormDataService;
use app\console\service\FormDesignerService;
use app\console\service\FormPublishService;
use app\console\service\FormSchemaRepository;
use app\console\service\ManagedGenerationService;

function businessCasExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function businessCasSchema(string $title): array
{
    return [
        'table' => 'fun_orders',
        'comment' => $title,
        'columns' => [[
            'name' => 'id',
            'type' => $title === '新结构' ? 'int unsigned' : 'bigint unsigned',
            'nullable' => false,
            'default' => null,
            'extra' => 'auto_increment',
            'comment' => '',
            'position' => 1,
            'primary' => true,
        ]],
        'primaryKey' => ['id'],
        'pivot' => false,
        'indexes' => [['name' => 'PRIMARY', 'unique' => true, 'columns' => ['id']]],
        'uniqueIndexes' => [],
        'foreignKeys' => [],
    ];
}

function businessCasService(string $root, callable $query): BusinessDevelopmentService
{
    $schemas = new FormSchemaRepository();
    $forms = new FormDesignerService($root, $schemas);
    $crud = new DevCrudService(
        $root,
        ['mysql'],
        static fn (string $connection): SchemaInspector => new SchemaInspector($query)
    );
    return new BusinessDevelopmentService(
        new BusinessModuleService(),
        $forms,
        $schemas,
        new FormPublishService($forms, $schemas),
        new ManagedGenerationService($root, schemas: $schemas),
        new FormDataService(),
        $crud,
        new FieldCapabilityRegistry()
    );
}

function businessCasQuery(array $schemas): callable
{
    $inspection = 0;
    return static function (string $sql, array $bindings) use ($schemas, &$inspection): array {
        $schema = $schemas[min($inspection, count($schemas) - 1)];
        if (str_contains($sql, 'information_schema.TABLES')) return [['TABLE_NAME' => $bindings[0], 'TABLE_COMMENT' => $schema['comment']]];
        if (str_contains($sql, 'information_schema.COLUMNS')) return array_map(static fn (array $column): array => [
            'COLUMN_NAME' => $column['name'],
            'COLUMN_TYPE' => $column['type'],
            'IS_NULLABLE' => $column['nullable'] ? 'YES' : 'NO',
            'COLUMN_DEFAULT' => $column['default'],
            'EXTRA' => $column['extra'],
            'COLUMN_COMMENT' => $column['comment'],
            'ORDINAL_POSITION' => $column['position'],
        ], $schema['columns']);
        if (str_contains($sql, 'information_schema.STATISTICS')) {
            $rows = [];
            foreach ($schema['indexes'] as $index) {
                foreach ($index['columns'] as $position => $column) {
                    $rows[] = ['INDEX_NAME' => $index['name'], 'NON_UNIQUE' => $index['unique'] ? 0 : 1, 'SEQ_IN_INDEX' => $position + 1, 'COLUMN_NAME' => $column];
                }
            }
            $inspection++;
            return $rows;
        }
        return [];
    };
}

$root = dirname(__DIR__) . '/';
$service = businessCasService($root, businessCasQuery([businessCasSchema('订单')]));
$inspection = $service->inspectDatabase('mysql', 'fun_orders');
businessCasExpect(array_keys($inspection) === ['connection', 'table', 'snapshotHash', 'observedAt', 'fields', 'primaryKey', 'indexes'], '数据库检查响应必须仅返回稳定契约字段');
businessCasExpect($inspection['connection'] === 'mysql' && $inspection['table'] === 'fun_orders', '数据库检查必须绑定连接与表');
businessCasExpect((bool) preg_match('/^[a-f0-9]{64}$/', $inspection['snapshotHash']), '数据库检查必须返回 SHA-256 snapshotHash');
businessCasExpect($inspection['primaryKey'] === ['id'] && $inspection['indexes'][0]['name'] === 'PRIMARY', '数据库检查必须返回主键与索引');
businessCasExpect($inspection['fields'][0]['name'] === 'id', '数据库检查必须返回服务端推断字段');
$canonical = [
    'connection' => $inspection['connection'],
    'table' => $inspection['table'],
    'fields' => $inspection['fields'],
    'primaryKey' => $inspection['primaryKey'],
    'indexes' => $inspection['indexes'],
];
businessCasExpect($inspection['snapshotHash'] === hash('sha256', CrudDefinition::canonicalJson($canonical)), 'snapshotHash 必须由 canonical inspection 内容计算');

try {
    $service->createFromDatabase(['connection' => 'mysql', 'table' => 'fun_orders', 'code' => 'orders', 'name' => '订单'], 'tester');
    businessCasExpect(false, '数据库创建必须要求 expectedInspectionHash');
} catch (InvalidArgumentException $exception) {
    businessCasExpect($exception->getMessage() === 'hash 不合法', '缺少 expectedInspectionHash 必须在产生写入前拒绝');
}

$changingService = businessCasService($root, businessCasQuery([businessCasSchema('旧结构'), businessCasSchema('新结构')]));
$oldInspection = $changingService->inspectDatabase('mysql', 'fun_orders');
try {
    $changingService->createFromDatabase([
        'connection' => 'mysql',
        'table' => 'fun_orders',
        'code' => 'orders',
        'name' => '订单',
        'expectedInspectionHash' => $oldInspection['snapshotHash'],
        'fields' => [['name' => 'client_injected']],
    ], 'tester');
    businessCasExpect(false, '结构变化后创建必须被拒绝');
} catch (InvalidArgumentException $exception) {
    businessCasExpect($exception->getMessage() === 'DATABASE_INSPECTION_STALE', '结构变化必须返回稳定 DATABASE_INSPECTION_STALE');
}

$repositorySource = (string) file_get_contents($root . 'app/console/service/FormSchemaRepository.php');
$developmentSource = (string) file_get_contents($root . 'app/console/service/BusinessDevelopmentService.php');
businessCasExpect(str_contains($repositorySource, 'public function rollbackIfCurrentHash('), '版本仓库必须提供 rollbackIfCurrentHash');
businessCasExpect(str_contains($repositorySource, 'Form::lock(true)') && str_contains($repositorySource, "InvalidArgumentException('FORM_SCHEMA_CONFLICT')"), '回滚 CAS 必须在事务行锁内比较当前 canonical hash');
businessCasExpect(str_contains($developmentSource, 'rollbackIfCurrentHash('), '业务回滚必须接入仓储 CAS');
businessCasExpect(!str_contains($developmentSource, 'return $this->schemas->rollback($formId,'), '业务回滚不得绕过 expectedSchemaHash');
businessCasExpect(str_contains($developmentSource, "\$input['expectedInspectionHash']") && str_contains($developmentSource, 'hash_equals('), '数据库创建必须校验 expectedInspectionHash');
businessCasExpect(!str_contains($developmentSource, "(array) (\$input['fields']"), '数据库创建禁止消费客户端 fields');

echo "business schema/database CAS tests: PASS\n";
