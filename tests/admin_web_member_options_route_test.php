<?php

declare(strict_types=1);

$routeFile = dirname(__DIR__) . '/app/backend/route/app.php';
$routeSource = file_get_contents($routeFile);

if ($routeSource === false) {
    throw new RuntimeException('无法读取后台路由文件');
}

$optionsPosition = strpos($routeSource, "Route::get('system/member/options'");
$indexPosition = strpos($routeSource, "Route::get('system/member', 'system.SystemMember/index')");

if ($optionsPosition === false) {
    throw new RuntimeException('缺少会员选项路由');
}

if ($indexPosition === false) {
    throw new RuntimeException('缺少会员列表路由');
}

if ($optionsPosition > $indexPosition) {
    throw new RuntimeException('会员选项路由必须注册在会员列表路由之前，避免被列表路由截获');
}

echo "Admin Web member options route test passed.\n";
