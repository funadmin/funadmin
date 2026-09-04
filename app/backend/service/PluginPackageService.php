<?php

namespace app\backend\service;

use app\common\service\AbstractService;
use RuntimeException;
use ZipArchive;

/**
 * 插件包暂存、校验与原子替换。
 */
class PluginPackageService extends AbstractService
{
    private const MAX_ARCHIVE_BYTES = 104857600;
    private const MAX_UNPACKED_BYTES = 524288000;
    public function stage(string $archive, string $expectedName = ''): array
    {
        if ($expectedName !== '') {
            $this->assertName($expectedName);
        }
        if (!is_file($archive)) {
            throw new RuntimeException('插件安装包不存在');
        }
        if (filesize($archive) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('插件安装包超过 100MB 限制');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('服务器未安装 ZipArchive 扩展');
        }

        $stageDirectory = runtime_path('plugins' . DIRECTORY_SEPARATOR . 'stage' . DIRECTORY_SEPARATOR . bin2hex(random_bytes(8)));
        $this->createDirectory($stageDirectory);

        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            $this->removeDirectory($stageDirectory);
            throw new RuntimeException('无法打开插件安装包');
        }

        try {
            $unpackedBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $unpackedBytes += (int) ($stat['size'] ?? 0);
                if ($unpackedBytes > self::MAX_UNPACKED_BYTES) {
                    throw new RuntimeException('插件解压后超过 500MB 限制');
                }
                $entry = (string) $zip->getNameIndex($index);
                if ($this->isSymbolicLink($zip, $index)) {
                    throw new RuntimeException('插件包不允许包含符号链接：' . $entry);
                }
                $this->extractEntry($zip, $entry, $stageDirectory);
            }
            $pluginDirectory = $this->locatePluginDirectory($stageDirectory, $expectedName);
            $pluginName = $this->validatePlugin($pluginDirectory, $expectedName);
        } catch (\Throwable $exception) {
            $this->removeDirectory($stageDirectory);
            throw $exception;
        } finally {
            $zip->close();
        }

        return [
            'stage_directory' => $stageDirectory,
            'plugin_directory' => $pluginDirectory,
            'name' => $pluginName,
        ];
    }

    public function deploy(array $staged, string $name): ?string
    {
        $this->assertName($name);
        $source = (string) ($staged['plugin_directory'] ?? '');
        if (!is_dir($source)) {
            throw new RuntimeException('暂存插件目录不存在');
        }

        $target = $this->pluginDirectory($name);
        $backup = null;
        if (is_dir($target)) {
            $backupRoot = runtime_path('plugins' . DIRECTORY_SEPARATOR . 'backup');
            $this->createDirectory($backupRoot);
            $backup = $backupRoot . DIRECTORY_SEPARATOR . $name . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
            if (!rename($target, $backup)) {
                throw new RuntimeException('无法备份当前插件目录');
            }
        }

        $this->createDirectory(dirname($target));
        if (!rename($source, $target)) {
            if ($backup !== null && !rename($backup, $target)) {
                throw new RuntimeException('无法部署插件目录，且旧版本目录恢复失败：' . $backup);
            }
            throw new RuntimeException('无法部署插件目录');
        }

        return $backup;
    }

    public function rollback(string $name, ?string $backup): void
    {
        $target = $this->pluginDirectory($name);
        $this->removeDirectory($target);
        if ($backup !== null && is_dir($backup) && !rename($backup, $target)) {
            throw new RuntimeException('插件更新失败，且旧版本目录恢复失败：' . $backup);
        }
    }

    public function finish(array $staged, ?string $backup): void
    {
        $this->removeDirectory((string) ($staged['stage_directory'] ?? ''));
        if ($backup !== null) {
            $this->removeDirectory($backup);
        }
    }

    public function discard(array $staged): void
    {
        $this->removeDirectory((string) ($staged['stage_directory'] ?? ''));
    }

    private function extractEntry(ZipArchive $zip, string $entry, string $target): void
    {
        $normalized = str_replace('\\', '/', $entry);
        if ($normalized === '' || str_contains($normalized, "\0")) {
            throw new RuntimeException('插件包包含非法文件名');
        }
        if (str_starts_with($normalized, '/') || preg_match('/^[a-zA-Z]:\//', $normalized)) {
            throw new RuntimeException('插件包包含绝对路径：' . $entry);
        }
        foreach (explode('/', trim($normalized, '/')) as $segment) {
            if ($segment === '..') {
                throw new RuntimeException('插件包包含目录穿越路径：' . $entry);
            }
        }

        $destination = $target . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
        if (str_ends_with($normalized, '/')) {
            $this->createDirectory($destination);
            return;
        }

        $this->createDirectory(dirname($destination));
        $stream = $zip->getStream($entry);
        if ($stream === false) {
            throw new RuntimeException('无法读取插件包文件：' . $entry);
        }
        $output = fopen($destination, 'wb');
        if ($output === false) {
            fclose($stream);
            throw new RuntimeException('无法写入插件暂存文件：' . $entry);
        }
        try {
            if (stream_copy_to_stream($stream, $output) === false) {
                throw new RuntimeException('插件包文件解压失败：' . $entry);
            }
        } finally {
            fclose($stream);
            fclose($output);
        }
    }

    private function locatePluginDirectory(string $stageDirectory, string $name): string
    {
        $direct = $stageDirectory . DIRECTORY_SEPARATOR . $name;
        if ($name !== '' && is_dir($direct)) {
            return $direct;
        }
        if ($this->hasManifest($stageDirectory)) {
            return $stageDirectory;
        }

        $directories = array_values(array_filter(glob($stageDirectory . DIRECTORY_SEPARATOR . '*') ?: [], 'is_dir'));
        if (count($directories) === 1 && $this->hasManifest($directories[0])) {
            return $directories[0];
        }
        throw new RuntimeException('插件包目录结构无效');
    }

    private function validatePlugin(string $directory, string $expectedName): string
    {
        $manifest = is_file($directory . DIRECTORY_SEPARATOR . 'plugin.ini')
            ? $directory . DIRECTORY_SEPARATOR . 'plugin.ini'
            : $directory . DIRECTORY_SEPARATOR . 'plugin.ini';
        $info = parse_ini_file($manifest, true, INI_SCANNER_TYPED) ?: [];
        $name = (string) ($info['name'] ?? '');
        $this->assertName($name);
        if ($expectedName !== '' && strcasecmp($name, $expectedName) !== 0) {
            throw new RuntimeException('插件包名称与请求名称不一致');
        }
        if (!is_file($directory . DIRECTORY_SEPARATOR . 'Plugin.php')) {
            throw new RuntimeException('插件入口文件不存在');
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('插件包不允许包含符号链接');
            }
        }
        return $name;
    }

    private function isSymbolicLink(ZipArchive $zip, int $index): bool
    {
        $operations = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $operations, $attributes)) {
            return false;
        }
        return (($attributes >> 16) & 0170000) === 0120000;
    }

    private function hasManifest(string $directory): bool
    {
        return is_file($directory . DIRECTORY_SEPARATOR . 'plugin.ini')
            || is_file($directory . DIRECTORY_SEPARATOR . 'plugin.ini');
    }

    private function pluginDirectory(string $name): string
    {
        return root_path() . 'plugins' . DIRECTORY_SEPARATOR . $name;
    }

    private function assertName(string $name): void
    {
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            throw new RuntimeException('插件名格式错误');
        }
    }

    private function createDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建目录：' . $directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
