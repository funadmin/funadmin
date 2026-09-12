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

    /**
     * 本地签包 v1：域前缀 + 无空白 JSON 二元组列表，路径按 SORT_STRING 排序。
     * 文件记录为 [ZIP完整路径, 原始内容SHA-256小写hex]，显式目录为 [路径/, null]。
     * 仅排除唯一 plugin.json 同目录的 plugin.sig；不包含 ZIP 哈希，避免签名自引用。
     * 签包与验签共用此实现；不读取或执行任何插件 PHP。
     */
    public static function localSignaturePayload(ZipArchive $zip): array
    {
        $paths = $records = [];
        $manifestEntry = null;
        $total = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $path = (string) $zip->getNameIndex($index);
            $directory = str_ends_with($path, '/');
            $name = $directory ? substr($path, 0, -1) : $path;
            if ($name === '' || preg_match('~[\\\\\\x00-\\x1f\\x7f:]~', $name)
                || preg_match('//u', $name) !== 1) {
                throw new RuntimeException('插件包包含非法路径');
            }
            foreach (explode('/', $name) as $segment) {
                if ($segment === '' || $segment === '.' || $segment === '..' || rtrim($segment, '. ') !== $segment) {
                    throw new RuntimeException('插件包包含歧义或穿越路径：' . $path);
                }
            }
            $key = strtolower($name);
            if (isset($paths[$key])) throw new RuntimeException('插件包包含重复路径：' . $path);
            $paths[$key] = $directory ? 'directory' : 'file';
            $opsys = $attributes = 0;
            $zip->getExternalAttributesIndex($index, $opsys, $attributes);
            $type = ($attributes >> 16) & 0170000;
            if ($type !== 0 && $type !== ($directory ? 0040000 : 0100000)) {
                throw new RuntimeException('插件包不允许包含符号链接或特殊文件：' . $path);
            }
            $stat = $zip->statIndex($index);
            if ($stat === false || ($total += (int) $stat['size']) > 524288000) {
                throw new RuntimeException('插件解压后超过 500MB 限制');
            }
            if ($path === 'plugin.json' || preg_match('~^[^/]+/plugin\\.json$~', $path) === 1) {
                if ($manifestEntry !== null) throw new RuntimeException('插件包包含多个 plugin.json');
                $manifestEntry = $path;
            }
            $records[$path] = $directory ? null : $index;
        }
        if ($manifestEntry === null) throw new RuntimeException('插件包缺少 plugin.json');
        $signatureEntry = dirname($manifestEntry) === '.' ? 'plugin.sig' : dirname($manifestEntry) . '/plugin.sig';
        foreach ($paths as $path => $type) {
            for ($parent = dirname($path); $parent !== '.'; $parent = dirname($parent)) {
                if (($paths[$parent] ?? null) === 'file') throw new RuntimeException('插件包包含冲突路径：' . $path);
            }
        }
        unset($records[$signatureEntry]);
        ksort($records, SORT_STRING);
        $entries = [];
        foreach ($records as $path => $index) {
            $digest = null;
            if ($index !== null) {
                $stream = $zip->getStream($path);
                if ($stream === false) throw new RuntimeException('无法读取插件包文件：' . $path);
                try {
                    $hash = hash_init('sha256');
                    $size = hash_update_stream($hash, $stream);
                    $stat = $zip->statIndex($index);
                    if ($size === false || $size !== (int) $stat['size']) throw new RuntimeException('插件包文件读取不完整：' . $path);
                    $digest = hash_final($hash);
                } finally { fclose($stream); }
            }
            $entries[] = [$path, $digest];
        }
        return ['manifest_entry' => $manifestEntry, 'signature_entry' => $signatureEntry,
            'payload' => "funadmin-plugin-files-v1\n" . json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
    }

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
