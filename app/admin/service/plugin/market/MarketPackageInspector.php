<?php

declare(strict_types=1);

namespace app\admin\service\plugin\market;

use app\admin\plugin\service\PluginPackageService;
use app\market\service\MarketProtocol;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * 上传包检查：复用客户端安装时的 stage 校验（manifest、命名空间、路径穿越等），
 * 确保能发布的包在客户端同样能通过部署前校验。
 */
final class MarketPackageInspector
{
    /**
     * @return array{code:string, code_version:string, name:string, description:string, author:string, requires:array,
     *     sha256:string, size:int, tree_hash:string, database_capability:string, applications:array{app:bool, admin:bool}}
     */
    public function inspect(string $archive): array
    {
        $size = is_file($archive) ? (int) filesize($archive) : 0;
        if ($size <= 0 || $size > MarketProtocol::MAX_PACKAGE_BYTES) {
            throw new RuntimeException('插件包为空或超过 100MB 限制');
        }
        $packages = PluginPackageService::instance();
        $staged = $packages->stage($archive, '', '', false);
        try {
            $manifest = (array) ($staged['manifest'] ?? []);
            $directory = (string) $staged['plugin_directory'];
            $code = (string) ($manifest['code'] ?? '');
            return [
                'code' => $code,
                'code_version' => (string) ($manifest['version'] ?? ''),
                'name' => (string) ($manifest['name'] ?? $code),
                'description' => (string) ($manifest['description'] ?? ''),
                'author' => (string) ($manifest['author'] ?? ''),
                'requires' => (array) ($manifest['requires'] ?? []),
                'sha256' => (string) hash_file('sha256', $archive),
                'size' => $size,
                'tree_hash' => $this->treeHash($directory),
                'database_capability' => $this->databaseCapability($directory, $manifest),
                'applications' => [
                    'app' => is_dir($directory . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $code),
                    'admin' => is_dir($directory . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'admin'),
                ],
            ];
        } finally {
            $packages->discard($staged);
        }
    }

    /** 与客户端部署守卫 maxDatabaseVersion() 相同：migration 目录下按自然序最大的文件名。 */
    private function databaseCapability(string $directory, array $manifest): string
    {
        $relative = (string) ($manifest['migrations']['path'] ?? 'migrations');
        $path = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $versions = array_map(static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), glob($path . DIRECTORY_SEPARATOR . '*.sql') ?: []);
        sort($versions, SORT_NATURAL);
        return $versions === [] ? '' : (string) end($versions);
    }

    /** 与 PluginArchiveService 相同的树哈希格式：按相对路径排序的「路径\0sha256\n」。 */
    private function treeHash(string $directory): string
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($item->getPathname(), strlen($directory) + 1));
            if ($relative !== 'plugin.sig') {
                $files[] = $relative;
            }
        }
        sort($files, SORT_STRING);
        $context = hash_init('sha256');
        foreach ($files as $relative) {
            hash_update($context, $relative . "\0" . hash_file('sha256', $directory . DIRECTORY_SEPARATOR . $relative) . "\n");
        }
        return hash_final($context);
    }
}
