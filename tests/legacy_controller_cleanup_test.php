<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$removedFiles = [
    'app/backend/controller/sys/Attach.php',
    'app/backend/controller/sys/AttachGroup.php',
    'app/backend/controller/sys/Upgrade.php',
    'app/backend/view/sys/attach/add.html',
    'app/backend/view/sys/attach/index.html',
    'app/backend/view/sys/attach/selectfiles.html',
    'app/backend/view/sys/attach_group/add.html',
    'app/backend/view/sys/upgrade/index.html',
];
foreach ($removedFiles as $file) {
    expect(!is_file($root . '/' . $file), "旧后台文件仍存在：{$file}");
}

$references = [
    'config/funadmin.php',
    'database/migrations/002_rbac_schema.sql',
    'extend/fun/crud/Menu.php',
];
foreach ($references as $file) {
    $content = strtolower((string) file_get_contents($root . '/' . $file));
    expect(!str_contains($content, 'sys.attach'), "仍引用旧附件入口：{$file}");
    expect(!str_contains($content, 'sys.upgrade'), "仍引用旧升级入口：{$file}");
}

echo "legacy controller cleanup test passed\n";
