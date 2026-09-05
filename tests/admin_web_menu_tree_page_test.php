<?php

declare(strict_types=1);

$viewFile = dirname(__DIR__) . '/admin-web/src/views/system/menu/index.vue';
$controllerFile = dirname(__DIR__) . '/app/backend/controller/system/SystemMenu.php';
$viewSource = file_get_contents($viewFile);
$controllerSource = file_get_contents($controllerFile);

if ($viewSource === false || $controllerSource === false) {
    throw new RuntimeException('无法读取菜单管理相关文件');
}

if (!str_contains($viewSource, "import { computed, nextTick, onActivated, onMounted, onUnmounted")) {
    throw new RuntimeException('菜单管理页必须在 keep-alive 再次激活时刷新树数据');
}

if (!str_contains($viewSource, 'onActivated(loadData);')) {
    throw new RuntimeException('菜单管理页缺少 onActivated 树数据刷新');
}

if (!str_contains($controllerSource, "#^(?:/[A-Za-z0-9_/-]+|[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*)$#")) {
    throw new RuntimeException('菜单控制器必须允许子菜单使用相对路径');
}

echo "Admin Web menu tree page test passed.\n";
