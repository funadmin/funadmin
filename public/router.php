<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// $Id$

$requested = $_SERVER["DOCUMENT_ROOT"] . $_SERVER["SCRIPT_NAME"];
if (is_dir($requested)) {
    $requested = rtrim($requested, '/\\') . '/index.html';
}

if (is_file($requested)) {
    // PHP 内置服务器静态响应不带任何缓存头，浏览器启发式缓存会让 index.html
    // 在前端重新构建后仍复用旧 chunk 哈希；开发路由对 HTML 强制重新验证。
    if (substr($requested, -5) === '.html') {
        header('Cache-Control: no-cache, must-revalidate');
        header('Content-Type: text/html; charset=utf-8');
        readfile($requested);
        return true;
    }
    return false;
} else {
    $_SERVER["SCRIPT_FILENAME"] = __DIR__ . '/index.php';

    require __DIR__ . "/index.php";
}

