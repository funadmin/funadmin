<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use app\console\development\model\BusinessModule;
use app\console\development\service\BusinessModuleService;
use app\console\development\service\BusinessTargetService;
use app\console\development\service\ManagedGenerationService;
use app\console\form\model\Form;
use app\console\form\repository\FormSchemaRepository;
use app\console\plugin\service\PluginService;
use think\App;
use think\facade\Db;

function lifecycleExpect(bool $ok, string $message): void
{
    if (!$ok) throw new RuntimeException($message);
}

function lifecycleManifest(string $directory, callable $change): void
{
    $data = Manifest::fromDirectory($directory)->toArray();
    $change($data);
    file_put_contents($directory . '/plugin.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
}

/** 只允许清理本次独占创建的目录；不跟随符号链接。 */
function lifecycleCleanup(string $root, string $parent, string $nonce): void
{
    lifecycleExpect($root === $parent . '/lifecycle-isolated-' . $nonce && !is_link($root), '清理根目录校验失败');
    if (!is_dir($root)) return;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $ok = $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        lifecycleExpect($ok, '临时文件清理失败');
    }
    lifecycleExpect(rmdir($root), '临时目录清理失败');
}

$repository = dirname(__DIR__);
$parent = realpath($repository . '/runtime');
lifecycleExpect(is_string($parent) && $parent === $repository . '/runtime', 'runtime 必须为仓库内真实目录');
$nonce = bin2hex(random_bytes(6));
$root = $parent . '/lifecycle-isolated-' . $nonce;
$name = 'funadmin_lifecycle_test_' . $nonce;
$user = 'falife_' . $nonce;
$password = bin2hex(random_bytes(24));
$server = null;
$createdDatabase = false;
$createdUser = false;
$createdRoot = false;
$exit = 0;

