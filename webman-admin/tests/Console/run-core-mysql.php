<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$name = 'funadmin-console-test-' . bin2hex(random_bytes(6));
$database = 'webman_console_test_' . bin2hex(random_bytes(6));
$password = bin2hex(random_bytes(24));

function coreTestProcess(array $arguments, string $input = ''): int
{
    $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
    if (!is_resource($process)) throw new RuntimeException('无法启动核心迁移测试进程');
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    return proc_close($process);
}

$status = 1;
$started = false;
try {
    $started = coreTestProcess(['docker', 'run', '--rm', '-d', '--name', $name, '--network', 'funadmin-webman-test', '-e', 'MYSQL_ROOT_PASSWORD=' . $password, 'mysql:8.0']) === 0;
    if (!$started) throw new RuntimeException('无法启动核心迁移测试数据库');
    $ready = false;
    for ($i = 0; $i < 60; $i++) {
        sleep(1);
        if (coreTestProcess(['docker', 'exec', $name, 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=TCP -h127.0.0.1 -uroot -e "SELECT 1" >/dev/null 2>&1']) === 0) { $ready = true; break; }
    }
    if (!$ready) throw new RuntimeException('核心迁移测试数据库启动超时');
    $status = 0;
    foreach (['fun_', 'custom_', ''] as $prefix) {
        $reportArguments = [];
        if ($prefix === 'fun_' && in_array('--reference-report', $argv, true)) {
            $private = $root . '/runtime/private';
            if (!is_dir($private) && !mkdir($private, 0700, true)) throw new RuntimeException('无法创建私有规范结构报告目录');
            $reportFile = 'core-reference-' . bin2hex(random_bytes(8)) . '.json';
            $reportArguments = ['-e', 'CORE_REFERENCE_REPORT_PATH=/app/runtime/private/' . $reportFile];
            echo '规范结构报告：' . $private . '/' . $reportFile . PHP_EOL;
        }
        $sql = "CREATE DATABASE `$database`;";
        if (coreTestProcess(['docker', 'exec', '-i', $name, 'sh', '-c', 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql --protocol=TCP -h127.0.0.1 -uroot'], $sql) !== 0) throw new RuntimeException('创建隔离库失败');
        $status = coreTestProcess([
            'docker', 'run', '--rm', '--network', 'funadmin-webman-test', '-v', $root . ':/app', '-v', dirname($root) . '/database:/core-database:ro', '-w', '/app',
            '-e', 'CONSOLE_TEST_MYSQL=1', '-e', 'DB_HOST=' . $name, '-e', 'DB_DATABASE=' . $database,
            '-e', 'DB_USERNAME=root', '-e', 'DB_PASSWORD=' . $password, '-e', 'DB_PREFIX=' . $prefix,
            '-e', 'CORE_DATABASE_PATH=/core-database', ...$reportArguments, 'funadmin-webman-console:8.5', 'php', '-d', 'memory_limit=512M', 'vendor/bin/phpunit',
            '--bootstrap', 'tests/Console/mysql-bootstrap.php', 'tests/Console/MySqlCoreMigrationTest.php',
        ]);
        if ($status !== 0) break;
        $database = 'webman_console_test_' . bin2hex(random_bytes(6));
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    $status = 1;
} finally {
    if ($started && coreTestProcess(['docker', 'stop', $name]) !== 0) $status = 1;
}
exit($status);
