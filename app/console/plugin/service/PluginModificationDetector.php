<?php

declare(strict_types=1);

namespace app\console\plugin\service;

use app\console\plugin\contract\PluginResourceRepository;
use app\console\plugin\repository\DatabasePluginResourceRepository;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** 只读检查已登记插件发布目标是否发生本地变化。 */
final class PluginModificationDetector
{
    private const CONSOLE_LAYERS = ['controller', 'model', 'service', 'validate', 'middleware'];
    private ?array $registry = null;

    public function __construct(
        private readonly string $appRoot,
        private readonly string $publicRoot,
        private readonly string $adminWebRoot,
        private readonly PluginResourceRepository $repository
    ) {
    }

    public static function create(): self
    {
        return new self(
            root_path('app'),
            public_path(),
            root_path('admin-web'),
            new DatabasePluginResourceRepository()
        );
    }

    public function isModified(string $pluginCode): bool
    {
        $this->assertPluginCode($pluginCode);
        $records = array_values(array_filter(
            $this->registry ??= $this->repository->all(),
            static fn (array $record): bool => ($record['plugin_code'] ?? '') === $pluginCode
                && in_array(($record['resource_type'] ?? ''), ['file', 'native_app'], true)
        ));
        if ($records === []) {
            return false;
        }

        $registeredFiles = [];
        $nativeUnits = [];
        foreach ($records as $record) {
            $targetPath = (string) ($record['target_path'] ?? '');
            try {
                $target = $this->resolveTarget($targetPath, $pluginCode);
            } catch (RuntimeException) {
                return true;
            }
            $registeredFiles[$target] = true;
            if (!is_file($target) || is_link($target)) {
                return true;
            }
            $actual = hash_file('sha256', $target);
            if ($actual === false || !hash_equals((string) ($record['sha256'] ?? ''), $actual)) {
                return true;
            }
            if (($record['resource_type'] ?? '') === 'native_app') {
                $unit = (string) ($record['publication_unit'] ?? '');
                if ($unit === '') {
                    return true;
                }
                $nativeUnits[$unit] = (string) ($record['tree_hash'] ?? '');
            }
        }

        foreach ($nativeUnits as $unit => $expectedTreeHash) {
            $root = $this->nativeUnitRoot($unit, $pluginCode);
            if (!is_dir($root) || $expectedTreeHash === '' || !hash_equals($expectedTreeHash, $this->treeHash($root))) {
                return true;
            }
        }
        foreach ($this->filePublicationRoots($pluginCode, $records) as $root) {
            foreach ($this->files($root) as $file) {
                if (!isset($registeredFiles[$file])) {
                    return true;
                }
            }
        }
        return false;
    }

    private function resolveTarget(string $targetPath, string $pluginCode): string
    {
        if (preg_match('~^public:plugin-assets/' . preg_quote($pluginCode, '~') . '/(.+)$~', $targetPath, $match) === 1) {
            return $this->safeTarget($this->publicRoot, 'plugin-assets/' . $pluginCode . '/' . $match[1]);
        }
        if (preg_match('~^admin-web:src/modules/' . preg_quote($pluginCode, '~') . '/(.+)$~', $targetPath, $match) === 1) {
            return $this->safeTarget($this->adminWebRoot, 'src/modules/' . $pluginCode . '/' . $match[1]);
        }
        if (preg_match('~^application:' . preg_quote($pluginCode, '~') . '/(.+)$~', $targetPath, $match) === 1) {
            return $this->safeTarget($this->appRoot, $pluginCode . '/' . $match[1]);
        }
        if (preg_match('~^console-plugin:([a-z]+):' . preg_quote($pluginCode, '~') . '/(.+)$~', $targetPath, $match) === 1
            && in_array($match[1], self::CONSOLE_LAYERS, true)) {
            return $this->safeTarget($this->appRoot, 'console/' . $match[1] . '/plugin/' . $pluginCode . '/' . $match[2]);
        }
        throw new RuntimeException('插件 registry 目标路径无效：' . $targetPath);
    }

    private function nativeUnitRoot(string $unit, string $pluginCode): string
    {
        if ($unit === 'application:' . $pluginCode) {
            return $this->safeTarget($this->appRoot, $pluginCode);
        }
        if (preg_match('/^console-plugin:([a-z]+):' . preg_quote($pluginCode, '/') . '$/', $unit, $match) === 1
            && in_array($match[1], self::CONSOLE_LAYERS, true)) {
            return $this->safeTarget($this->appRoot, 'console/' . $match[1] . '/plugin/' . $pluginCode);
        }
        throw new RuntimeException('插件 registry publication unit 无效：' . $unit);
    }

    private function filePublicationRoots(string $pluginCode, array $records): array
    {
        $roots = [];
        foreach ($records as $record) {
            if (($record['resource_type'] ?? '') !== 'file') {
                continue;
            }
            $targetPath = (string) ($record['target_path'] ?? '');
            if (str_starts_with($targetPath, 'public:plugin-assets/' . $pluginCode . '/')) {
                $roots[$this->safeTarget($this->publicRoot, 'plugin-assets/' . $pluginCode)] = true;
            } elseif (str_starts_with($targetPath, 'admin-web:src/modules/' . $pluginCode . '/')) {
                $roots[$this->safeTarget($this->adminWebRoot, 'src/modules/' . $pluginCode)] = true;
            }
        }
        return array_keys($roots);
    }

    private function treeHash(string $directory): string
    {
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                return '';
            }
            $relative = substr($item->getPathname(), strlen($directory) + 1);
            if ($item->isDir()) {
                $hashes[] = 'd' . "\0" . $relative;
                continue;
            }
            $hash = hash_file('sha256', $item->getPathname());
            if ($hash === false) {
                return '';
            }
            $hashes[] = 'f' . "\0" . $relative . "\0" . $hash;
        }
        sort($hashes, SORT_STRING);
        return hash('sha256', implode("\n", $hashes));
    }

    private function files(string $directory): array
    {
        if (!is_dir($directory) || is_link($directory)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                return [''];
            }
            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function safeTarget(string $root, string $relative): string
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\')
            || str_starts_with($relative, '/') || preg_match('~(^|/)\.\.?(/|$)~', $relative) === 1
            || is_link($root)) {
            throw new RuntimeException('插件 registry 目标路径越界');
        }
        $rootReal = realpath($root);
        if ($rootReal === false) {
            throw new RuntimeException('插件 registry 根目录不存在');
        }
        $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $current = $root;
        foreach (explode('/', $relative) as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new RuntimeException('插件 registry 目标路径禁止符号链接');
            }
        }
        $targetReal = realpath($target);
        if ($targetReal !== false && $targetReal !== $rootReal
            && !str_starts_with($targetReal, $rootReal . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('插件 registry 目标路径越界');
        }
        return $target;
    }

    private function assertPluginCode(string $pluginCode): void
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $pluginCode) !== 1) {
            throw new RuntimeException('插件标识不合法');
        }
    }
}
