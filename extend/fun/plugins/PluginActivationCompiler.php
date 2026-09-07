<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 将数据库生命周期记录与已验证 Manifest v2 快照编译为可信激活清单。 */
final class PluginActivationCompiler
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly string $runtimePath,
        private readonly string $pluginsPath
    ) {
    }

    /**
     * @param array<string, array> $records
     * @param array<string, array> $manifests
     */
    public function compile(array $records, array $manifests): array
    {
        $this->ensureDirectory($this->runtimePath);
        $lock = fopen($this->runtimePath . DIRECTORY_SEPARATOR . '.compile.lock', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            throw new RuntimeException('无法获取插件激活清单编译锁');
        }
        try {
            $generation = bin2hex(random_bytes(16));
            $plugins = $this->plugins($records, $manifests);
            $payload = [
                'schema_version' => self::SCHEMA_VERSION,
                'generation' => $generation,
                'plugins' => $plugins,
            ];
            $payload['integrity_hash'] = self::integrityHash($payload);
            $directory = $this->runtimePath . DIRECTORY_SEPARATOR . 'generations' . DIRECTORY_SEPARATOR . $generation;
            $this->ensureDirectory($directory);
            $file = $directory . DIRECTORY_SEPARATOR . 'activation.php';
            $this->writeAtomic($file, "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n");
            $this->writeAtomic($this->runtimePath . DIRECTORY_SEPARATOR . 'active', $generation . "\n");
            return $payload;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function integrityHash(array $payload): string
    {
        return hash('sha256', serialize($payload));
    }

    private function plugins(array $records, array $manifests): array
    {
        ksort($records, SORT_STRING);
        $plugins = [];
        foreach ($records as $key => $record) {
            $code = (string) ($record['code'] ?? $key);
            if (preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1) {
                continue;
            }
            $manifest = is_array($manifests[$code] ?? null) ? $manifests[$code] : [];
            $dependencies = array_keys((array) ($manifest['requires']['plugins'] ?? []));
            sort($dependencies, SORT_STRING);
            $base = rtrim($this->pluginsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $code . DIRECTORY_SEPARATOR . 'app';
            $plugins[$code] = [
                'code' => $code,
                'state' => (string) ($record['lifecycle_state'] ?? 'discovered'),
                'enabled' => (int) ($record['status'] ?? 0) === 1,
                'needs_reinstall' => (int) ($record['needs_reinstall'] ?? 0) === 1,
                'operation_token' => $this->token($record['operation_token'] ?? null),
                'version' => (string) ($record['version'] ?? ''),
                'package_hash' => (string) ($record['package_hash'] ?? ''),
                'dependencies' => $dependencies,
                'applications' => [
                    'app' => is_dir($base . DIRECTORY_SEPARATOR . $code),
                    'console' => is_dir($base . DIRECTORY_SEPARATOR . 'console'),
                ],
            ];
        }
        return $plugins;
    }

    private function token(mixed $token): ?string
    {
        $token = trim((string) ($token ?? ''));
        return $token === '' ? null : $token;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建插件激活清单目录');
        }
    }

    private function writeAtomic(string $file, string $content): void
    {
        $temporary = tempnam(dirname($file), basename($file) . '.tmp.');
        if ($temporary === false) {
            throw new RuntimeException('无法创建插件激活清单临时文件');
        }
        try {
            if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $file)) {
                throw new RuntimeException('插件激活清单原子写入失败');
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
