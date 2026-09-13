<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$app = new think\App($root . '/');
$app->initialize();
set_exception_handler(static function (Throwable $error): never {
    fwrite(STDERR, (string) $error . "\n");
    exit(1);
});
$config = (array) config('database.connections.mysql');
$host = (string) $config['hostname'];
$port = (string) ($config['hostport'] ?: '3306');
$user = (string) $config['username'];
$password = (string) $config['password'];
$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'funadmin_acceptance_retirement_' . bin2hex(random_bytes(6));
$failed = 0;

// 凭据仅通过子进程环境传递，禁止将退役测试指向项目数据库。
foreach (['HOST' => $host, 'PORT' => $port, 'USER' => $user, 'PASS' => $password] as $key => $value) {
    putenv('BUSINESS_PERMISSION_TEST_DB_' . $key . '=' . $value);
}
$server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    putenv("LEGACY_FORM_RETIREMENT_TEST_DSN=mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4");
    putenv('LEGACY_FORM_RETIREMENT_TEST_USER=' . $user);
    putenv('LEGACY_FORM_RETIREMENT_TEST_PASS=' . $password);
    foreach (['business_development_permission_test.php', 'business_legacy_form_origin_retirement_test.php'] as $test) {
        echo "=== {$test}: isolated integration enabled ===\n";
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($root . '/tests/' . $test), $status);
        echo "EXIT={$status}\n";
        $failed += $status === 0 ? 0 : 1;
    }
} finally {
    $server->exec("DROP DATABASE IF EXISTS `{$database}`");
    echo "Isolated retirement database dropped: {$database}\n";
}
echo 'OPTIONAL_INTEGRATIONS passed=' . (2 - $failed) . ' failed=' . $failed . " total=2\n";
exit($failed === 0 ? 0 : 1);
