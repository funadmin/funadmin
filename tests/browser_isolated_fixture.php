<?php

declare(strict_types=1);

// 用法：prepare；serve <root>；verify <root>；cleanup <root>。仅用于本机隔离验收。
function fixtureAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function fixtureWrite(string $path, string $content): void
{
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
    fixtureAssert(file_put_contents($path, $content) !== false, '隔离文件写入失败');
    chmod($path, 0600);
}

function fixtureAvailablePorts(): array
{
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $backendPort = random_int(20000, 45000);
        $frontendPort = $backendPort + 1;
        $backend = @stream_socket_server('tcp://127.0.0.1:' . $backendPort, $backendError, $backendMessage);
        $frontend = @stream_socket_server('tcp://127.0.0.1:' . $frontendPort, $frontendError, $frontendMessage);
        if (is_resource($backend) && is_resource($frontend)) {
            fclose($backend);
            fclose($frontend);
            return [$backendPort, $frontendPort];
        }
        if (is_resource($backend)) fclose($backend);
        if (is_resource($frontend)) fclose($frontend);
    }
    throw new RuntimeException('无法分配隔离验收端口');
}

function fixtureCopy(string $from, string $to, array $exclude = []): void
{
    fixtureAssert(!is_link($from), '拒绝复制符号链接');
    if (!is_dir($to)) mkdir($to, 0700, true);
    foreach (new DirectoryIterator($from) as $item) {
        if ($item->isDot() || in_array($item->getFilename(), $exclude, true)) continue;
        fixtureAssert(!$item->isLink(), '源码中发现符号链接，拒绝隐式越界');
        $target = $to . '/' . $item->getFilename();
        if ($item->isDir()) fixtureCopy($item->getPathname(), $target, $exclude);
        else fixtureWrite($target, (string) file_get_contents($item->getPathname()));
    }
}

function fixtureRoot(string $root, string $repository): array
{
    fixtureAssert(preg_match('#^' . preg_quote($repository . '/runtime/browser-isolated-', '#') . '([a-f0-9]{12})$#D', $root, $match) === 1, '隔离根目录不合法');
    fixtureAssert(realpath($root) === $root && !is_link($root), '隔离根目录必须为真实目录');
    $state = json_decode((string) file_get_contents($root . '/fixture.json'), true, 512, JSON_THROW_ON_ERROR);
    fixtureAssert($state['nonce'] === $match[1] && $state['database'] === 'funadmin_browser_test_' . $match[1] && $state['user'] === 'fabrowser_' . $match[1], '隔离资源身份不匹配');
    return $state;
}

