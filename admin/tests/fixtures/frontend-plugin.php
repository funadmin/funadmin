<?php

declare(strict_types=1);

// Frontend contracts must never inherit an external MySQL test switch.
putenv('TEST_MYSQL=0');
require dirname(__DIR__) . '/bootstrap.php';

$root = sys_get_temp_dir() . '/frontend-plugin-' . bin2hex(random_bytes(8));
try {
    (new \app\common\plugin\sdk\PluginScaffolder($root))->scaffold('example');
    $manifest = \app\common\plugin\sdk\Manifest::fromDirectory($root . '/example');
    echo json_encode($manifest->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} finally {
    if (is_dir($root)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($root);
    }
}
