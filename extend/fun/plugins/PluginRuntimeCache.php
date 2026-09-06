<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 将已严格验证的启用插件编译为可被 OPcache 缓存的应用级 PHP 清单。 */
final class PluginRuntimeCache
{
    private const APPLICATIONS = ['api', 'frontend', 'console'];

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
            foreach ($ordered as $name => $manifest) {
                $data = $manifest->toArray();
                $payloads['console'][$name] = $data;
                foreach (['api', 'frontend'] as $application) {
                    if (isset($data['load']['routes']) || isset($data['channels'][$application]['routes'])
                        || isset($data['load']['services']) || isset($data['load']['events'])) {
                        $payloads[$application][$name] = $data;
                    }
                }
            }
            foreach ($payloads as $application => $payload) {
                $this->write($application, $payload);
            }
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    /** @return array<string, Manifest> */
    public function load(string $application): array
    {
        $this->assertApplication($application);
        $file = $this->file($application);
        if (!is_file($file)) {
            return [];
        }
        $payload = require $file;
        if (!is_array($payload)) {
            throw new RuntimeException('插件运行时清单格式无效：' . $application);
        }
        $manifests = [];
        foreach ($payload as $name => $data) {
            if (!is_string($name) || !is_array($data)) {
                throw new RuntimeException('插件运行时清单内容无效：' . $application);
            }
            $directory = rtrim($this->pluginsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
            $manifests[$name] = Manifest::fromCompiled($directory, $data);
        }
        return $manifests;
    }

    public function exists(string $application): bool
    {
        $this->assertApplication($application);
        return is_file($this->file($application));
    }

    /** 删除可能与数据库状态不一致的旧清单，下一请求按 Registry 安全降级。 */
    public function invalidate(): void
    {
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

    private function write(string $application, array $payload): void
    {
        $file = $this->file($application);
        $temporary = tempnam($this->runtimePath, $application . '.tmp.');
        if ($temporary === false) {
            throw new RuntimeException('无法创建插件运行时临时清单');
        }
        try {
            $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($payload, true) . ";\n";
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
