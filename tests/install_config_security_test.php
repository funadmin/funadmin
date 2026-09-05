<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\common\service\InstallSupport;

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$root = dirname(__DIR__);
$db = [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'funadmin',
    'prefix' => 'fun_',
    'username' => 'installer',
    'password' => 'pa\\ss"#;word',
];
$admin = [
    'username' => 'admin',
    'password' => 'admin123',
    'repassword' => 'admin123',
    'email' => 'admin@example.com',
];

$rendered = InstallSupport::renderEnv($root . '/.env.example', null, $db, false);
$parsed = parse_ini_string($rendered, false, INI_SCANNER_RAW);
expect(is_array($parsed), '生成的 .env 必须是有效 INI');
expect(($parsed['DB_PASS'] ?? null) === $db['password'], '数据库密码必须安全编码并可无损读取');
expect(($parsed['API_JWT_SECRET'] ?? null) === '', '全新安装必须保留标准环境配置项');

$existing = "APP_DEBUG = true\nAPI_JWT_SECRET = keep-secret\n";
$merged = InstallSupport::renderEnv($root . '/.env.example', $existing, $db, false);
expect(str_contains($merged, 'API_JWT_SECRET = keep-secret'), '更新 .env 时必须保留自定义配置');
expect((parse_ini_string($merged, false, INI_SCANNER_RAW)['DB_PASS'] ?? null) === $db['password'], '合并更新也必须安全编码数据库密码');

$unsafeDb = $db;
$unsafeDb['password'] = "safe\nINJECTED = true";
expect(InstallSupport::validate($unsafeDb, $admin) === '数据库配置不能包含换行或空字节', '必须拒绝环境变量换行注入');

$config = (string) file_get_contents($root . '/config/database.php');
expect(!str_contains($config, 'Test123456!'), '受版本控制的数据库配置不得包含真实密码');

$normalized = InstallSupport::normalizeDatabaseInput([
    'host' => '127.0.0.1:3307',
    'port' => '3306',
    'database' => 'funadmin',
    'prefix' => 'fun_',
    'username' => 'root',
    'password' => '',
]);
expect($normalized['host'] === '127.0.0.1' && $normalized['port'] === '3307', 'Web 与 CLI 必须共用主机端口归一化');

$paths = InstallSupport::paths($root);
expect($paths['lock'] === $root . '/public/install.lock', '安装锁路径必须由共享内核统一提供');
expect($paths['env'] === $root . '/.env', '.env 路径必须由共享内核统一提供');
expect($paths['migrations'] === $root . '/database/migrations', '迁移目录必须由共享内核统一提供');

$databaseConfig = InstallSupport::databaseConfig($db, ['connections' => ['mysql' => ['break_reconnect' => false]]]);
expect($databaseConfig['default'] === 'mysql', '共享内核必须选中 mysql 连接');
expect($databaseConfig['connections']['mysql']['hostname'] === $db['host'], '共享内核必须应用数据库主机');
expect($databaseConfig['connections']['mysql']['break_reconnect'] === false, '共享内核必须保留已有连接扩展配置');

$cliSource = (string) file_get_contents($root . '/extend/fun/curd/Install.php');
$webSource = (string) file_get_contents($root . '/app/install/controller/Index.php');
foreach (['applyDatabaseConfig', 'writeEnvConfig', 'sqlFileDir', 'envTemplate'] as $duplicateMethod) {
    expect(!str_contains($cliSource, 'function ' . $duplicateMethod), "CLI 不应重复实现 {$duplicateMethod}");
    expect(!str_contains($webSource, 'function ' . $duplicateMethod), "Web 不应重复实现 {$duplicateMethod}");
}
expect(str_contains($cliSource, 'InstallSupport::install('), 'CLI 必须委托共享安装编排');
expect(str_contains($webSource, 'InstallSupport::install('), 'Web 必须委托共享安装编排');
expect(str_contains($cliSource, "'mysql password', ''"), 'CLI 帮助不得泄露当前环境数据库密码');
expect(str_contains($cliSource, "'app_debug', null"), 'CLI 长选项不得使用多字符快捷参数');
expect(str_contains($config, "env('DB_PASS', '')"), '数据库密码默认值必须为空');

$webInstaller = (string) file_get_contents($root . '/app/install/controller/Index.php');
$cliInstaller = (string) file_get_contents($root . '/extend/fun/curd/Install.php');
foreach ([$webInstaller, $cliInstaller] as $installer) {
    expect(!str_contains($installer, 'databaseTpl'), '安装器不得保留 database.php 模板引用');
    expect(!str_contains($installer, 'file_put_contents($this->databaseConfigFile'), '安装器不得写入 config/database.php');
    expect(!str_contains($installer, "view/tpl/env.example"), '安装器必须使用 Web 根目录之外的标准环境模板');
    expect(!str_contains($installer, 'protected string $lockFile'), '安装器路径必须按需计算，不得保留可变属性');
    expect(!str_contains($installer, 'protected string $envFile'), '安装器路径必须按需计算，不得保留可变属性');
    expect(!str_contains($installer, 'protected string $envTpl'), '安装器路径必须按需计算，不得保留可变属性');
    expect(!str_contains($installer, 'protected string $sqlFileDir'), '安装器路径必须按需计算，不得保留可变属性');
    expect(!str_contains($installer, 'protected bool $appDebug'), '调试开关必须作为参数传递，不得保留可变属性');
}
expect(!is_file($root . '/app/install/view/tpl/database.tpl'), '数据库配置模板必须移除');
expect(!is_file($root . '/app/install/view/tpl/env.example'), '环境模板不得存放在 view 目录');

echo "install config security test passed\n";
