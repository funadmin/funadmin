<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\MigrationService;
use app\console\model\BackendModel;
use app\console\model\BusinessModule;
use app\console\model\CrudGeneration;
use app\console\model\Form;
use app\console\model\GeneratedFileBaseline;
use think\App;

function businessDevelopmentExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function businessDevelopmentProperty(object $object, string $property): mixed
{
    $reflection = new ReflectionClass($object);
    while (!$reflection->hasProperty($property) && ($reflection = $reflection->getParentClass())) {
    }
    businessDevelopmentExpect($reflection !== false && $reflection->hasProperty($property), '模型缺少属性：' . $property);
    $modelProperty = $reflection->getProperty($property);
    $modelProperty->setAccessible(true);
    return $modelProperty->getValue($object);
}

function businessDevelopmentIdentifier(string $identifier): string
{
    businessDevelopmentExpect(preg_match('/^[a-z0-9_]+$/', $identifier) === 1, '数据库标识符不安全');
    return '`' . $identifier . '`';
}

$root = dirname(__DIR__);
$migrationName = '077_business_development_center.sql';
$migrationPath = $root . '/database/migrations/' . $migrationName;
$migrations = array_map('basename', glob($root . '/database/migrations/*.sql') ?: []);
sort($migrations, SORT_STRING);

businessDevelopmentExpect(in_array($migrationName, $migrations, true), '缺少业务开发中心 077 migration');
$numbered077 = array_values(array_filter($migrations, static fn (string $name): bool => str_starts_with($name, '077_')));
businessDevelopmentExpect($numbered077 === [$migrationName], '077 migration 编号冲突');
businessDevelopmentExpect(is_file($migrationPath), '缺少业务开发中心 migration');
businessDevelopmentExpect(is_file($root . '/app/console/model/BusinessModule.php'), '缺少 BusinessModule 模型');
businessDevelopmentExpect(is_file($root . '/app/console/model/GeneratedFileBaseline.php'), '缺少 GeneratedFileBaseline 模型');

$businessModule = (new ReflectionClass(BusinessModule::class))->newInstanceWithoutConstructor();
$baseline = (new ReflectionClass(GeneratedFileBaseline::class))->newInstanceWithoutConstructor();
businessDevelopmentExpect($businessModule instanceof BackendModel, 'BusinessModule 必须继承 BackendModel');
businessDevelopmentExpect($baseline instanceof BackendModel, 'GeneratedFileBaseline 必须继承 BackendModel');
businessDevelopmentExpect(businessDevelopmentProperty($businessModule, 'name') === 'business_module', 'BusinessModule 表名错误');
businessDevelopmentExpect(businessDevelopmentProperty($baseline, 'name') === 'generated_file_baseline', 'GeneratedFileBaseline 表名错误');
businessDevelopmentExpect(in_array('metadata', (array) businessDevelopmentProperty($businessModule, 'json'), true), 'BusinessModule.metadata 必须使用 JSON 转换');
$crudGeneration = (new ReflectionClass(CrudGeneration::class))->newInstanceWithoutConstructor();
businessDevelopmentExpect(in_array('definition', (array) businessDevelopmentProperty($crudGeneration, 'json'), true), 'CrudGeneration.definition 必须使用 JSON 转换');
businessDevelopmentExpect((new ReflectionClass(Form::class))->isSubclassOf(BackendModel::class), 'Form 必须继承 BackendModel');
foreach ([BusinessModule::class, GeneratedFileBaseline::class] as $modelClass) {
    $traits = class_uses($modelClass);
    businessDevelopmentExpect(isset($traits['app\\common\\model\\concern\\LaravelSoftDelete']), $modelClass . ' 必须使用 LaravelSoftDelete');
}

