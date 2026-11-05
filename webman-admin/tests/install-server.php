<?php

declare(strict_types=1);

// 仅在隔离容器中使用：源码只读挂载，安装配置与锁写入 tmpfs。
$root = '/sandbox';
if (getenv('INSTALL_HTTP_TEST') !== '1' || !is_dir($root) || is_file($root . '/.env')) {
    throw new RuntimeException('此启动器仅允许在全新隔离测试容器运行');
}
define('BASE_PATH', $root);
foreach (['public', 'runtime'] as $directory) mkdir($root . '/' . $directory, 0777, true);
if (getenv('AUTH_HTTP_TEST') === '1') {
    foreach (['index.html', 'assets', 'favicon.svg'] as $entry) {
        symlink(dirname(__DIR__) . '/public/' . $entry, $root . '/public/' . $entry);
    }
}
foreach (['app', 'config', 'support', 'database'] as $directory) symlink(dirname(__DIR__) . '/' . $directory, $root . '/' . $directory);
copy(dirname(__DIR__) . '/.env.example', $root . '/.env.example');
chdir($root);
require dirname(__DIR__) . '/vendor/autoload.php';
support\App::run();
