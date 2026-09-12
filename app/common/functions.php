<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/10/3
 */
declare(strict_types=1);

// 自动加载 functions 目录下的所有 PHP 文件
$functionsDir = __DIR__ . '/functions/';
if (is_dir($functionsDir)) {
    $files = glob($functionsDir . '*.php');
    foreach ($files as $file) {
        require_once $file;
    }
}