$migration = (string) file_get_contents($migrationPath);
foreach (['fun_business_module', 'fun_generated_file_baseline', 'fun_crud_generation', 'fun_form'] as $table) {
    businessDevelopmentExpect(str_contains($migration, $table), 'migration 缺少表契约：' . $table);
}
foreach (['information_schema.COLUMNS', 'information_schema.STATISTICS'] as $guard) {
    businessDevelopmentExpect(str_contains($migration, $guard), 'migration 缺少幂等守卫：' . $guard);
}
$businessModuleDefinition = preg_match('/CREATE TABLE IF NOT EXISTS `fun_business_module`([\s\S]*?)\n\)/', $migration, $businessModuleMatch) === 1 ? $businessModuleMatch[1] : '';
$businessModuleFields = [
    'code', 'name', 'form_id', 'origin', 'connection_name', 'table_name', 'runtime_route', 'module_route',
    'lifecycle_status', 'published_schema_hash', 'published_schema_version', 'current_generation_id',
    'last_success_generation_id', 'generation_status', 'created_by', 'updated_by', 'created_at', 'updated_at', 'deleted_at',
];
foreach ($businessModuleFields as $field) {
    businessDevelopmentExpect(str_contains($businessModuleDefinition, '`' . $field . '`'), 'business module 缺少计划字段：' . $field);
}
businessDevelopmentExpect(!str_contains($businessModuleDefinition, '`source_form_id`'), 'business module 禁止使用 source_form_id 替代 form_id');
businessDevelopmentExpect(!str_contains($businessModuleDefinition, '`status`') && !str_contains($businessModuleDefinition, '`schema_hash`'), 'business module 禁止使用 status/schema_hash 替代计划字段');
businessDevelopmentExpect(preg_match('/UNIQUE KEY[^\n]*\(`code`\)/', $businessModuleDefinition) === 1, 'business module.code 必须唯一');
businessDevelopmentExpect(preg_match('/UNIQUE KEY[^\n]*\(`form_id`\)/', $businessModuleDefinition) === 1, 'business module.form_id 必须唯一');
$baselineDefinition = preg_match('/CREATE TABLE IF NOT EXISTS `fun_generated_file_baseline`([\s\S]*?)\n\)/', $migration, $baselineMatch) === 1 ? $baselineMatch[1] : '';
foreach (['business_module_id', 'relative_path', 'artifact_type', 'base_hash', 'base_storage_path', 'target_hash', 'template_version', 'definition_hash', 'generation_id', 'content_kind', 'status', 'created_at', 'updated_at', 'deleted_at'] as $field) {
    businessDevelopmentExpect(str_contains($baselineDefinition, '`' . $field . '`'), 'baseline 缺少计划字段：' . $field);
}
businessDevelopmentExpect(!str_contains($baselineDefinition, '`base_content`'), 'baseline 禁止存储 base_content');
businessDevelopmentExpect(preg_match('/UNIQUE KEY[^\n]*\(`business_module_id`,`relative_path`\)/', $baselineDefinition) === 1, 'baseline 必须按 module+relative_path 唯一');
foreach (['business_module_id', 'form_id', 'binding_status', 'generation_mode', 'transaction_id', 'plan_digest', 'recovery_status'] as $field) {
    businessDevelopmentExpect(str_contains($migration, "COLUMN_NAME='" . $field . "'"), 'crud generation 缺少幂等字段：' . $field);
}
foreach (['idx_crud_generation_business_module', 'idx_crud_generation_form', 'idx_crud_generation_transaction', 'idx_crud_generation_plan_digest'] as $index) {
    businessDevelopmentExpect(str_contains($migration, "INDEX_NAME='" . $index . "'"), 'crud generation 缺少幂等索引：' . $index);
}
businessDevelopmentExpect(str_contains($migration, "COLUMN_NAME='publish_mode'"), 'form 缺少 publish_mode 幂等字段');
businessDevelopmentExpect(str_contains($migration, "DEFAULT ''dynamic''"), 'form.publish_mode 必须默认 dynamic');
businessDevelopmentExpect(str_contains($migration, "'legacy_form'"), '表单回填 origin 必须是 legacy_form');
businessDevelopmentExpect(str_contains($migration, "'visual'") && str_contains($migration, "'database'"), 'business module origin 必须覆盖计划来源');
businessDevelopmentExpect(str_contains($migration, "CONCAT('/development/business/runtime/',"), '表单回填 runtime_route 错误');
businessDevelopmentExpect(str_contains($migration, "'unbound'"), '无法可靠关联的 generation 必须保持 unbound');
businessDevelopmentExpect(str_contains($migration, 'published_schema_hash') && str_contains($migration, 'fun_form_schema_version'), '发布版本必须由 schema hash 可靠解析');
businessDevelopmentExpect(str_contains($migration, "JSON_UNQUOTE(JSON_EXTRACT(g.`definition`,'$.formSchemaHash'))"), 'generation 必须优先校验 definition.formSchemaHash');
businessDevelopmentExpect(preg_match('/COUNT\(\*\).*?=\s*1/s', $migration) === 1, 'generation 关联必须排除歧义');
businessDevelopmentExpect(!preg_match('/\b(?:DROP|TRUNCATE|DELETE|RENAME)\b/i', preg_replace('/^\s*--.*$/m', '', $migration)), 'migration 必须 forward-only');
businessDevelopmentExpect(!preg_match('/INSERT\s+(?:IGNORE\s+)?INTO\s+`fun_generated_file_baseline`/i', $migration), '无可靠 Base 时不得猜测并回填 baseline');
foreach (['created_at', 'updated_at', 'deleted_at'] as $timeField) {
    businessDevelopmentExpect(substr_count($migration, '`' . $timeField . '`') >= 2, '新实体表缺少 Laravel 时间字段：' . $timeField);
}

