<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 将已严格验证的启用插件编译为可被 OPcache 缓存的应用级 PHP 清单。 */
final class PluginRuntimeCache
{
    private const APPLICATIONS = ['api', 'frontend', 'console'];
    private const CONTRACT_VERSION = 2;

    public function __construct(
        private readonly string $pluginsPath,
        private readonly string $runtimePath
    ) {
    }

    /** @param array<string, Manifest> $manifests */
    public function rebuild(array $manifests): void
    {
        if (!is_dir($this->runtimePath) && !mkdir($this->runtimePath, 0755, true) && !is_dir($this->runtimePath)) {
            throw new RuntimeException('无法创建插件运行时缓存目录');
        }
        $stream = fopen($this->runtimePath . DIRECTORY_SEPARATOR . '.compile.lock', 'c+');
        if ($stream === false || !flock($stream, LOCK_EX)) {
            throw new RuntimeException('无法获取插件运行时编译锁');
        }
        try {
            $ordered = (new DependencyValidator('', PHP_VERSION))->topologicalSort($manifests);
            $payloads = array_fill_keys(self::APPLICATIONS, []);
            foreach ($ordered as $code => $manifest) {
                $data = $manifest->toArray();
                $payloads['console'][$code] = $data;
                foreach (['api', 'frontend'] as $application) {
                    if (is_dir($manifest->directory() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $application)) {
                        $payloads[$application][$code] = $data;
                    }
                }
            }
            foreach (['api', 'frontend'] as $application) {
                $payloads[$application] = $this->dependencyClosure($payloads[$application], $ordered);
            }
            foreach ($payloads as $application => $payload) {
                $this->write($application, $payload);
            }
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    /**
     * @param null|callable(): array<string, Manifest> $recover
     * @return array<string, Manifest>
     */
    public function load(string $application, ?callable $recover = null): array
    {
        $this->assertApplication($application);
        $file = $this->file($application);
        if (!is_file($file)) {
            return [];
        }
        $payload = require $file;
        if (!$this->isCurrentPayload($payload)) {
            if ($recover === null) {
                throw new RuntimeException('插件运行时清单契约已过期：' . $application);
            }
            $this->invalidate();
            $this->rebuild($recover());
            $payload = require $file;
            if (!$this->isCurrentPayload($payload)) {
                throw new RuntimeException('插件运行时清单重建后仍无效：' . $application);
            }
        }
        return $this->hydrate($payload['manifests']);
    }

    public function exists(string $application): bool
    {
        $this->assertApplication($application);
        return is_file($this->file($application));
    }

    /** 重建失败时统一失效旧清单，确保运行时不会继续加载数据库已禁用的插件。 */
    public function rebuildOrInvalidate(array $manifests): void
    {
        try {
            $this->rebuild($manifests);
        } catch (\Throwable $exception) {
            $this->invalidate();
            throw $exception;
        }
    }

    /** 删除可能与数据库状态不一致的旧清单，下一请求按 Registry 安全降级。 */
    public function invalidate(): void
    {
        if (!is_dir($this->runtimePath)) {
            return;
        }
        foreach (self::APPLICATIONS as $application) {
            $file = $this->file($application);
            if (is_file($file) && !unlink($file)) {
                throw new RuntimeException('无法失效插件运行时清单：' . $application);
            }
            if (function_exists('opcache_invalidate')) {
                @opcache_invalidate($file, true);
            }
        }
    }

    private function dependencyClosure(array $selected, array $ordered): array
    {
        $include = array_fill_keys(array_keys($selected), true);
        $visit = function (string $code) use (&$visit, &$include, $ordered): void {
            $manifest = $ordered[$code] ?? null;
            if (!$manifest instanceof Manifest) {
                return;
            }
            foreach (array_keys($manifest->dependencies()) as $dependency) {
                if (!isset($include[$dependency])) {
                    $include[$dependency] = true;
                    $visit((string) $dependency);
                }
            }
        };
        foreach (array_keys($selected) as $code) {
            $visit((string) $code);
        }
        $payload = [];
        foreach ($ordered as $code => $manifest) {
            if (isset($include[$code])) {
                $payload[$code] = $manifest->toArray();
            }
        }
        return $payload;
    }

    /** @param array<string, array> $payload @return array<string, Manifest> */
    private function hydrate(array $payload): array
    {
        $manifests = [];
        foreach ($payload as $code => $data) {
            $directory = rtrim($this->pluginsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $code;
            $manifests[$code] = Manifest::fromCompiled($directory, $data);
        }
        return $manifests;
    }

    private function isCurrentPayload(mixed $payload): bool
    {
        if (!is_array($payload)
            || ($payload['contract_version'] ?? null) !== self::CONTRACT_VERSION
            || !is_array($payload['manifests'] ?? null)) {
            return false;
        }
        foreach ($payload['manifests'] as $code => $manifest) {
            if (!is_string($code) || !is_array($manifest) || ($manifest['code'] ?? null) !== $code) {
                return false;
            }
        }
        return true;
    }

    private function write(string $application, array $payload): void
    {
        $file = $this->file($application);
        $temporary = tempnam($this->runtimePath, $application . '.tmp.');
        if ($temporary === false) {
            throw new RuntimeException('无法创建插件运行时临时清单');
        }
        try {
            $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export([
                'contract_version' => self::CONTRACT_VERSION,
                'manifests' => $payload,
            ], true) . ";\n";
            if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $file)) {
                throw new RuntimeException('插件运行时清单原子写入失败：' . $application);
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

    private function file(string $application): string
    {
        return rtrim($this->runtimePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $application . '.php';
    }

    private function assertApplication(string $application): void
    {
        if (!in_array($application, self::APPLICATIONS, true)) {
            throw new RuntimeException('不支持的插件运行时应用：' . $application);
        }
    }
}
