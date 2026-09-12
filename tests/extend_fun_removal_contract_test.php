<?php

declare(strict_types=1);

$root = dirname(__DIR__);

function extendFunRemovalExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

extendFunRemovalExpect(!is_dir($root . '/extend/fun'), 'extend/fun 目录必须彻底删除');

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
extendFunRemovalExpect(!isset($composer['autoload']['psr-0']), 'Composer 不得继续使用 extend PSR-0 自动加载');
extendFunRemovalExpect(
    !str_contains(json_encode($composer['autoload'], JSON_THROW_ON_ERROR), 'app/common'),
    'Composer 自动加载不得继续引用 extend/fun'
);

foreach (['app', 'config', 'plugins', 'tests'] as $relativeRoot) {
    $sourceRoot = $root . '/' . $relativeRoot;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'php' || $file->getPathname() === __FILE__) {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        extendFunRemovalExpect(
            preg_match('/(?:namespace|use|extends|implements|new|instanceof)\s+\\?fun(?:\\|;)/', $source) !== 1,
            'PHP 源码不得继续声明或引用 fun 命名空间：' . $file->getPathname()
        );
        extendFunRemovalExpect(
            !str_contains($source, "'fun\\") && !str_contains($source, '"fun\\'),
            'PHP 字符串类名不得继续引用 fun 命名空间：' . $file->getPathname()
        );
    }
}

echo "extend fun removal contract tests: PASS\n";