if (!extension_loaded('pdo_mysql')) {
    echo "business development migration static contract tests: PASS; pdo_mysql unavailable, integration skipped\n";
    return;
}

$app = new App($root . '/');
$app->initialize();
$config = (array) config('database.connections.mysql');
$sourceDatabase = (string) ($config['database'] ?? '');
$temporaryDatabase = 'funadmin_business_development_' . bin2hex(random_bytes(6));
$host = (string) (getenv('BUSINESS_DEVELOPMENT_TEST_DB_HOST') ?: ($config['hostname'] ?? '127.0.0.1'));
$port = (string) (getenv('BUSINESS_DEVELOPMENT_TEST_DB_PORT') ?: ($config['hostport'] ?? '3306'));
$charset = (string) ($config['charset'] ?? 'utf8mb4');
$username = (string) (getenv('BUSINESS_DEVELOPMENT_TEST_DB_USER') ?: ($config['username'] ?? ''));
$password = (string) (getenv('BUSINESS_DEVELOPMENT_TEST_DB_PASS') ?: ($config['password'] ?? ''));
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => true];
$server = null;

try {
    $server = new PDO("mysql:host={$host};port={$port};charset={$charset}", $username, $password, $options);
} catch (Throwable $exception) {
    echo 'business development migration static contract tests: PASS; MySQL unavailable, integration skipped: ' . $exception->getMessage() . "\n";
    return;
}

