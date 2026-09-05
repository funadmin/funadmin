<?php

declare(strict_types=1);

$migrationFile = dirname(__DIR__) . '/database/migrations/011_admin_web_menu_icons.sql';
if (!is_file($migrationFile)) {
    throw new RuntimeException('缺少 Admin Web 菜单图标修复迁移');
}

$source = file_get_contents($migrationFile);
if ($source === false) {
    throw new RuntimeException('无法读取 Admin Web 菜单图标修复迁移');
}

$expectations = [
    "WHEN 'blacklist' THEN 'i-ep-warning'" => '黑名单菜单必须使用已生成的图标',
    "WHEN 'member_level' THEN 'i-ep-histogram'" => '会员等级菜单必须使用已生成的图标',
    "WHEN 'plugin_center' THEN 'i-ep-grid'" => '插件中心必须使用已生成的图标',
    "COALESCE(NULLIF(TRIM(`icon`), ''), '') = ''" => '迁移必须覆盖空图标记录',
    "ELSE 'i-ep-menu'" => '未知菜单必须使用默认图标兜底',
];

foreach ($expectations as $needle => $message) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException($message);
    }
}

echo "Admin Web menu icons migration test passed.\n";
