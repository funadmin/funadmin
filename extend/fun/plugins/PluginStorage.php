<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 管理与插件代码目录解耦的私有运行时存储。 */
final class PluginStorage
{
    public function __construct(private readonly string $root)
    {
    }

    public function path(Manifest $manifest): string
    {
        $relative = (string) ($manifest->toArray()['storage']['path'] ?? '');
        if ($relative === '') {
            throw new RuntimeException('插件未声明 storage.path');
        }
        return rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $manifest->name()
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    public function ensure(Manifest $manifest): string
    {
        $path = $this->path($manifest);
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('无法创建插件 storage：' . $path);
        }
        return $path;
    }

    public function remove(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $name) !== 1) {
            throw new RuntimeException('插件名格式错误');
        }
        $directory = rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        if (!is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $deleted = $item->isDir() && !$item->isLink()
                ? rmdir($item->getPathname())
                : unlink($item->getPathname());
            if (!$deleted) {
                throw new RuntimeException('无法删除插件 storage：' . $item->getPathname());
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('无法删除插件 storage：' . $directory);
        }
    }
}
