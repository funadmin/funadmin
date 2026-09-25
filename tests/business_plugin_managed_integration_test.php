<?php

declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use app\admin\form\repository\FormSchemaRepository;
use app\admin\form\model\Form;
use think\App;
use think\facade\Db;

function managedExpect(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}
function managedReject(callable $operation, string $message): void
{
    try { $operation(); } catch (InvalidArgumentException $error) {
        managedExpect(str_contains($error->getMessage(), $message), $error->getMessage());
        return;
    }
    throw new RuntimeException('预期拒绝：' . $message);
}

$repository = dirname(__DIR__);
$app = new App($repository . '/');
$app->initialize();
set_exception_handler(static function (Throwable $error): void { fwrite(STDERR, $error . "\n"); exit(1); });
$config = (array) config('database');
$source = Db::connect('mysql');
$tables = ['form', 'form_field', 'form_schema_version', 'business_module', 'crud_generation', 'generated_file_baseline'];
$ddl = [];
// 按本机实际前缀读取表结构，在隔离库统一重命名为测试约定的 fun_ 前缀，不依赖安装时选择的前缀。
$sourcePrefix = (string) ($config['connections']['mysql']['prefix'] ?? '');
foreach ($tables as $table) {
    $sql = array_values($source->query('SHOW CREATE TABLE `' . $sourcePrefix . $table . '`')[0])[1];
    $ddl[] = preg_replace('/^CREATE TABLE `[^`]+`/', 'CREATE TABLE `fun_' . $table . '`', $sql);
}
$name = 'funadmin_managed_test_' . bin2hex(random_bytes(6));
$serverConfig = $config;
$serverConfig['connections']['isolated_server'] = $config['connections']['mysql'];
$serverConfig['connections']['isolated_server']['database'] = '';
$app->config->set($serverConfig, 'database');
$server = Db::connect('isolated_server', true);
$server->execute('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4');
try {
    $config['connections']['mysql']['database'] = $name;
    $config['connections']['mysql']['prefix'] = 'fun_';
    $app->config->set($config, 'database');
    Db::connect('mysql', true);
    managedExpect(Db::query('SELECT DATABASE() AS db')[0]['db'] === $name, '必须使用隔离数据库');
    foreach ($ddl as $sql) Db::execute($sql);
    $schemas = new FormSchemaRepository();
    $form = Form::create(['form_key' => 'entry', 'name' => '条目', 'table_name' => 'fun_sample_item', 'connection' => 'mysql', 'source_type' => 'created']);
    $document = ['schemaVersion' => 2, 'key' => 'entry', 'title' => '条目',
        'database' => ['connection' => 'mysql', 'table' => 'fun_sample_item', 'source' => 'created'],
        'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
            'database' => ['columnType' => 'varchar(255)', 'nullable' => false], 'children' => []]]];
    $schemas->saveVersion((int) $form->id, $document, 'manual', 'tester');
    $initial = $schemas->draft((int) $form->id)->hash();
    foreach (['key', 'table', 'connection', 'source'] as $part) {
        $bad = $document;
        if ($part === 'key') $bad['key'] = 'foreign';
        else $bad['database'][$part] = match ($part) { 'table' => 'fun_foreign', 'connection' => 'other', 'source' => 'adopted' };
        managedReject(fn () => $schemas->saveVersion((int) $form->id, $bad, 'manual', 'tester'), 'BUSINESS_SCHEMA_IDENTITY_CONFLICT');
        managedExpect($schemas->draft((int) $form->id)->hash() === $initial, '拒绝后不得修改草稿');
    }
    $designer = new \app\admin\form\service\FormDesignerService($repository, $schemas);
    managedReject(fn () => $designer->save(['id' => (int) $form->id, 'form_key' => 'entry', 'name' => '条目',
        'table_name' => 'fun_foreign', 'connection' => 'mysql', 'source_type' => 'created', 'fields' => []]), 'BUSINESS_SCHEMA_IDENTITY_CONFLICT');
    managedExpect(Form::find($form->id)->table_name === 'fun_sample_item', '设计器不得先篡改身份再保存版本');
    echo "managed plugin isolated database: schema identity PASS\n";
    $root = $repository . '/runtime/managed-isolated-' . bin2hex(random_bytes(6));
    mkdir($root . '/plugins', 0700, true);
    (new \app\common\plugin\sdk\PluginScaffolder($root . '/plugins'))->scaffold('sample', '隔离插件');
    $target = \app\admin\development\service\BusinessModuleService::normalizeTarget(['type' => 'plugin', 'pluginCode' => 'sample'], 'created');
    $module = \app\admin\development\model\BusinessModule::create(['code' => 'entry', 'name' => '条目', 'form_id' => $form->id,
        'origin' => 'visual', 'connection_name' => 'mysql', 'table_name' => 'fun_sample_item', 'metadata' => ['target' => $target]]);
    $policy = new \app\admin\development\service\BusinessTargetService($root, 'mysql', static fn () => true, static fn () => [],
        static fn () => [['code' => 'sample', 'name' => '隔离插件', 'scopes' => ['admin'], 'businessWritable' => true]], static fn () => [], static fn () => false);
    $service = new \app\admin\development\service\ManagedGenerationService($root, targetService: $policy);
    // 使用真实 DB 冲突记录验证采纳与三层锁、提交前 CAS 的边界。
    $adoptPath = 'plugins/sample/app/admin/model/Entry.php';
    $adoptContent = "<?php\nnamespace app\\admin\\model\\plugin\\sample;\nclass Entry {}\n";
    $adoptHash = hash('sha256', $adoptContent);
    file_put_contents($root . '/' . $adoptPath, $adoptContent);
    $conflict = \app\admin\development\model\CrudGeneration::create([
        'business_module_id' => $module->id, 'form_id' => $form->id, 'status' => 'conflict', 'operation' => 'preview',
        'definition_hash' => str_repeat('a', 64), 'manifest' => ['plan' => ['files' => [[
            'path' => $adoptPath, 'status' => 'conflict-no-base', 'remoteHash' => $adoptHash, 'artifactType' => 'model',
        ]]]],
    ]);
    $adopt = fn () => $service->adoptResolvedBaseline((int) $module->id, (int) $conflict->id, $adoptPath, $adoptHash, $adoptHash, 'tester');
    $infra = new \app\admin\plugin\service\PluginInfrastructureService();
    $held = $infra::lifecycleLock($root)->acquire('sample');
    try {
        try { $adopt(); throw new LogicException('采纳未取得生命周期锁'); }
        catch (RuntimeException $error) { managedExpect(str_contains($error->getMessage(), '生命周期'), $error->getMessage()); }
    } finally { $held->release(); }
    $infra->withPublicationLock(function () use ($adopt): void {
        try { $adopt(); throw new LogicException('采纳未取得发布锁'); }
        catch (RuntimeException $error) { managedExpect(str_contains($error->getMessage(), '锁'), $error->getMessage()); }
    }, $root);
    managedExpect(Db::name('generated_file_baseline')->count() === 0, '竞争失败不能保存基线');
    $saved = $adopt();
    $state = new \app\admin\development\repository\DatabaseGenerationStateRepository();
    $expected = ['module' => \app\admin\development\model\BusinessModule::find($module->id)->toArray(),
        'generation' => \app\admin\development\model\CrudGeneration::find($conflict->id)->toArray(),
        'baseline' => $state->loadBaselines((int) $module->id)[0]];
    $casReject = function (array $snapshot) use ($state, $module, $conflict, $saved): void {
        try {
            $state->adoptResolvedBaseline((int) $module->id, (int) $conflict->id, $saved, $snapshot);
            throw new LogicException('提交 CAS 未拒绝快照漂移');
        } catch (\app\admin\development\exception\BusinessOperationException $error) {
            managedExpect(str_contains($error->getMessage(), 'GENERATION_PLAN_CONFLICT'), $error->getMessage());
        }
    };
    foreach (['module', 'generation', 'baseline'] as $part) {
        $stale = $expected;
        if ($part === 'module') $stale[$part]['metadata']['target']['pluginCode'] = 'other';
        elseif ($part === 'generation') $stale[$part]['manifest']['plan']['files'][0]['remoteHash'] = str_repeat('c', 64);
        else $stale[$part]['base_hash'] = str_repeat('d', 64);
        $casReject($stale);
    }
    $stale = $expected;
    $stale['baseline'] = null;
    $casReject($stale);
    managedExpect($adopt()['base_hash'] === $adoptHash, '同一冲突重复采纳仍可用');
    $deniedPolicy = new \app\admin\development\service\BusinessTargetService($root, 'mysql', function () use ($root): bool {
        $probe = fopen($root . '/runtime/cache/business-development-write.lock', 'c+');
        try { managedExpect(!flock($probe, LOCK_EX | LOCK_NB), '授权校验必须位于生成锁内'); }
        finally { fclose($probe); }
        return false;
    });
    $deniedAdoption = new \app\admin\development\service\ManagedGenerationService($root, targetService: $deniedPolicy);
    managedReject(fn () => $deniedAdoption->adoptResolvedBaseline((int) $module->id, (int) $conflict->id,
        $adoptPath, $adoptHash, $adoptHash, 'tester'), 'BUSINESS_TARGET_FORBIDDEN');
    \app\admin\development\model\CrudGeneration::create([
        'business_module_id' => $module->id, 'form_id' => $form->id, 'status' => 'conflict', 'operation' => 'preview', 'definition_hash' => str_repeat('b', 64),
    ]);
    try {
        $state->adoptResolvedBaseline((int) $module->id, (int) $conflict->id, $saved, $expected);
        throw new LogicException('提交 CAS 未拒绝过期冲突');
    } catch (\app\admin\development\exception\BusinessOperationException $error) {
        managedExpect(str_contains($error->getMessage(), 'GENERATION_PLAN_CONFLICT'), $error->getMessage());
    }
    Db::name('generated_file_baseline')->delete(true);
    unlink($root . '/' . $adoptPath);
    echo "managed plugin isolated database: baseline adoption locks and CAS PASS\n";
    $preview = $service->preview((int) $module->id, true, 'isolated-plugin-preview');
    managedExpect(!$preview['plan']['blocked'], '插件草稿必须能预览');
    managedExpect(!is_file($root . '/plugins/sample/app/admin/model/Entry.php'), '预览不得写源码');
    $result = $service->execute((int) $module->id, $preview['generationId'], $preview['sensitive']['confirmToken']);
    managedExpect($result['resourceApplyStatus'] === 'pending_publication', '源码生成不能冒充已发布');
    managedExpect(is_file($root . '/plugins/sample/app/admin/model/Entry.php'), '必须写入插件 CRUD');
    managedExpect(count(glob($root . '/plugins/sample/database/migrations/*.sql')) > 0, '必须写入迁移');
    managedExpect(Db::query("SHOW TABLES LIKE 'fun_sample_item'") === [], '生成不得执行 DDL');
    $savedModule = \app\admin\development\model\BusinessModule::find($module->id);
    managedExpect($savedModule->metadata['target']['locked'] === true, '成功才原子锁定目标');
    $manifestBaseline = Db::name('generated_file_baseline')->where('artifact_type', 'manifest')->find();
    managedExpect($manifestBaseline !== null, 'Manifest 必须保存模块投影基线');
    $projection = json_decode((new \app\admin\development\repository\GeneratedFileBaselineRepository($root))->load($manifestBaseline['base_storage_path'], $manifestBaseline['base_hash']), true);
    managedExpect(isset($projection['adminWeb']) && !isset($projection['code'], $projection['entry']), 'Manifest 基线不得包含整文件身份及人工键');
    $again = $service->preview((int) $module->id, true, 'isolated-plugin-repeat');
    managedExpect(!$again['plan']['blocked'], '相同迁移应复用，重复生成可执行');
    $modules = new \app\admin\development\service\BusinessModuleService();
    $generation = \app\admin\development\model\CrudGeneration::find($again['generationId']);
    $generation->save(['status' => 'failed', 'recovery_status' => 'recovery_required']);
    $listed = $modules->listing(1, 20, '', '', '')['list'][0];
    managedExpect(($listed['recovery_status'] ?? '') === 'recovery_required', '列表必须读取生成记录恢复状态，不能依赖旧成功模块指针');
    managedExpect($listed['generation_status'] === 'failed', '列表必须显示最新生成失败而非旧成功状态');
    $generation->save(['status' => 'running', 'recovery_status' => 'recovering']);
    $listed = $modules->listing(1, 20, '', '', '')['list'][0];
    managedExpect(($listed['recovery_status'] ?? '') === 'recovering', '列表必须展示真实恢复中状态');
    managedExpect(!isset($listed['manifest'], $listed['transaction_id']), '列表不得暴露生成内部记录');
    $generation->save(['status' => 'planned', 'recovery_status' => 'none']);
    echo "managed plugin isolated database: generation and recovery listing PASS\n";
    if (!is_dir($root . '/runtime/plugins')) mkdir($root . '/runtime/plugins', 0700, true);
    $lock = fopen($root . '/runtime/plugins/publication.lock', 'c+');
    flock($lock, LOCK_EX | LOCK_NB);
    try {
        try {
            $service->execute((int) $module->id, $again['generationId'], $again['sensitive']['confirmToken']);
            throw new RuntimeException('发布锁占用时 managed 必须拒绝');
        } catch (\RuntimeException $error) {
            managedExpect(str_contains($error->getMessage(), '锁'), '必须因共享锁拒绝');
        }
    } finally { flock($lock, LOCK_UN); fclose($lock); }
    echo "managed plugin isolated database: publication lock PASS\n";
    $service->execute((int) $module->id, $again['generationId'], $again['sensitive']['confirmToken']);
    $drift = $service->preview((int) $module->id, true, 'isolated-plugin-drift');
    file_put_contents($root . '/plugins/sample/database/migrations/099_manual.sql', "SELECT 1;\n");
    try {
        $service->execute((int) $module->id, $drift['generationId'], $drift['sensitive']['confirmToken']);
        throw new RuntimeException('迁移目录变化必须拒绝');
    } catch (\app\admin\development\exception\BusinessOperationException $error) {
        managedExpect(str_contains($error->getMessage(), '漂移'), '迁移目录指纹必须绑定令牌');
    }
    $definition = $service->resolveDefinition((int) $module->id)['definition'];
    managedReject(fn () => (new \app\common\crud\CrudGenerator($root))->planManaged($definition,
        [['path' => 'plugins/other/app/admin/model/Entry.php', 'baseContent' => 'foreign']]), 'BUSINESS_ARTIFACT_PATH_FORBIDDEN');
    echo "managed plugin isolated database: migration drift and path PASS\n";
    $race = $service->preview((int) $module->id, true, 'isolated-plugin-race');
    $record = \app\admin\development\model\CrudGeneration::find($race['generationId'])->toArray();
    $method = new ReflectionMethod($service, 'rebuildRecordedBundle');
    $bundle = $method->invoke($service, (int) $module->id, $record, $record['manifest'], $record['manifest']['managedNonce']);
    unset($bundle['definition']);
    $state = new \app\admin\development\repository\DatabaseGenerationStateRepository();
    $transaction = new \app\admin\development\service\GenerationTransactionService($root, new \app\common\crud\ConfirmationToken($root),
        new \app\admin\development\repository\GeneratedFileBaselineRepository($root, $state), $state, new \stdClass(),
        static function () use ($root, $bundle) {
            file_put_contents($root . '/plugins/sample/database/migrations/098_race.sql', "SELECT 3;\n");
            return $bundle;
        });
    try {
        $transaction->execute((int) $module->id, $race['generationId'], $bundle, $race['sensitive']['confirmToken'],
                    fn () => $state->claimGeneration((int) $module->id, $race['generationId'], 'tester'));
        throw new RuntimeException('持锁后必须再次检查迁移指纹');
    } catch (\app\admin\development\exception\BusinessOperationException $error) {
        managedExpect(str_contains($error->getMessage(), '漂移'), '持锁指纹异常：' . $error->getMessage());
    }
    $unauthorized = new \app\admin\development\service\BusinessTargetService($root, 'mysql', static fn () => false);
    $denied = new \app\admin\development\service\ManagedGenerationService($root, targetService: $unauthorized);
    managedReject(fn () => $denied->preview((int) $module->id, true, 'isolated-denied-preview'), 'BUSINESS_TARGET_FORBIDDEN');
    session('admin.id', 101);
    $actorPreview = $service->preview((int) $module->id, true, 'isolated-actor-preview');
    session('admin.id', 102);
    try {
        $service->execute((int) $module->id, $actorPreview['generationId'], $actorPreview['sensitive']['confirmToken']);
        throw new RuntimeException('跨操作者令牌必须拒绝');
    } catch (\app\admin\development\exception\BusinessOperationException $error) {
        managedExpect(str_contains($error->getMessage(), '漂移'), '操作者必须绑定 bundle');
    }
    session('admin.id', null);
    $recoveryId = bin2hex(random_bytes(16));
    $journal = ['transaction_id' => $recoveryId, 'state' => 'prepared', 'history' => ['prepared'], 'module_id' => (int) $module->id,
        'target' => ['type' => 'plugin', 'plugin' => 'sample', 'scope' => 'admin'], 'allowed_paths' => [],
        'generation_id' => $actorPreview['generationId'], 'plan_digest' => str_repeat('a', 64),
                'files' => [['path' => 'plugins/foreign/plugin.json']]];
    file_put_contents($root . '/runtime/private/business-development/wal/' . $recoveryId . '.json', json_encode($journal));
    managedReject(fn () => $transaction->recover($recoveryId), 'BUSINESS_ARTIFACT_PATH_FORBIDDEN');
    $external = \app\common\plugin\sdk\Manifest::fromDirectory($root . '/plugins/sample');
    $manifestData = $external->toArray();
    $manifestData['externalTables'] = [['module' => 'legacy', 'connection' => 'mysql', 'table' => 'fun_legacy', 'primaryKey' => ['id'],
        'columns' => [['name' => 'id', 'type' => 'bigint unsigned', 'nullable' => false]]]];
    $manifestData['purge'] = ['supported' => true];
    file_put_contents($root . '/plugins/sample/plugin.json', json_encode($manifestData));
    Db::execute('CREATE TABLE fun_legacy (id bigint unsigned NOT NULL PRIMARY KEY)');
    managedReject(fn () => (new \app\admin\plugin\service\PluginInfrastructureService())->assertExternalTables(
        \app\common\plugin\sdk\Manifest::fromDirectory($root . '/plugins/sample')), 'EXTERNAL_TABLE_PURGE_FORBIDDEN');
    $manifestData['purge'] = ['supported' => false];
    file_put_contents($root . '/plugins/sample/plugin.json', json_encode($manifestData));
    file_put_contents($root . '/plugins/sample/database/migrations/097_external.sql', "ALTER TABLE `fun_legacy` ADD COLUMN hacked int;\n");
    managedReject(fn () => (new \app\admin\plugin\service\PluginInfrastructureService())->assertExternalTables(
        \app\common\plugin\sdk\Manifest::fromDirectory($root . '/plugins/sample')), 'EXTERNAL_TABLE_DDL_FORBIDDEN');
    $manifestData['externalTables'] = [];
    file_put_contents($root . '/plugins/sample/plugin.json', json_encode($manifestData));
    Db::execute('CREATE TABLE fun_sample_item (id bigint unsigned NOT NULL PRIMARY KEY, title int NOT NULL)');
    managedReject(fn () => (new \app\admin\plugin\service\PluginInfrastructureService())->assertExternalTables(
        \app\common\plugin\sdk\Manifest::fromDirectory($root . '/plugins/sample')), 'PLUGIN_TABLE_STRUCTURE_CONFLICT');
    $createFile = glob($root . '/plugins/sample/database/migrations/*_create_entry.sql')[0];
    $createSql = (string) file_get_contents($createFile);
    Db::execute('DROP TABLE fun_sample_item');
    Db::execute($createSql);
    $infrastructure = new \app\admin\plugin\service\PluginInfrastructureService();
    $infrastructure->assertExternalTables(\app\common\plugin\sdk\Manifest::fromDirectory($root . '/plugins/sample'));
    Db::execute("ALTER TABLE fun_sample_item ALTER COLUMN title SET DEFAULT 'unexpected'");
    managedReject(fn () => $infrastructure->assertExternalTables(
        \app\common\plugin\sdk\Manifest::fromDirectory($root . '/plugins/sample')), 'PLUGIN_TABLE_STRUCTURE_CONFLICT');
    echo "managed plugin isolated database: compatible CREATE and default conflict PASS\n";
    $httpRuntime = $root . '/http-runtime/console/';
    $app->setRuntimePath($httpRuntime);
    $infrastructureClass = \app\admin\plugin\service\PluginInfrastructureService::class;
    managedExpect($infrastructureClass::lockDirectory($repository) === rtrim($httpRuntime, '/') . '/plugins', 'HTTP console runtime 必须复用实际发布锁目录');
    managedExpect($infrastructureClass::lockDirectory() === $infrastructureClass::lockDirectory($repository), 'HTTP 插件操作与生成锁路径必须一致');
    $httpHeld = $infrastructureClass::lifecycleLock()->acquire('sample');
    try {
        try { $infrastructureClass::lifecycleLock($repository)->acquire('sample'); throw new LogicException('HTTP 同插件锁必须互斥'); }
        catch (RuntimeException $error) { managedExpect(str_contains($error->getMessage(), '生命周期'), 'HTTP 锁异常'); }
    } finally { $httpHeld->release(); }
    echo "managed plugin isolated database: HTTP runtime shared lifecycle lock PASS\n";
    $duplicate = $root . '/plugins/sample/database/migrations/099_duplicate.sql';
    file_put_contents($duplicate, "SELECT 2;\n");
    managedReject(fn () => (new \app\common\crud\CrudGenerator($root))->planManaged($definition, []), '版本重复');
} finally {
    $server->execute('DROP DATABASE `' . $name . '`');
}
