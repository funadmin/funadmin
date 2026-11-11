<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$database = 'webman_console_test_' . bin2hex(random_bytes(6));
$user = 'console_' . bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(24));
$container = 'funadmin-console-test-' . bin2hex(random_bytes(6));
$rootPassword = bin2hex(random_bytes(24));
$redisContainer = 'funadmin-console-redis-' . bin2hex(random_bytes(6));

function executeConsoleTestProcess(array $argv, string $stdin = ''): int
{
    $process = proc_open($argv, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
    if (!is_resource($process)) throw new RuntimeException('无法启动隔离测试进程');
    fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    return proc_close($process);
}

function executeConsoleTestSql(string $sql): int
{
    global $container;
    return executeConsoleTestProcess([
        'docker', 'exec', '-i', $container, 'sh', '-c',
        'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --protocol=TCP --host=127.0.0.1 --user=root --batch',
    ], $sql);
}

$status = 1;
try {
    if (executeConsoleTestProcess([
        'docker', 'run', '--rm', '-d', '--name', $container, '--network', 'funwebman-test',
        '-e', 'MYSQL_ROOT_PASSWORD=' . $rootPassword, 'mysql:8.0',
    ]) !== 0) throw new RuntimeException('无法启动专用 MySQL 测试实例');
    $ready = false;
    for ($attempt = 0; $attempt < 60; $attempt++) {
        sleep(1);
        if (executeConsoleTestProcess([
            'docker', 'exec', $container, 'sh', '-c',
            'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=TCP --host=127.0.0.1 --user=root --batch --execute="SELECT 1" >/dev/null 2>&1',
        ]) === 0) { $ready = true; break; }
    }
    if (!$ready) throw new RuntimeException('专用 MySQL 测试实例启动超时');
    if (executeConsoleTestSql("CREATE DATABASE `$database`; CREATE USER '$user'@'%' IDENTIFIED BY '$password'; GRANT ALL ON `$database`.* TO '$user'@'%'; GRANT ALL ON `funadmin\\_shadow\\_%`.* TO '$user'@'%'; GRANT ALL ON `funadmin\\_backup\\_%`.* TO '$user'@'%';") !== 0) {
        throw new RuntimeException('隔离数据库初始化失败');
    }
    if (executeConsoleTestProcess(['docker', 'run', '--rm', '-d', '--name', $redisContainer, '--network', 'funwebman-test', 'redis:7', 'redis-server', '--save', '', '--appendonly', 'no']) !== 0) {
        throw new RuntimeException('无法启动专用 Redis 测试实例');
    }
    $status = executeConsoleTestProcess([
        'docker', 'run', '--rm', '--network', 'funwebman-test',
        '-v', $root . ':/app', '-v', dirname($root) . '/docs/examples:/docs/examples:ro', '-w', '/app',
        '-e', 'CONSOLE_TEST_MYSQL=1', '-e', 'DB_HOST=' . $container,
        '-e', 'DB_DATABASE=' . $database, '-e', 'DB_USERNAME=' . $user, '-e', 'DB_PASSWORD=' . $password,
        '-e', 'AI_QUEUE_REDIS_URL=redis://' . $redisContainer . ':6379',
        'funwebman-console:8.5', 'php', 'vendor/bin/phpunit',
        '--bootstrap', 'tests/Console/mysql-bootstrap.php', 'tests/Console/MySqlAiQueueTest.php', 'tests/Console/MySqlMcpExecutionTest.php', 'tests/Console/MySqlCrudInspectTest.php', 'tests/Console/MySqlCrudGeneratedExecutionTest.php', 'tests/Console/MySqlPluginCommandTest.php', 'tests/Console/MySqlBusinessGenerationTest.php', 'tests/Console/MySqlUpgradeRecoveryTest.php', 'tests/Console/MySqlAiSandboxConsumerTest.php', 'tests/Console/MySqlAiProfileImageConsumerTest.php', 'tests/Console/MySqlSqlMigrationExecutionTest.php', 'tests/Console/MySqlCrudAdvancedGeneratedTest.php', 'tests/Console/MySqlCrudFormSchemaTest.php', 'tests/Console/MySqlPluginPublicationTest.php', 'tests/Console/MySqlPluginLifecycleTest.php', 'tests/Console/MySqlDocumentSoftDeleteUpgradeTest.php', 'tests/Console/MySqlQaSoftDeleteUpgradeTest.php', 'tests/Console/MySqlOssSoftDeleteUpgradeTest.php', 'tests/Console/MySqlCmsSoftDeleteUpgradeTest.php', 'tests/Console/MySqlBusinessSoftDeleteUpgradeTest.php', 'tests/Console/MySqlFormDataSoftDeleteTest.php',
    ]);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
} finally {
    executeConsoleTestProcess(['docker', 'stop', $redisContainer]);
    if (executeConsoleTestProcess(['docker', 'stop', $container]) !== 0) $status = 1;
}
exit($status);
