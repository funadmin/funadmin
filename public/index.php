<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// [ 应用入口文件 ]
namespace think;
use think\route\Url;

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    header("Content-type: text/html; charset=utf-8");
    die('PHP 7.4.0 及以上版本系统才可运行~ ');
}
if (!is_file($_SERVER['DOCUMENT_ROOT'] . '/install.lock')) {
    header('Location: /install.php/index');exit();
}
require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);