function fixtureSource(string $repository): array
{
    // 仅实例化空根容器解析环境，绝不 initialize 原项目或执行 migration。
    $app = new think\App($repository . '/runtime/browser-config-reader/');
    $app->env->load($repository . '/.env');
    $config = require $repository . '/config/database.php';
    $source = $config['connections']['mysql'];
    fixtureAssert($source['type'] === 'mysql' && $source['prefix'] === 'fun_' && (int) $source['deploy'] === 0, '仅支持本地单机 MySQL / fun_');
    fixtureAssert(in_array($source['hostname'], ['127.0.0.1', 'localhost', '::1'], true), '拒绝非本地数据库');
    fixtureAssert(preg_match('/^[a-zA-Z0-9_]+$/D', $source['database']) === 1, '源库名称不安全');
    $dsn = 'mysql:host=' . $source['hostname'] . ';port=' . $source['hostport'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $source['username'], $source['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    return [$pdo, $source, $config, $dsn];
}

function fixtureBoot(string $root, string $repository, Composer\Autoload\ClassLoader $loader): think\App
{
    $loader->setPsr4('app\\', [$root . '/app']);
    $loader->setPsr4('plugins\\', [$root . '/plugins']);
    chdir($root);
    $app = new think\App($root . '/');
    $app->initialize();
    fixtureAssert($app->getService('app\\AppService') === null, '禁止启动真实 AppService');
    fixtureAssert(root_path() === $root . '/' && public_path() === $root . '/public/' && app_path() === $root . '/app/', '应用路径未隔离');
    fixtureAssert(!config('captcha.check') && config('funadmin.auth_on'), '隔离验证码/权限配置不符合约束');
    return $app;
}

function fixtureInsert(PDO $pdo, string $table, array $row): void
{
    $fields = array_keys($row);
    $sql = 'INSERT INTO `fun_' . $table . '` (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', array_fill(0, count($fields), '?')) . ')';
    $pdo->prepare($sql)->execute(array_values($row));
}

$repository = dirname(__DIR__);
$loader = require $repository . '/vendor/autoload.php';
require_once $repository . '/vendor/topthink/framework/src/helper.php';
umask(0077);
$command = $argv[1] ?? '';
try {
    if ($command === 'serve') {
        $root = $argv[2] ?? '';
        fixtureRoot($root, $repository);
        fixtureAssert(PHP_SAPI === 'cli', '服务只能由 CLI 启动');
        $state = fixtureRoot($root, $repository);
        // PHP 内置服务器进程仅监听 loopback；请求通过独立 router 启动真实 HTTP 栈。
        $process = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-d', 'zend.exception_ignore_args=1', '-S', '127.0.0.1:' . $state['backendPort'], '-t', $root . '/public', $root . '/router.php'], [STDIN, STDOUT, STDERR], $pipes, $root);
        fixtureAssert(is_resource($process), '后端启动失败');
        exit(proc_close($process));
    }
    if ($command === 'verify') {
        $root = $argv[2] ?? '';
        $state = fixtureRoot($root, $repository);
        $app = fixtureBoot($root, $repository, $loader);
        $db = think\facade\Db::connect();
        fixtureAssert($db->query('SELECT DATABASE() AS db')[0]['db'] === $state['database'], '数据库未隔离');
        $grants = $db->query('SHOW GRANTS');
        fixtureAssert(count($grants) === 2, '账号授权数量异常');
        foreach ($grants as $row) {
            $grant = (string) array_values($row)[0];
            fixtureAssert(str_contains($grant, 'GRANT USAGE ON *.*') || str_contains(str_replace('\\_', '_', $grant), '`' . $state['database'] . '`.*'), '账号越权');
            fixtureAssert(!str_contains($grant, 'ALL PRIVILEGES') && !str_contains($grant, 'GRANT OPTION'), '账号权限过大');
        }
        foreach (['cache', 'session', 'log'] as $kind) fixtureAssert(str_contains(json_encode(config($kind), JSON_UNESCAPED_SLASHES), $root . '/runtime/' . $kind . '/'), $kind . ' 路径未隔离');
        fixtureAssert(think\facade\Db::name('admin')->count() === 2, '随机测试用户数量异常');
        fixtureAssert((fileperms($root . '/credentials.json') & 0777) === 0600, '测试凭据文件权限必须为 0600');
        fixtureAssert((fileperms($root) & 0777) === 0700, '隔离根目录权限必须为 0700');
        $sourceEnv = new think\Env();
        $sourceEnv->load($repository . '/.env');
        $sourceName = (string) $sourceEnv->get('DB_NAME', 'funadmin');
        fixtureAssert(preg_match('/^[a-zA-Z0-9_]+$/D', $sourceName) === 1, '源库标识符不安全');
        $denied = false;
        try { $db->query('SHOW CREATE TABLE `' . $sourceName . '`.`fun_admin`'); }
        catch (Throwable $error) { $denied = str_contains($error->getMessage(), 'denied'); }
        fixtureAssert($denied, '临时账号必须无法访问源库');
        echo "SOURCE ACCESS DENIED PASS; credentials=0600; root=0700\n";
        echo json_encode(['status' => 'PASS', 'database' => $state['database'], 'source' => 'SHOW CREATE TABLE only; no source rows', 'appService' => false, 'root' => $root, 'captchaEnabled' => false, 'authorization' => true, 'restrictedGrants' => $grants], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        exit;
    }
    if ($command === 'cleanup') {
        $root = $argv[2] ?? '';
        $state = fixtureRoot($root, $repository);
        foreach (['backendPort', 'frontendPort'] as $key) {
            $socket = @fsockopen('127.0.0.1', $state[$key], $errno, $error, 0.2);
            if ($socket) { fclose($socket); throw new RuntimeException('请先停止本 fixture 的前后端服务'); }
        }
        [$server] = fixtureSource($repository);
        $server->exec('DROP DATABASE IF EXISTS `' . $state['database'] . '`');
        $server->exec('DROP USER IF EXISTS ' . $server->quote($state['user']) . '@' . $server->quote($state['clientHost']));
        // 严格限定独占根目录，不跟随符号链接；仅供验收结束后显式调用。
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            fixtureAssert($item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname()), '清理失败');
        }
        fixtureAssert(rmdir($root), '根目录清理失败');
        echo "CLEANUP PASS: isolated database, account and root removed\n";
        exit;
    }
    fixtureAssert($command === 'prepare', '用法：prepare | serve ROOT | verify ROOT | cleanup ROOT');
    fixtureAssert(realpath($repository . '/runtime') === $repository . '/runtime', 'runtime 必须为真实目录');
    $nonce = bin2hex(random_bytes(6));
    $root = $repository . '/runtime/browser-isolated-' . $nonce;
    fixtureAssert(!file_exists($root), '隔离目录冲突');
    mkdir($root, 0700);
    [$server, $source, $databaseConfig, $dsn] = fixtureSource($repository);
    $clientIdentity = (string) $server->query('SELECT USER()')->fetchColumn();
    $host = substr($clientIdentity, strrpos($clientIdentity, '@') + 1);
    fixtureAssert($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false, '无法确定安全的 MySQL 客户端地址');
    [$backendPort, $frontendPort] = fixtureAvailablePorts();
    $state = ['nonce' => $nonce, 'database' => 'funadmin_browser_test_' . $nonce, 'user' => 'fabrowser_' . $nonce, 'clientHost' => $host, 'backendPort' => $backendPort, 'frontendPort' => $frontendPort];
    fixtureWrite($root . '/fixture.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    // 白名单空表 + 外键依赖闭包。所有源库操作仅为结构元数据查询。
    $tables = ['admin', 'auth_group', 'auth_group_inherit', 'auth_group_department', 'auth_group_field_permission', 'admin_department', 'permission', 'admin_menu', 'casbin_rule', 'blacklist', 'config', 'admin_log', 'form', 'form_field', 'form_schema_version', 'business_module', 'crud_generation', 'generated_file_baseline', 'plugin', 'plugin_operation', 'plugin_version_history', 'plugin_resource', 'system_migration', 'identity_tenant', 'identity_user', 'identity_credential', 'identity_admin_link', 'identity_user_department'];
    $ddl = [];
    while ($tables) {
        $table = 'fun_' . array_shift($tables);
        if (isset($ddl[$table])) continue;
        fixtureAssert(preg_match('/^fun_[a-z0-9_]+$/D', $table) === 1, '表标识符不安全');
        $sql = array_values($server->query('SHOW CREATE TABLE `' . $source['database'] . '`.`' . $table . '`')->fetch())[1];
        fixtureAssert(!preg_match('/CONNECTION\s*=|DATA DIRECTORY|INDEX DIRECTORY|ENGINE\s*=\s*(FEDERATED|MRG_MYISAM)|REFERENCES\s+`[^`]+`\s*\./i', $sql), '拒绝外部依赖表');
        preg_match_all('/REFERENCES\s+`(fun_[a-z0-9_]+)`/i', $sql, $refs);
        foreach ($refs[1] as $ref) if (!isset($ddl[$ref])) $tables[] = substr($ref, 4);
        $ddl[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $sql);
    }
    $password = bin2hex(random_bytes(24));
    $server->exec('CREATE DATABASE `' . $state['database'] . '` CHARACTER SET utf8mb4');
    $server->exec('CREATE USER ' . $server->quote($state['user']) . '@' . $server->quote($host) . ' IDENTIFIED BY ' . $server->quote($password));
    $server->exec('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON `' . str_replace('_', '\\_', $state['database']) . '`.* TO ' . $server->quote($state['user']) . '@' . $server->quote($host));
    $pdo = new PDO($dsn . ';dbname=' . $state['database'], $state['user'], $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($ddl as $sql) $pdo->exec($sql);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    fixtureCopy($repository . '/app', $root . '/app', ['AppService.php', 'service.php']);
    fixtureCopy($repository . '/config/casbin', $root . '/config/casbin');
    fixtureCopy($repository . '/route', $root . '/route');
    fixtureCopy($repository . '/admin-web/src', $root . '/admin-web/src');
    fixtureCopy($repository . '/admin-web/public', $root . '/admin-web/public');
    foreach (['index.html', 'package.json', 'tsconfig.json', 'vite.config.ts'] as $file) fixtureWrite($root . '/admin-web/' . $file, (string) file_get_contents($repository . '/admin-web/' . $file));
    symlink($repository . '/admin-web/node_modules', $root . '/admin-web/node_modules');
    foreach (['public', 'plugins', 'runtime/cache', 'runtime/session', 'runtime/log', 'runtime/schema', 'database/generated', 'vendor'] as $directory) if (!is_dir($root . '/' . $directory)) mkdir($root . '/' . $directory, 0700, true);
    fixtureWrite($root . '/public/install.lock', 'isolated browser fixture');
    fixtureWrite($root . '/vendor/services.php', "<?php return ['think\\\\annotation\\\\Service', 'think\\\\captcha\\\\CaptchaService', 'think\\\\app\\\\Service'];\n");
    fixtureWrite($root . '/app/service.php', "<?php return [\\app\\common\\plugin\\sdk\\Service::class];\n");
    // 运行时配置不继承原 .env；只保留临时库凭据和测试专用随机密钥。
    $configApp = new think\App($root . '/');
    $source = array_replace($source, ['database' => $state['database'], 'username' => $state['user'], 'password' => $password, 'schema_cache_path' => $root . '/runtime/schema/']);
    $databaseConfig['connections'] = ['mysql' => $source];
    $databaseConfig['default'] = 'mysql';
    $configs = ['database' => $databaseConfig];
    foreach (['app', 'route', 'annotation', 'captcha', 'funadmin', 'view', 'lang', 'form', 'filesystem', 'cookie', 'session'] as $name) $configs[$name] = require $repository . '/config/' . $name . '.php';
    $configs['captcha']['check'] = false;
    $configs['annotation']['model']['enable'] = false;
    $configs['app']['show_error_msg'] = false;
    $configs['app']['app_host'] = 'http://127.0.0.1:' . $state['backendPort'];
    $configs['crud'] = ['confirm_secret' => bin2hex(random_bytes(32)), 'confirm_ttl' => 300, 'connections' => ['mysql']];
    $configs['cache'] = ['default' => 'file', 'stores' => ['file' => ['type' => 'File', 'path' => $root . '/runtime/cache/']]];
    $configs['session'] = array_replace($configs['session'], ['name' => 'BROWSER_' . $nonce, 'type' => 'file', 'path' => $root . '/runtime/session/', 'expire' => 7200]);
    $configs['cookie'] = array_replace($configs['cookie'], ['domain' => '', 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax']);
    $configs['log'] = ['default' => 'file', 'channels' => ['file' => ['type' => 'File', 'path' => $root . '/runtime/log/']]];
    foreach ($configs as $name => $config) fixtureWrite($root . '/config/' . $name . '.php', '<?php return ' . var_export($config, true) . ';');
    $accounts = [];
    foreach ([1 => 'super', 2 => 'reader'] as $id => $kind) {
        $account = ['username' => $kind . '_' . substr($nonce, 0, 8), 'password' => 'T9!' . bin2hex(random_bytes(18))];
        fixtureInsert($pdo, 'admin', ['id' => $id, 'username' => $account['username'], 'password' => password_hash($account['password'], PASSWORD_DEFAULT), 'real_name' => '隔离测试' . $kind, 'status' => 1]);
        fixtureInsert($pdo, 'auth_group', ['id' => $id, 'name' => '隔离角色' . $kind, 'code' => $kind . '_' . $nonce, 'status' => 1, 'level' => $id, 'data_scope' => $id === 1 ? 'all' : 'self']);
        $accounts[$kind] = $account;
    }
    fixtureInsert($pdo, 'identity_tenant', ['id' => 1, 'public_id' => Ramsey\Uuid\Uuid::uuid4()->toString(), 'code' => 'fixture', 'name' => '隔离测试租户', 'status' => 1]);
    fixtureWrite($root . '/credentials.json', json_encode($accounts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    // bootstrap 是测试环境装配，控制器/路由/认证/业务服务全部使用独立的真实源码快照。
    fixtureWrite($root . '/bootstrap.php', '<?php $loader = require ' . var_export($repository . '/vendor/autoload.php', true) . '; $loader->setPsr4("app\\\\", [__DIR__ . "/app"]); $loader->setPsr4("plugins\\\\", [__DIR__ . "/plugins"]); chdir(__DIR__); return new \\think\\App(__DIR__ . "/");');
    fixtureWrite($root . '/router.php', <<<'PHP'
<?php
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_contains($path, '..') || str_contains($path, "\0")) { http_response_code(400); return; }
$file = realpath(__DIR__ . '/public' . $path);
if ($file && str_starts_with($file, __DIR__ . '/public/admin-web/') && is_file($file) && !str_ends_with($file, '.php')) return false;
if (str_starts_with($path, '/admin-web')) {
    $index = __DIR__ . '/public/admin-web/index.html';
    if (is_file($index)) { header('Content-Type: text/html; charset=utf-8'); readfile($index); return; }
}
$app = require __DIR__ . '/bootstrap.php';
$response = $app->http->run();
$response->send();
$app->http->end($response);
PHP);
    fixtureWrite($root . '/admin-web/.env', "VITE_APP_BASE=/admin-web/\nVITE_APP_BASE_API=/admin\nVITE_APP_PROXY_TARGET=http://127.0.0.1:" . $state['backendPort'] . "\nVITE_APP_PORT=" . $state['frontendPort'] . "\nVITE_APP_MOCK=false\n");
    // 原 Vite 配置仅在快照中执行，其 public 输出和 d.ts 均落在独占根目录。
    fixtureWrite($root . '/admin-web/fixture-vite.mjs', "import original from './vite.config.ts';\nexport default (context) => { const config = original(context); config.server.host = '127.0.0.1'; config.server.strictPort = true; config.cacheDir = '../runtime/vite'; return config; };\n");
    $app = fixtureBoot($root, $repository, $loader);
    $casbin = new app\admin\authorization\service\CasbinService();
    $casbin->syncAdminRoles(1, [1]);
    $casbin->syncAdminRoles(2, [2]);
    $permissions = [];
    foreach (['modules', 'module', 'targets', 'fieldcapabilities', 'createvisual', 'databasetables', 'databasetableschema', 'previewformalgeneration', 'formalgeneration', 'generations'] as $action) {
        $permission = app\admin\authorization\model\Permission::create(['app_name' => 'admin', 'code' => 'admin/development.business:' . $action, 'obj' => 'admin/development.business', 'act' => $action, 'name' => $action, 'resource_type' => 'route', 'status' => 1, 'source_type' => 'admin_web', 'source_name' => 'fixture']);
        $permissions[$action] = (int) $permission->id;
    }
    $casbin->syncRolePermissions(2, [$permissions['modules'], $permissions['module'], $permissions['fieldcapabilities']]);
    $menus = [
        [200, 0, 'development', '开发工具', 'Layout', 'Development', 0, '/development/business/mine'],
        [201, 200, 'business', '业务开发', 'Blank', 'BusinessDevelopment', 0, '/development/business/mine'],
        [202, 201, 'mine', '我的业务', 'development/business/mine', 'BusinessMine', $permissions['modules'], ''],
        [203, 201, 'visual', '可视化创建', 'development/business/visual', 'BusinessVisual', $permissions['createvisual'], ''],
        [204, 201, 'database', '从数据库生成', 'development/business/database', 'BusinessDatabase', $permissions['databasetables'], ''],
        [205, 201, 'records', '生成与发布记录', 'development/business/records', 'BusinessRecords', $permissions['generations'], ''],
        [206, 200, 'business/designer', '业务设计器', 'form/designer/index', 'BusinessDesigner', $permissions['createvisual'], ''],
        [300, 0, 'system', '系统管理', 'Layout', 'System', $permissions['formalgeneration'], '/system/plugin'],
        [301, 300, 'plugin', '插件管理', 'system/plugin/index', 'SystemPlugin', $permissions['formalgeneration'], ''],
    ];
    foreach ($menus as [$id, $pid, $href, $name, $component, $routeName, $permissionId, $redirect]) {
        app\admin\authorization\model\AdminMenu::create(['id' => $id, 'pid' => $pid, 'app_name' => 'admin', 'href' => $href, 'name' => $name, 'permission_id' => $permissionId, 'query' => http_build_query(['name' => $routeName, 'component' => $component, 'type' => $redirect ? 'M' : 'C', 'redirect' => $redirect, 'hidden' => $id === 206 ? 'true' : 'false']), 'status' => 1, 'source_type' => 'admin_web', 'source_name' => 'fixture', 'sort_order' => $id]);
    }
    $pluginCode = 'browser' . substr($nonce, 0, 8);
    (new app\common\plugin\sdk\PluginScaffolder($root . '/plugins'))->scaffold($pluginCode, '隔离验收插件', false, true, true);
    $pdo->exec('CREATE TABLE `fun_external_' . $nonce . '` (id bigint unsigned NOT NULL PRIMARY KEY, title varchar(255) NOT NULL)');
    $pdo->exec("INSERT INTO `fun_external_" . $nonce . "` VALUES (1, 'fixture-only-sentinel')");
    $state['plugin'] = $pluginCode;
    $state['externalTable'] = 'fun_external_' . $nonce;
    $state['structureCount'] = count($ddl);
    fixtureWrite($root . '/fixture.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "PREPARED {$root}\nsource=DDL-only; tables=" . count($ddl) . "; credentials=private credentials.json\n";
} catch (Throwable $error) {
    // 不输出异常参数、SQL、原库名称或凭据；保留独占目录供定位/清理。
    fwrite(STDERR, 'BLOCKED: ' . get_class($error) . ' at ' . basename($error->getFile()) . ':' . $error->getLine() . "; no credentials emitted\n");
    if (isset($root)) fwrite(STDERR, 'isolated root: ' . $root . "\n");
    if (isset($table)) fwrite(STDERR, 'structure table: ' . $table . "\n");
    if ($error instanceof RuntimeException && $error->getFile() === __FILE__) fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
