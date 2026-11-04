<?php

declare(strict_types=1);

// 测试不加载业务 .env，且只允许独立容器中的随机测试库。
if (getenv('TEST_DATABASE') !== '1'
    || getenv('DB_CONNECTION') !== 'mysql'
    || !preg_match('/\Awebman_(?:mysql|console)_test_[a-f0-9]{6,12}\z/D', (string) getenv('DB_DATABASE'))
    || !preg_match('/\Afunadmin-mysql-test-[a-f0-9]{6,12}\z/D', (string) getenv('DB_HOST'))) {
    throw new RuntimeException('仅允许独立 MySQL 测试环境');
}
require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/common.php';
Webman\Config::load(dirname(__DIR__) . '/config', ['route']);
class_exists(support\Db::class);
