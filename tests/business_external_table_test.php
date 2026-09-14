<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\sdk\ExternalTableRequirements;
use app\common\crud\CrudDefinition;

function externalExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
externalExpect(class_exists(ExternalTableRequirements::class), '缺少外部表声明与生命周期校验');
$definition = CrudDefinition::fromArray([
    'entity' => 'items', 'table' => 'fun_legacy', 'connection' => 'mysql', 'primaryKey' => 'record_id',
    'fields' => [
        ['name' => 'record_id', 'dbType' => 'bigint unsigned', 'nullable' => false, 'primary' => true],
        ['name' => 'title', 'dbType' => 'varchar(255)', 'nullable' => false],
    ],
]);
$requirement = ExternalTableRequirements::fromDefinition($definition);
externalExpect($requirement['table'] === 'fun_legacy' && !isset($requirement['owned']), '外部声明不得授予所有权');
$reads = 0;
$schema = ['primaryKey' => ['record_id'], 'columns' => [
    ['name' => 'record_id', 'type' => 'bigint unsigned', 'nullable' => false],
    ['name' => 'title', 'type' => 'varchar(255)', 'nullable' => false],
]];
$guard = new ExternalTableRequirements('mysql', static function (string $connection, string $table) use (&$reads, &$schema): array {
    $reads++;
    externalExpect($connection === 'mysql' && $table === 'fun_legacy', '只读可信表');
    return $schema;
});
$guard->assertCompatible([$requirement]);
externalExpect($reads === 1, '生命周期必须实际读取结构');
foreach (['missing', 'type', 'nullable', 'primary'] as $case) {
    $bad = $schema;
    if ($case === 'missing') $schema['columns'] = [];
    if ($case === 'type') $schema['columns'][1]['type'] = 'int';
    if ($case === 'nullable') $schema['columns'][1]['nullable'] = true;
    if ($case === 'primary') $schema['primaryKey'] = ['title'];
    try { $guard->assertCompatible([$requirement]); throw new RuntimeException('不兼容结构必须阻断'); }
    catch (InvalidArgumentException $exception) { externalExpect(str_contains($exception->getMessage(), 'EXTERNAL_TABLE'), $exception->getMessage()); }
    $schema = $bad;
}
$source = (string) file_get_contents(dirname(__DIR__) . '/app/console/plugin/service/PluginService.php');
externalExpect(substr_count($source, 'assertExternalTables($manifest)') >= 2, '安装更新必须在迁移之前接通校验');
$targetSource = (string) file_get_contents(dirname(__DIR__) . '/app/common/crud/PluginCrudTarget.php');
externalExpect(str_contains($targetSource, '!$definition->isAdopted()'), '外部表制品必须按统一采纳身份禁止迁移');
foreach ([['tableIdentity' => ['source' => 'adopted', 'kind' => 'physical']], ['formSchema' => ['database' => ['source' => 'adopted']]]] as $identity) {
    externalExpect(CrudDefinition::fromArray($identity)->isAdopted(), 'CLI 与表单采纳来源必须统一识别');
}
foreach (['migratePlugin', 'setPluginEnabled', 'purgePluginData'] as $method) {
    $reflection = new ReflectionMethod(\app\console\plugin\service\PluginService::class, $method);
    $body = implode('', array_slice(file($reflection->getFileName()), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
    externalExpect(str_contains($body, $method === 'purgePluginData' ? 'assertPurgeAllowed' : 'assertExternalTables'), $method . ' 必须在执行边界检查外部表');
}
$purgeRoot = dirname(__DIR__) . '/runtime/external-purge-test-' . bin2hex(random_bytes(6));
mkdir($purgeRoot, 0700, true);
$lock = new \app\common\plugin\sdk\LifecycleLock($purgeRoot);
$called = false;
$validatedUnderLock = false;
$coordinator = new \app\common\plugin\sdk\PluginPurgeCoordinator(
    function () use (&$called): object { $called = true; return new stdClass(); }, fn (array $audit) => null, $lock,
    function (string $code) use ($lock, &$validatedUnderLock): bool {
        try { $lock->acquire($code); }
        catch (RuntimeException $error) { $validatedUnderLock = true; }
        throw new InvalidArgumentException('EXTERNAL_TABLE_PURGE_FORBIDDEN');
    });
try { $coordinator->purge('sample', 'sample'); throw new RuntimeException('外部表 purge 必须拒绝'); }
catch (InvalidArgumentException $error) { externalExpect($error->getMessage() === 'EXTERNAL_TABLE_PURGE_FORBIDDEN', 'purge 错误'); }
externalExpect($validatedUnderLock && !$called, 'purge 必须持锁校验，拒绝后不实例化插件或调用钩子');
echo "business external table tests: PASS\n";