businessDevelopmentExpect($sourceDatabase !== $temporaryDatabase, '隔离测试库不得等于项目数据库');
try {
    $server->exec('CREATE DATABASE ' . businessDevelopmentIdentifier($temporaryDatabase) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $database = new PDO("mysql:host={$host};port={$port};dbname={$temporaryDatabase};charset={$charset}", $username, $password, $options);
    $database->exec(<<<'SQL'
CREATE TABLE `fun_form` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `form_key` varchar(64) NOT NULL, `name` varchar(100) NOT NULL,
  `table_name` varchar(100) NOT NULL DEFAULT '', `connection` varchar(50) NOT NULL DEFAULT 'mysql',
  `status` tinyint NOT NULL DEFAULT 1, `publish_status` varchar(20) NOT NULL DEFAULT 'draft', `schema_version` int NOT NULL DEFAULT 1,
  `schema_hash` char(64) NULL, `published_schema_hash` char(64) NULL, `crud_generation_id` bigint unsigned NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_form_key` (`form_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `fun_form_schema_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `form_id` bigint unsigned NOT NULL, `version` int unsigned NOT NULL,
  `schema_version` int unsigned NOT NULL, `schema_hash` char(64) NOT NULL, `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_form_schema_version` (`form_id`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE `fun_crud_generation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `operation` varchar(20) NOT NULL, `status` varchar(32) NOT NULL,
  `connection_name` varchar(64) NOT NULL DEFAULT '', `table_name` varchar(190) NOT NULL DEFAULT '',
  `definition_hash` char(64) NOT NULL, `definition` json NULL, `manifest` json NULL,
  `created_at` datetime NULL, `updated_at` datetime NULL, `deleted_at` datetime NULL, PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    $currentHash = str_repeat('a', 64);
    $publishedHash = str_repeat('b', 64);
    $definitionHash = str_repeat('c', 64);
    $customerHash = str_repeat('e', 64);
    $mismatchHash = str_repeat('f', 64);
    $database->exec("INSERT INTO fun_crud_generation (id,operation,status,connection_name,table_name,definition_hash,definition,manifest) VALUES (10,'generate','success','mysql','fun_orders','{$definitionHash}',JSON_OBJECT('formSchemaHash','{$publishedHash}','connection','mysql','table','fun_orders'),JSON_OBJECT()),(11,'generate','success','mysql','fun_invoices',REPEAT('d',64),JSON_OBJECT('formSchemaHash','{$mismatchHash}','connection','mysql','table','fun_invoices'),JSON_OBJECT()),(12,'generate','success','mysql','fun_customers',REPEAT('e',64),JSON_OBJECT('formSchemaHash','{$customerHash}','connection','mysql','table','fun_customers'),JSON_OBJECT()),(13,'generate','success','mysql','fun_unknown',REPEAT('f',64),JSON_OBJECT(),JSON_OBJECT())");
    $database->exec("INSERT INTO fun_form (id,form_key,name,table_name,connection,publish_status,schema_version,schema_hash,published_schema_hash,crud_generation_id,created_at,updated_at) VALUES (1,'orders','Orders','fun_orders','mysql','published',2,'{$currentHash}','{$publishedHash}',10,NOW(),NOW()),(2,'drafts','Drafts','fun_drafts','archive','draft',2,'{$currentHash}',NULL,NULL,NOW(),NOW()),(3,'removed','Removed','fun_removed','mysql','published',2,'{$currentHash}','{$publishedHash}',NULL,NOW(),NOW()),(4,'invoices','Invoices','fun_invoices','mysql','published',2,'{$currentHash}','{$publishedHash}',11,NOW(),NOW()),(5,'customers','Customers','fun_customers','mysql','published',2,'{$currentHash}','{$customerHash}',NULL,NOW(),NOW())");
    $database->exec("UPDATE fun_form SET deleted_at=NOW() WHERE id=3");
    $database->exec("INSERT INTO fun_form_schema_version (form_id,version,schema_version,schema_hash,created_at) VALUES (1,3,2,'{$publishedHash}',NOW()),(1,9,2,'{$mismatchHash}',NOW()),(4,4,2,'{$publishedHash}',NOW()),(5,5,2,'{$customerHash}',NOW())");

    $service = new MigrationService();
    $forwardOnly = new ReflectionMethod($service, 'assertForwardOnly');
    $forwardOnly->setAccessible(true);
    $forwardOnly->invoke($service, $migration, $migrationPath);
    $statements = new ReflectionMethod($service, 'statements');
    $statements->setAccessible(true);
    foreach ($statements->invoke($service, $migration) as $statement) {
        $database->exec($statement);
    }
    foreach ($statements->invoke($service, $migration) as $statement) {
        $database->exec($statement);
    }

    businessDevelopmentExpect((int) $database->query('SELECT COUNT(*) FROM fun_business_module')->fetchColumn() === 4, '必须幂等回填全部未删除 form');
    $module = $database->query("SELECT * FROM fun_business_module WHERE code='orders'")->fetch();
    businessDevelopmentExpect($module['origin'] === 'legacy_form' && $module['runtime_route'] === '/development/business/runtime/orders', 'legacy form 模块映射错误');
    businessDevelopmentExpect((int) $module['form_id'] === 1, 'business module.form_id 回填错误');
    businessDevelopmentExpect($module['connection_name'] === 'mysql' && $module['table_name'] === 'fun_orders', 'connection/table 回填错误');
    businessDevelopmentExpect($module['lifecycle_status'] === 'published', 'lifecycle_status 回填错误');
    businessDevelopmentExpect((int) $module['published_schema_version'] === 3 && $module['published_schema_hash'] === $publishedHash, '已发布 schema version/hash 必须精确关联');
    businessDevelopmentExpect((int) $module['current_generation_id'] === 10 && (int) $module['last_success_generation_id'] === 10 && $module['generation_status'] === 'success', 'generation 状态回填错误');
    businessDevelopmentExpect((int) $database->query('SELECT business_module_id FROM fun_form WHERE id=1')->fetchColumn() === (int) $module['id'], 'form.business_module_id 未更新');
    businessDevelopmentExpect($database->query('SELECT publish_mode FROM fun_form WHERE id=1')->fetchColumn() === 'dynamic', 'form.publish_mode 默认值错误');
    $bound = $database->query('SELECT business_module_id,form_id,binding_status FROM fun_crud_generation WHERE id=10')->fetch();
    businessDevelopmentExpect((int) $bound['business_module_id'] === (int) $module['id'] && (int) $bound['form_id'] === 1 && $bound['binding_status'] === 'bound', '显式且 hash 匹配的 generation 未关联');
    $mismatched = $database->query('SELECT business_module_id,form_id,binding_status FROM fun_crud_generation WHERE id=11')->fetch();
    businessDevelopmentExpect($mismatched['business_module_id'] === null && $mismatched['form_id'] === null && $mismatched['binding_status'] === 'unbound', '显式但 hash 不匹配的 generation 不得绑定');
    $customerModuleId = (int) $database->query("SELECT id FROM fun_business_module WHERE code='customers'")->fetchColumn();
    $metadataBound = $database->query('SELECT business_module_id,form_id,binding_status FROM fun_crud_generation WHERE id=12')->fetch();
    businessDevelopmentExpect((int) $metadataBound['business_module_id'] === $customerModuleId && (int) $metadataBound['form_id'] === 5 && $metadataBound['binding_status'] === 'bound', '严格 hash 可唯一定位的 generation 未关联');
    $unbound = $database->query('SELECT business_module_id,form_id,binding_status FROM fun_crud_generation WHERE id=13')->fetch();
    businessDevelopmentExpect($unbound['business_module_id'] === null && $unbound['form_id'] === null && $unbound['binding_status'] === 'unbound', '无可靠 metadata 的 generation 不得猜测关联');
    businessDevelopmentExpect((int) $database->query('SELECT COUNT(*) FROM fun_generated_file_baseline')->fetchColumn() === 0, '无可靠 Base 不得创建 baseline');
} finally {
    if ($server instanceof PDO && str_starts_with($temporaryDatabase, 'funadmin_business_development_')) {
        $server->exec('DROP DATABASE IF EXISTS ' . businessDevelopmentIdentifier($temporaryDatabase));
    }
}

echo "business development migration tests: PASS\n";
