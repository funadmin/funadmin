<?php

declare(strict_types=1);

$migrationFile = dirname(__DIR__) . '/database/migrations/008_admin_web_menu_hierarchy.sql';

if (!is_file($migrationFile)) {
    throw new RuntimeException('缺少双列菜单层级修复迁移');
}

$source = file_get_contents($migrationFile);
if ($source === false) {
    throw new RuntimeException('无法读取双列菜单层级修复迁移');
}

$expectations = [
    "'系统管理'" => '迁移必须创建系统管理一级目录',
    "'component=Layout&name=SystemManagement&type=M&redirect=/system/user'" => '系统管理目录必须声明 Layout 和默认跳转',
    "SET `pid` = @system_menu_id" => '系统业务菜单必须归入系统管理目录',
    "`href` = CASE `id`" => '子菜单必须改为相对路径',
    "WHEN 20 THEN 'dict'" => '字典菜单相对路径错误',
    "WHEN 23 THEN 'user'" => '管理员菜单相对路径错误',
    "WHEN 25 THEN 'log/operation'" => '操作日志菜单相对路径错误',
    "WHEN 20 THEN 'component=system/dict/index&name=SystemDict'" => '字典菜单必须补充页面组件配置',
    "WHEN 33 THEN 'config'" => '配置管理菜单相对路径错误',
];

foreach ($expectations as $needle => $message) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException($message);
    }
}

echo "Admin Web columns menu migration test passed.\n";
