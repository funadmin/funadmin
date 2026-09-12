<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use Closure;
use RuntimeException;
use Throwable;
use ZipArchive;

/** 创建可复现插件 ZIP，并在产出后调用安装链完整重验。 */
final class PluginArchiveService
{
    private const FIXED_MTIME = 315532800;

    private readonly Closure $stageVerifier;

    public function __construct(private readonly string $pluginsDirectory, callable $stageVerifier)
    {
        $this->stageVerifier = Closure::fromCallable($stageVerifier);
    }

    public function package(string $name, string $output): array
    {
        PluginScaffolder::assertValidName($name);
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('服务器未安装 ZipArchive 扩展');
        }
        $source = rtrim($this->pluginsDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        Manifest::fromDirectory($source);
        $files = $this->collectFiles($source);
        $output = $this->normalizeOutput($output, $name);
        $temporary = $output . '.tmp-' . bin2hex(random_bytes(5));
        $this->createDirectory(dirname($output));
        try {
            $this->writeArchive($source, $name, $files, $temporary);
            ($this->stageVerifier)($temporary);
            $backup = null;
            if (is_file($output)) {
                $backup = $output . '.backup-' . bin2hex(random_bytes(5));
                if (!rename($output, $backup)) {
                    throw new RuntimeException('无法备份已有插件包：' . $output);
                }
            }
            if (!rename($temporary, $output)) {
                if ($backup !== null) {
                    rename($backup, $output);
                }
                throw new RuntimeException('无法提交插件包：' . $output);
            }
            if ($backup !== null && is_file($backup)) {
                unlink($backup);
            }
        } catch (Throwable $exception) {
            if (is_file($temporary)) {
                unlink($temporary);
            }
            throw $exception;
        }
        return [
            'output' => $output,
            'sha256' => hash_file('sha256', $output),
            'tree_hash' => $this->treeHash($source, $files),
            'files' => $files,
        ];
    }

    /** @return list<string> */
    private function collectFiles(string $source): array
    {
        if (!is_dir($source)) {
            throw new RuntimeException('插件目录不存在：' . $source);
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($source) + 1));
            if ($item->isLink()) {
                throw new RuntimeException('插件目录不允许包含符号链接：' . $relative);
            }
            if ($item->isFile() && !$this->excluded($relative)) {
                $files[] = $relative;
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function excluded(string $relative): bool
    {
        foreach (explode('/', $relative) as $segment) {
            if (in_array($segment, ['.git', '.idea', '.vscode', 'runtime', 'cache'], true)) {
                return true;
            }
        }
        $name = basename($relative);
        return $name === '.DS_Store'
            || $name === '.gitkeep'
            || $name === '.env'
            || str_starts_with($name, '.env.')
            || preg_match('/(?:^|\.)local(?:\.[^.]+)?$/i', $name) === 1;
    }

    /** @param list<string> $files */
    private function writeArchive(string $source, string $name, array $files, string $archive): void
    {
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('无法创建插件包：' . $archive);
        }
        try {
            foreach (['database/migrations', 'resources/public', 'storage'] as $directory) {
                $entry = $name . '/' . $directory;
                $zip->addEmptyDir($entry);
                if (method_exists($zip, 'setMtimeName')) {
                    $zip->setMtimeName($entry . '/', self::FIXED_MTIME);
                }
            }
            foreach ($files as $relative) {
                $file = $source . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $entry = $name . '/' . $relative;
                if (!$zip->addFile($file, $entry)) {
                    throw new RuntimeException('无法归档插件文件：' . $relative);
                }
                if (method_exists($zip, 'setMtimeName')) {
                    $zip->setMtimeName($entry, self::FIXED_MTIME);
                }
                if (method_exists($zip, 'setExternalAttributesName')) {
                    $zip->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, 0100644 << 16);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /** @param list<string> $files */
    private function treeHash(string $source, array $files): string
    {
        $context = hash_init('sha256');
        foreach ($files as $relative) {
            hash_update($context, $relative . "\0" . hash_file('sha256', $source . DIRECTORY_SEPARATOR . $relative) . "\n");
        }
        return hash_final($context);
    }

    private function normalizeOutput(string $output, string $name): string
    {
        if ($output === '') {
            return getcwd() . DIRECTORY_SEPARATOR . $name . '.zip';
        }
        if (is_dir($output) || str_ends_with($output, DIRECTORY_SEPARATOR)) {
            return rtrim($output, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name . '.zip';
        }
        return str_ends_with(strtolower($output), '.zip') ? $output : $output . '.zip';
    }

    private function createDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建输出目录：' . $directory);
        }
    }
}
