<?php

declare(strict_types=1);

// 仅连接显式指定的隔离 PostgreSQL 测试库，不加载业务 .env。
if (getenv('TEST_PGSQL') !== '1'
    || !preg_match('/\Awebman_pg_test_[a-f0-9]{6,12}\z/D', (string) getenv('DB_DATABASE'))
    || !preg_match('/\Afunadmin-pg-test-[a-f0-9]{6,12}\z/D', (string) getenv('DB_HOST'))) {
    throw new RuntimeException('仅允许独立 PostgreSQL 测试库');
}
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/common.php';
putenv('DB_CONNECTION=pgsql');
Webman\Config::load(dirname(__DIR__) . '/config', ['route']);
class_exists(support\Db::class);
