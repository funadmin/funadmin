<?php

declare(strict_types=1);

// 浏览器验收仅连接隔离测试库，安装状态与配置写入独立临时目录。
if (getenv('TEST_PGSQL') !== '1' || !preg_match('/\Awebman_pg_test_[a-f0-9]{6,12}\z/D', (string) getenv('DB_DATABASE')) || !preg_match('/\Afunadmin-pg-test-[a-f0-9]{6,12}\z/D', (string) getenv('DB_HOST'))) throw new RuntimeException('仅允许 PostgreSQL 浏览器验收环境');
$source = dirname(__DIR__);
$root = sys_get_temp_dir() . '/funadmin-pg-browser-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
foreach (['app','config','database','vendor','support'] as $directory) symlink($source . '/' . $directory, $root . '/' . $directory);
mkdir($root . '/runtime', 0700); mkdir($root . '/runtime/logs', 0700); mkdir($root . '/public', 0700);
copy($source . '/.env.example', $root . '/.env.example');
define('BASE_PATH', $root);
require $source . '/vendor/autoload.php';
require_once $source . '/tests/pgsql-bootstrap.php';
require_once $source . '/app/functions.php';
\Webman\Middleware::load(config('middleware', []));
\Webman\Route::load([config_path()]);
$app = new \Webman\App(\support\Request::class, new \Psr\Log\NullLogger(), app_path(), public_path());
$worker = new \Workerman\Worker('http://0.0.0.0:18987');
$worker->count = 1;
$worker->onWorkerStart = [$app, 'onWorkerStart'];
$worker->onMessage = [$app, 'onMessage'];
\Workerman\Worker::$pidFile = $root . '/runtime/browser.pid';
\Workerman\Worker::$logFile = $root . '/runtime/browser.log';
echo '独立浏览器验收目录：' . $root . PHP_EOL;
$argv = [$argv[0], 'start'];
\Workerman\Worker::runAll();
