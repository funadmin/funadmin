<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use app\console\model\CrudGeneration;
use app\console\service\DatabaseGenerationStateRepository;
use think\App;
use think\facade\Db;

function canonicalStateExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function canonicalStateIdentifier(string $identifier): string
{
    canonicalStateExpect(preg_match('/^[a-z0-9_]+$/', $identifier) === 1, '数据库标识符不安全');
    return '`' . $identifier . '`';
}

$root = dirname(__DIR__);
$migrationName = '088_generation_canonical_state.sql';
$migrationPath = $root . '/database/migrations/' . $migrationName;
$migrations = array_map('basename', glob($root . '/database/migrations/*.sql') ?: []);
$numbered = array_values(array_filter($migrations, static fn (string $name): bool => str_starts_with($name, '088_')));
canonicalStateExpect($numbered === [$migrationName], '088 migration 必须唯一且不得修改 086/087');

$migration = (string) file_get_contents($migrationPath);
foreach (['operation_key', 'superseded_by_id', 'result', 'failure_code', 'actor', 'started_at', 'completed_at', 'failed_at', 'recovered_at'] as $field) {
    canonicalStateExpect(str_contains($migration, "COLUMN_NAME='{$field}'"), 'migration 缺少字段：' . $field);
}
foreach (['idx_crud_generation_operation_key', 'idx_crud_generation_status_recovery', 'idx_crud_generation_superseded_by'] as $index) {
    canonicalStateExpect(str_contains($migration, "INDEX_NAME='{$index}'"), 'migration 缺少索引：' . $index);
}
canonicalStateExpect(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|RENAME)\b/i', preg_replace('/^\s*--.*$/m', '', $migration)), 'migration 必须 forward-only');
foreach (['planned', 'running', 'completed', 'failed', 'conflict', 'superseded'] as $status) {
    canonicalStateExpect(str_contains($migration, "'{$status}'"), 'migration 缺少 canonical generation 状态：' . $status);
}
foreach (['none', 'recovering', 'rolled_back', 'recovered_completed', 'recovery_required'] as $status) {
    canonicalStateExpect(str_contains($migration, "'{$status}'"), 'migration 缺少 canonical recovery 状态：' . $status);
}

$model = (new ReflectionClass(CrudGeneration::class))->newInstanceWithoutConstructor();
$property = new ReflectionProperty(CrudGeneration::class, 'json');
$property->setAccessible(true);
canonicalStateExpect(in_array('result', (array) $property->getValue($model), true), 'CrudGeneration.result 必须按 JSON 转换');

if (!extension_loaded('pdo_mysql')) {
    throw new RuntimeException('canonical state 测试必须在带 pdo_mysql 的 PHP 8.1 Docker 中执行');
}

$app = new App($root . '/');
$app->initialize();
$originalConfig = (array) config('database');
$originalConfig['connections']['mysql']['hostname'] = (string) (getenv('BUSINESS_GENERATION_TEST_DB_HOST') ?: $originalConfig['connections']['mysql']['hostname']);
$originalConfig['connections']['mysql']['database'] = (string) (getenv('BUSINESS_GENERATION_TEST_DB_NAME') ?: $originalConfig['connections']['mysql']['database']);
$originalConfig['connections']['mysql']['username'] = (string) (getenv('BUSINESS_GENERATION_TEST_DB_USER') ?: $originalConfig['connections']['mysql']['username']);
$originalConfig['connections']['mysql']['password'] = (string) (getenv('BUSINESS_GENERATION_TEST_DB_PASS') ?: $originalConfig['connections']['mysql']['password']);
$databaseName = 'funadmin_generation_state_' . bin2hex(random_bytes(6));
$serverConfig = $originalConfig;
$serverConfig['connections']['mysql']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('mysql', true);

