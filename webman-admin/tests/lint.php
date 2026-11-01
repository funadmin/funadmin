<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [$root . '/start.php'];
foreach (['app', 'bin', 'config', 'database', 'support', 'tests'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
}
foreach ($files as $file) {
    passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file), $status);
    if ($status !== 0) {
        exit($status);
    }
}
echo '语法检查通过：' . count($files) . " 个 PHP 文件\n";
