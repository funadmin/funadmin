<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/common.php';

// 默认使用内存连接；MySQL 模式固定使用独立测试库，不加载宿主项目的 .env。
putenv('DB_CONNECTION=' . (getenv('TEST_MYSQL') === '1' ? 'mysql' : 'sqlite'));
putenv('DB_DATABASE=' . (getenv('TEST_MYSQL') === '1' ? 'webman_admin_test' : ':memory:'));
Webman\Config::load(dirname(__DIR__) . '/config', ['route']);
class_exists(support\Db::class);
