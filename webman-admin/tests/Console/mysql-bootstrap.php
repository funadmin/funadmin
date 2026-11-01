<?php

declare(strict_types=1);

// 独立进程启动，测试不加载 .env，也不复用 Web 服务的连接。
$database = getenv('DB_DATABASE');
if (getenv('CONSOLE_TEST_MYSQL') !== '1'
    || !is_string($database)
    || !preg_match('/^webman_console_test_[a-f0-9]{12}$/D', $database)
    || !preg_match('/^funadmin-console-test-[a-f0-9]{12}$/D', (string) getenv('DB_HOST'))) {
    fwrite(STDERR, "仅允许独立 Console MySQL 测试库\n");
    exit(1);
}
require dirname(__DIR__, 2) . '/vendor/autoload.php';
putenv('DB_CONNECTION=mysql');
putenv('DB_PREFIX=console_');
Webman\Config::load(dirname(__DIR__, 2) . '/config', ['route']);
class_exists(support\Db::class);
