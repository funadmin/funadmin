<?php

declare(strict_types=1);

// 不经过 Web 启动器，不加载 .env；协议测试只使用内存数据库。
require dirname(__DIR__) . '/bootstrap.php';
$input = new Symfony\Component\Console\Input\ArrayInput(['action' => 'start', '--transport' => 'stdio']);
$output = new Symfony\Component\Console\Output\ConsoleOutput();
exit((new app\admin\common\command\McpServer())->run($input, $output));