try {
    $server->execute('CREATE DATABASE ' . canonicalStateIdentifier($databaseName) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $isolatedConfig = $originalConfig;
    $isolatedConfig['connections']['mysql']['database'] = $databaseName;
    $app->config->set($isolatedConfig, 'database');
    Db::connect('mysql', true);
    Db::execute(<<<'SQL'
CREATE TABLE `fun_business_module` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `form_id` bigint unsigned NULL, `module_route` varchar(255) NOT NULL DEFAULT '',
  `lifecycle_status` varchar(32) NOT NULL DEFAULT 'draft', `published_schema_hash` char(64) NULL,
  `published_schema_version` int unsigned NULL, `current_generation_id` bigint unsigned NULL,
  `last_success_generation_id` bigint unsigned NULL, `generation_status` varchar(32) NOT NULL DEFAULT 'idle',
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL, PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);
    Db::execute(<<<'SQL'
CREATE TABLE `fun_form` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `publish_mode` varchar(20) NOT NULL DEFAULT 'dynamic',
  `publish_status` varchar(20) NOT NULL DEFAULT 'draft', `published_at` datetime NULL, `crud_generation_id` bigint unsigned NULL,
  `published_definition_hash` char(64) NULL, `published_schema_hash` char(64) NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL, PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);
    Db::execute(<<<'SQL'
CREATE TABLE `fun_crud_generation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `business_module_id` bigint unsigned NULL, `form_id` bigint unsigned NULL,
  `operation` varchar(20) NOT NULL, `status` varchar(32) NOT NULL, `recovery_status` varchar(32) NOT NULL DEFAULT 'none',
  `transaction_id` varchar(64) NULL, `plan_digest` char(64) NULL, `connection_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(190) NOT NULL DEFAULT '', `definition_hash` char(64) NOT NULL, `definition` json NULL,
  `manifest` json NULL, `error` json NULL, `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);
    Db::execute(<<<'SQL'
CREATE TABLE `fun_generated_file_baseline` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `business_module_id` bigint unsigned NOT NULL,
  `relative_path` varchar(512) NOT NULL, `artifact_type` varchar(64) NOT NULL DEFAULT '', `base_hash` char(64) NOT NULL,
  `base_storage_path` varchar(512) NOT NULL, `target_hash` char(64) NULL, `template_version` varchar(64) NOT NULL DEFAULT '',
  `definition_hash` char(64) NOT NULL, `generation_id` bigint unsigned NULL, `content_kind` varchar(32) NOT NULL DEFAULT 'text',
  `status` varchar(32) NOT NULL DEFAULT 'active', `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_generated_baseline_module_path` (`business_module_id`,`relative_path`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL);

    $migrationService = new MigrationService();
    $statements = new ReflectionMethod($migrationService, 'statements');
    $statements->setAccessible(true);
    foreach (range(1, 2) as $_) {
        foreach ($statements->invoke($migrationService, $migration) as $statement) {
            Db::execute($statement);
        }
    }

    $columns = array_column(Db::query("SHOW COLUMNS FROM `fun_crud_generation`"), 'Field');
    foreach (['operation_key', 'superseded_by_id', 'result', 'failure_code', 'actor', 'started_at', 'completed_at', 'failed_at', 'recovered_at'] as $field) {
        canonicalStateExpect(in_array($field, $columns, true), '真实 migration 未创建字段：' . $field);
    }

    $now = date('Y-m-d H:i:s');
    Db::name('form')->insert(['id' => 10, 'publish_status' => 'draft', 'created_at' => $now, 'updated_at' => $now]);
    Db::name('business_module')->insert(['id' => 20, 'form_id' => 10, 'lifecycle_status' => 'draft', 'generation_status' => 'idle', 'created_at' => $now, 'updated_at' => $now]);
    $definitionHash = str_repeat('a', 64);
    $schemaHash = str_repeat('b', 64);
    $generationId = (int) Db::name('crud_generation')->insertGetId([
        'business_module_id' => 20, 'form_id' => 10, 'operation' => 'generate', 'operation_key' => 'module:20:publish',
        'status' => 'planned', 'recovery_status' => 'none', 'definition_hash' => $definitionHash,
        'definition' => json_encode(['routePath' => '/generated/orders', 'formSchemaHash' => $schemaHash, 'formSchemaVersion' => 2], JSON_THROW_ON_ERROR),
        'manifest' => json_encode([], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now,
    ]);
    $repository = new DatabaseGenerationStateRepository();
    $repository->markRunning($generationId, 'tester');
    $running = CrudGeneration::find($generationId);
    canonicalStateExpect($running->status === 'running' && $running->actor === 'tester' && $running->started_at !== null, 'markRunning 状态错误');

    $repository->transaction(fn () => $repository->commitGeneration(20, $generationId, [], 'tx-1', str_repeat('c', 64)));
    $completed = CrudGeneration::find($generationId);
    $result = $repository->completedResult($generationId);
    canonicalStateExpect($completed->status === 'completed' && $completed->completed_at !== null, 'commitGeneration 未完成 generation');
    canonicalStateExpect($result['routePath'] === '/generated/orders' && $result['generationId'] === $generationId, 'completedResult 未返回可信结果');
    canonicalStateExpect(($result['state'] ?? '') === 'completed' && ($result['resourceApplyStatus'] ?? '') === 'applied', '持久化成功结果必须满足前端正式生成契约');
    $module = Db::name('business_module')->where('id', 20)->find();
    canonicalStateExpect($module['lifecycle_status'] === 'published' && $module['generation_status'] === 'completed', '模块 canonical 发布状态错误');
    canonicalStateExpect($module['module_route'] === '/generated/orders' && (int) $module['current_generation_id'] === $generationId && (int) $module['last_success_generation_id'] === $generationId, '模块 route/generation 指针错误');
    $form = Db::name('form')->where('id', 10)->find();
    canonicalStateExpect($form['publish_mode'] === 'generated' && $form['publish_status'] === 'published', 'Form 正式发布状态错误');
    canonicalStateExpect((int) $form['crud_generation_id'] === $generationId && $form['published_definition_hash'] === $definitionHash && $form['published_schema_hash'] === $schemaHash, 'Form 发布关联字段错误');

    $cases = [
        ['markFailed', ['E_WRITE', ['message' => 'failed'], 'tester'], 'failed', 'none'],
        ['markConflict', ['E_CONFLICT', ['message' => 'conflict'], 'tester'], 'conflict', 'none'],
        ['markRecovering', ['tester'], 'running', 'recovering'],
        ['markRolledBack', ['tester'], 'failed', 'rolled_back'],
        ['markRecoveryRequired', ['E_RECOVERY', ['message' => 'manual'], 'tester'], 'failed', 'recovery_required'],
    ];
    foreach ($cases as [$method, $arguments, $expectedStatus, $expectedRecovery]) {
        $id = (int) Db::name('crud_generation')->insertGetId([
            'operation' => 'generate', 'status' => 'planned', 'recovery_status' => 'none', 'definition_hash' => str_repeat('d', 64),
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $repository->{$method}($id, ...$arguments);
        $row = CrudGeneration::find($id);
        canonicalStateExpect($row->status === $expectedStatus && $row->recovery_status === $expectedRecovery, $method . ' 状态错误');
    }
    $supersededId = (int) Db::name('crud_generation')->insertGetId(['operation' => 'generate', 'status' => 'planned', 'recovery_status' => 'none', 'definition_hash' => str_repeat('e', 64), 'created_at' => $now, 'updated_at' => $now]);
    $replacementId = (int) Db::name('crud_generation')->insertGetId(['operation' => 'generate', 'status' => 'planned', 'recovery_status' => 'none', 'definition_hash' => str_repeat('f', 64), 'created_at' => $now, 'updated_at' => $now]);
    $repository->markSuperseded($supersededId, $replacementId, 'tester');
    $superseded = CrudGeneration::find($supersededId);
    canonicalStateExpect($superseded->status === 'superseded' && (int) $superseded->superseded_by_id === $replacementId, 'markSuperseded 状态错误');

    Db::name('business_module')->insert(['id' => 21, 'lifecycle_status' => 'draft', 'generation_status' => 'idle', 'created_at' => $now, 'updated_at' => $now]);
    $recoveredId = (int) Db::name('crud_generation')->insertGetId([
        'business_module_id' => 21, 'operation' => 'generate', 'status' => 'failed', 'recovery_status' => 'recovery_required',
        'definition_hash' => $definitionHash, 'definition' => json_encode(['routePath' => '/generated/recovered'], JSON_THROW_ON_ERROR),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $repository->markRecovering($recoveredId, 'recovery-tester');
    $repository->commitGeneration(21, $recoveredId, [], 'tx-recovered', str_repeat('1', 64));
    $recovered = CrudGeneration::find($recoveredId);
    canonicalStateExpect($recovered->status === 'completed' && $recovered->recovery_status === 'recovered_completed' && $recovered->recovered_at !== null, '恢复后完成必须持久化 recovered_completed');
} finally {
    $app->config->set($serverConfig, 'database');
    Db::connect('mysql', true)->execute('DROP DATABASE IF EXISTS ' . canonicalStateIdentifier($databaseName));
    $app->config->set($originalConfig, 'database');
    Db::connect('mysql', true);
}

echo "business generation canonical state tests: PASS\n";