try {
    lifecycleExpect(!file_exists($root) && !is_link($root), '临时目录冲突');
    lifecycleExpect(mkdir($root, 0700), '无法创建隔离根目录');
    $createdRoot = true;
    // 不启动真实项目 AppService，不加载真实插件、事件、缓存或用户会话。
    $app = new App($root . '/');
    // ModelService 启动时会实例化 Db 并取得缓存驱动，必须在 initialize 之前隔离缓存。
    $app->config->set(['default' => 'file', 'stores' => ['file' => ['type' => 'File', 'path' => $root . '/runtime/cache/']]], 'cache');
    $app->initialize();
    set_exception_handler(static function (Throwable $error): void { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); });
    $app->env->load($repository . '/.env');
    $databaseConfig = require $repository . '/config/database.php';
    $source = $databaseConfig['connections']['mysql'];
    lifecycleExpect($source['type'] === 'mysql' && $source['prefix'] === 'fun_' && (int) $source['deploy'] === 0, '仅支持单机 MySQL / fun_ 前缀');
    lifecycleExpect(in_array($source['hostname'], ['127.0.0.1', 'localhost', '::1'], true), '拒绝使用非本地 MySQL');
    $dsn = 'mysql:host=' . $source['hostname'] . ';port=' . $source['hostport'] . ';charset=utf8mb4';
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
    $server = new PDO($dsn, $source['username'], $source['password'], $options);
    $clientHost = substr((string) $server->query('SELECT USER()')->fetchColumn(), strrpos((string) $server->query('SELECT USER()')->fetchColumn(), '@') + 1);
    lifecycleExpect($clientHost === 'localhost' || filter_var($clientHost, FILTER_VALIDATE_IP) !== false, '无法安全确定 MySQL 客户端地址');
    lifecycleExpect(preg_match('/^[a-zA-Z0-9_]+$/D', $source['database']) === 1, '源库名称不安全');
    // 复用 managed 集成测试的 SHOW CREATE TABLE 方案，仅复制必要结构，不读取数据、不运行旧 migration。
    $tables = ['form', 'form_field', 'form_schema_version', 'business_module', 'crud_generation', 'generated_file_baseline',
        'plugin', 'plugin_operation', 'plugin_version_history', 'plugin_resource', 'permission', 'admin_menu', 'casbin_rule', 'system_migration'];
    $ddl = [];
    foreach ($tables as $table) {
        $sql = array_values($server->query('SHOW CREATE TABLE `' . $source['database'] . '`.`fun_' . $table . '`')->fetch())[1];
        lifecycleExpect(!str_contains($sql, 'REFERENCES') && !str_contains($sql, 'CONNECTION='), '拒绝带外部依赖的源表结构');
        $ddl[] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
    }
    $server->exec('CREATE DATABASE `' . $name . '` CHARACTER SET utf8mb4');
    $createdDatabase = true;
    $server->exec("CREATE USER '" . $user . "'@'" . $clientHost . "' IDENTIFIED BY " . $server->quote($password));
    $createdUser = true;
    // GRANT 的库名下划线必须转义，避免成为权限通配符。
    $server->exec('GRANT ALL PRIVILEGES ON `' . str_replace('_', '\\_', $name) . "`.* TO '" . $user . "'@'" . $clientHost . "'");
    $isolated = new PDO($dsn . ';dbname=' . $name, $user, $password, $options);
    $grants = $isolated->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
    lifecycleExpect(count($grants) === 2, '临时账号出现非预期授权');
    foreach ($grants as $grant) lifecycleExpect(str_contains($grant, 'GRANT USAGE ON *.*') || str_contains(str_replace('\\_', '_', $grant), '`' . $name . '`.*'), '临时账号授权越界');
    $source['database'] = $name;
    $source['username'] = $user;
    $source['password'] = $password;
    $source['schema_cache_path'] = $root . '/runtime/schema/';
    $databaseConfig['default'] = 'mysql';
    $databaseConfig['connections'] = ['mysql' => $source];
    $app->config->set($databaseConfig, 'database');
    $app->config->set(['default' => 'file', 'stores' => ['file' => ['type' => 'File', 'path' => $root . '/runtime/cache/']]], 'cache');
    $app->config->set(['type' => 'File', 'path' => $root . '/runtime/session/'], 'session');
    $app->config->set(['default' => 'file', 'channels' => ['file' => ['type' => 'File', 'path' => $root . '/runtime/log/']]], 'log');
    $app->config->set(['version' => '8.1.0', 'mysqlPrefix' => 'fun_'], 'funadmin');
    $app->config->set(['confirm_secret' => bin2hex(random_bytes(32)), 'connections' => ['mysql']], 'crud');
    $app->config->set(require $repository . '/config/form.php', 'form');
    $app->config->set(require $repository . '/config/view.php', 'view');
    defined('DS') || define('DS', DIRECTORY_SEPARATOR);
    defined('PLUGIN_DIR') || define('PLUGIN_DIR', 'plugins');
    $app->bind('plugins', \app\common\plugin\sdk\Service::class);
    lifecycleExpect(root_path() === $root . '/' && public_path() === $root . '/public/' && runtime_path() === $root . '/runtime/', '文件系统未完全隔离');
    lifecycleExpect(Db::query('SELECT DATABASE() AS db')[0]['db'] === $name, '数据库未隔离');
    foreach ($ddl as $sql) Db::execute($sql);
    echo "ISOLATION PASS: database={$name}; restricted_user={$user}@{$clientHost}; source=DDL-only\n";
    echo "ISOLATION PASS: root={$root}; public/app/admin-web/cache/session/log/activation isolated\n";

    $code = 'lifetest';
    $directory = $root . '/plugins/' . $code;
    (new PluginScaffolder($root . '/plugins'))->scaffold($code, '生命周期隔离插件', false, true, true);
    // 真实插件钩子写入隔离运行目录，用于证明安装/更新确实经过入口，purge 被挡在钩子之前。
    $entry = (string) file_get_contents($directory . '/Plugin.php');
    $entry = str_replace('public function install(): bool { return true; }', 'public function install(): bool { file_put_contents(runtime_path() . "install-hook", "installed"); return true; }', $entry);
    $entry = str_replace('public function beforeUpdate(string $fromVersion, string $toVersion, bool $migrate): bool { return true; }', 'public function beforeUpdate(string $fromVersion, string $toVersion, bool $migrate): bool { file_put_contents(runtime_path() . "update-hook", $fromVersion . "->" . $toVersion); return true; }', $entry);
    $entry = str_replace('public function purgeData(): bool { return false; }', 'public function purgeData(): bool { file_put_contents(runtime_path() . "purge-hook", "unexpected"); return true; }', $entry);
    file_put_contents($directory . '/Plugin.php', $entry);
    $schemas = new FormSchemaRepository();
    // 只注入 CLI 测试授权；状态、候选插件、表归属及表存在性均使用真实实现。
    $policy = new BusinessTargetService($root, 'mysql', static fn (): bool => true);
    $generation = new ManagedGenerationService($root, targetService: $policy);
    $plugins = new PluginService();
    $firstMigration = [];
    foreach (['entry', 'detail'] as $index => $entity) {
        $table = 'fun_' . $code . '_' . $entity;
        $form = Form::create(['form_key' => $entity, 'name' => $entity, 'table_name' => $table, 'connection' => 'mysql', 'source_type' => 'created']);
        $document = ['schemaVersion' => 2, 'key' => $entity, 'title' => $entity,
            'database' => ['connection' => 'mysql', 'table' => $table, 'source' => 'created'],
            'nodes' => [['id' => 'title_node', 'kind' => 'field', 'type' => 'input', 'field' => 'title', 'title' => '标题',
                'database' => ['columnType' => 'varchar(255)', 'nullable' => false], 'children' => []]]];
        $schemas->saveVersion((int) $form->id, $document, 'manual', 'lifecycle-test');
        $target = BusinessModuleService::normalizeTarget(['type' => 'plugin', 'pluginCode' => $code], 'created');
        $module = BusinessModule::create(['code' => $entity, 'name' => $entity, 'form_id' => $form->id, 'origin' => 'visual',
            'connection_name' => 'mysql', 'table_name' => $table, 'metadata' => ['target' => $target]]);
        $preview = $generation->preview((int) $module->id, true, 'lifecycle-' . $entity);
        lifecycleExpect(!$preview['plan']['blocked'], '生成计划被阻断：' . json_encode($preview['plan']));
        $result = $generation->execute((int) $module->id, $preview['generationId'], $preview['sensitive']['confirmToken']);
        lifecycleExpect($result['resourceApplyStatus'] === 'pending_publication', '生成不得冒充发布');
        lifecycleExpect(!in_array($table, Db::connect()->getTables(), true), '生成时不得执行 DDL');
        lifecycleExpect(is_file($directory . '/app/console/model/' . ucfirst($entity) . '.php'), '模块源码未生成');
        if ($index === 0) {
            lifecycleExpect($plugins->installPlugin($code), '真实安装失败');
            lifecycleExpect(is_file(runtime_path() . 'install-hook'), '未执行安装钩子');
            $firstMigration = Db::name('system_migration')->where('scope', 'plugin:' . $code)->select()->toArray();
            lifecycleExpect(count($firstMigration) === 1, '安装应执行一个生成迁移');
            echo "INSTALL PASS: PluginService; entry generated; migration=1; hook executed\n";
        } else {
            lifecycleManifest($directory, static function (array &$data): void { $data['version'] = '1.1.0'; });
            lifecycleExpect($plugins->updatePlugin($code), '真实更新失败');
            lifecycleExpect(file_get_contents(runtime_path() . 'update-hook') === '1.0.0->1.1.0', '更新钩子版本不符');
            $migrations = Db::name('system_migration')->where('scope', 'plugin:' . $code)->select()->toArray();
            lifecycleExpect(count($migrations) === 2 && $migrations[0] === $firstMigration[0], '更新须保留首个迁移，只执行新增迁移');
            echo "UPDATE PASS: PluginService; detail generated; migrations=2; first migration unchanged\n";
        }
        lifecycleExpect(in_array($table, Db::connect()->getTables(), true), '生命周期未执行真实建表');
        $record = $plugins->isInstall($code);
        lifecycleExpect($record->lifecycle_state === 'disabled' && (int) $record->migration_pending === 0 && !$record->operation_token, '生命周期终态不正确');
        lifecycleExpect(Db::name('plugin_resource')->where('plugin_code', $code)->count() > 0, '未登记发布资源');
    }
    foreach (['Entry', 'Detail'] as $entity) lifecycleExpect(is_file($root . '/app/console/model/plugin/' . $code . '/' . $entity . '.php'), '两个模块必须都已原生发布：' . $entity);
    echo "TWO MODULES PASS: both generated models published; permissions=" . Db::name('permission')->where('source_name', $code)->count() . "\n";

    $external = [['module' => 'legacy', 'connection' => 'mysql', 'table' => 'fun_external_lifecycle', 'primaryKey' => ['id'],
        'columns' => [['name' => 'id', 'type' => 'bigint unsigned', 'nullable' => false]]]];
    $missingCode = 'lifemissing';
    $missingDirectory = $root . '/plugins/' . $missingCode;
    (new PluginScaffolder($root . '/plugins'))->scaffold($missingCode, '外部依赖隔离插件', false, true, true);
    lifecycleManifest($missingDirectory, static function (array &$data) use ($external): void { $data['externalTables'] = $external; });
    try {
        $plugins->installPlugin($missingCode);
        throw new LogicException('外部表缺失时安装未拒绝');
    } catch (\think\db\exception\PDOException | InvalidArgumentException $error) {
        lifecycleExpect(str_contains($error->getMessage(), 'fun_external_lifecycle') || str_contains($error->getMessage(), 'EXTERNAL_TABLE'), '非预期安装错误');
        echo "MISSING EXTERNAL PASS: real install rejected; " . $error->getMessage() . "\n";
    }
    lifecycleExpect(!$plugins->isInstall($missingCode), '拒绝安装不得留下插件记录');
    lifecycleExpect(Db::name('plugin_resource')->where('plugin_code', $missingCode)->count() === 0, '拒绝安装不得发布资源');
    lifecycleExpect(Db::name('system_migration')->where('scope', 'plugin:' . $missingCode)->count() === 0, '拒绝安装不得执行迁移');
    Db::execute('CREATE TABLE fun_external_lifecycle (id bigint unsigned NOT NULL PRIMARY KEY, payload varchar(255) NOT NULL)');
    Db::execute("INSERT INTO fun_external_lifecycle VALUES (7, 'external-sentinel')");
    lifecycleExpect($plugins->installPlugin($missingCode), '外部表补齐后真实安装应通过');
    lifecycleManifest($directory, static function (array &$data) use ($external): void { $data['externalTables'] = $external; });
    $beforeDdl = Db::query('SHOW CREATE TABLE fun_external_lifecycle');
    $beforeRows = Db::query('SELECT * FROM fun_external_lifecycle ORDER BY id');
    try {
        $plugins->purgePluginData($code, $code);
        throw new LogicException('外部表插件 purge 未拒绝');
    } catch (InvalidArgumentException $error) {
        lifecycleExpect($error->getMessage() === 'EXTERNAL_TABLE_PURGE_FORBIDDEN', 'purge 必须因外部依赖拒绝');
    }
    lifecycleExpect(!is_file(runtime_path() . 'purge-hook'), 'purge 不得到达插件钩子');
    lifecycleExpect(Db::query('SHOW CREATE TABLE fun_external_lifecycle') === $beforeDdl && Db::query('SELECT * FROM fun_external_lifecycle ORDER BY id') === $beforeRows, '外部表结构或数据被改变');
    lifecycleExpect(Db::name('plugin_operation')->where('plugin_code', $code)->where('operation', 'purge')->where('result', 'failed')->count() > 0, '缺少真实 purge 失败审计');
    echo "PURGE PASS: PluginService rejected EXTERNAL_TABLE_PURGE_FORBIDDEN; hook not called; external DDL+rows unchanged; failure audited\n";
    echo "business plugin lifecycle integration: PASS\n";
} catch (Throwable $error) {
    $exit = 1;
    // 不打印堆栈参数，避免输出数据库凭据。
    fwrite(STDERR, 'BLOCKED/FAIL: ' . get_class($error) . ': ' . $error->getMessage() . ' at ' . $error->getFile() . ':' . $error->getLine() . "\n");
} finally {
    if ($server && $createdDatabase) {
        $server->exec('DROP DATABASE `' . $name . '`');
        $query = $server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
        $query->execute([$name]);
        lifecycleExpect((int) $query->fetchColumn() === 0, '隔离库清理失败');
        echo "CLEANUP PASS: database {$name} absent\n";
    }
    if ($server && $createdUser) {
        $server->exec("DROP USER '" . $user . "'@'" . $clientHost . "'");
        $query = $server->prepare('SELECT COUNT(*) FROM mysql.user WHERE User = ? AND Host = ?');
        $query->execute([$user, $clientHost]);
        lifecycleExpect((int) $query->fetchColumn() === 0, '隔离账号清理失败');
        echo "CLEANUP PASS: user {$user}@{$clientHost} absent\n";
    }
    if ($createdRoot) {
        lifecycleCleanup($root, $parent, $nonce);
        clearstatcache();
        lifecycleExpect(!file_exists($root), '隔离目录清理失败');
        echo "CLEANUP PASS: root {$root} absent\n";
    }
}
exit($exit);
