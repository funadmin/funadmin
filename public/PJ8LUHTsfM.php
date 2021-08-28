<?php
// [ 应用入口文件 ]
namespace think;
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    header("Content-type: text/html; charset=utf-8");
    exit('PHP 7.2.0 及以上版本系统才可运行~ ');
}
if (!is_file($_SERVER['DOCUMENT_ROOT'].'/install.lock'))
{
    header("location:/install.php");exit;
}
require __DIR__ . '/../vendor/autoload.php';
// 执行HTTP应用并响应
$http = (new  App())->http;
$response = $http->name('backend')->run();
$response->send();
$http->end($response);
?>