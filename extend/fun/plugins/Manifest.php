<?php

declare(strict_types=1);

namespace fun\plugins;

use JsonException;
use RuntimeException;

/**
 * plugin.json 不可变契约。
 */
final class Manifest
{
    private const LOAD_KEYS = ['services', 'events', 'routes'];

    private function __construct(
        private readonly string $directory,
        private readonly array $data
    ) {
    }

    public static function fromDirectory(string $directory): self
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $file = $directory . DIRECTORY_SEPARATOR . 'plugin.json';
        if (!is_file($file)) {
            throw new RuntimeException('插件缺少 plugin.json：' . $directory);
        }
        if (!is_file($directory . DIRECTORY_SEPARATOR . 'Plugin.php')) {
            throw new RuntimeException('插件缺少 Plugin.php：' . $directory);
        }
        try {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('plugin.json 格式无效：' . $exception->getMessage(), 0, $exception);
        }
        if (!is_array($data)) {
            throw new RuntimeException('plugin.json 根节点必须是对象');
        }
        self::validate($directory, $data);
        return new self($directory, $data);
    }

    public function name(): string
    {
        return $this->data['name'];
    }

    public function title(): string
    {
        return $this->data['title'];
    }

    public function version(): string
    {
        return $this->data['version'];
    }

    public function dependencies(): array
    {
        return $this->data['requires']['plugins'] ?? [];
    }

    public function requirement(string $name): ?string
    {
        $value = $this->data['requires'][$name] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function loadPath(string $type): ?string
    {
        $path = $this->data['load'][$type] ?? null;
        return is_string($path) ? $this->directory . DIRECTORY_SEPARATOR . $path : null;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function validate(string $directory, array $data): void
    {
        if (($data['schema_version'] ?? null) !== 1) {
            throw new RuntimeException('plugin.json schema_version 仅支持 1');
        }
        foreach (['name', 'title', 'version'] as $field) {
            if (!is_string($data[$field] ?? null) || trim($data[$field]) === '') {
                throw new RuntimeException('plugin.json 缺少字段：' . $field);
            }
        }
        if (!preg_match('/^[a-z][a-z0-9]*$/', $data['name'])) {
            throw new RuntimeException('插件 name 仅允许小写字母和数字');
        }
        if (basename($directory) !== $data['name']) {
            throw new RuntimeException('插件目录名与 plugin.json name 不一致');
        }
        if (!preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $data['version'])) {
            throw new RuntimeException('插件 version 必须是语义化版本');
        }
        $requires = $data['requires'] ?? [];
        if (!is_array($requires) || !is_array($requires['plugins'] ?? [])) {
            throw new RuntimeException('plugin.json requires.plugins 必须是对象');
        }
        foreach ($requires['plugins'] as $name => $constraint) {
            if (!preg_match('/^[a-z][a-z0-9]*$/', (string) $name) || !is_string($constraint) || $constraint === '') {
                throw new RuntimeException('plugin.json 插件依赖格式无效');
            }
        }
        $load = $data['load'] ?? [];
        if (!is_array($load) || array_diff(array_keys($load), self::LOAD_KEYS)) {
            throw new RuntimeException('plugin.json 仅允许显式 services/events/routes 加载边界');
        }
        foreach ($load as $path) {
            if (!is_string($path) || $path === '' || str_starts_with($path, '/') || str_contains($path, '..') || str_contains($path, "\0")) {
                throw new RuntimeException('plugin.json 加载路径无效');
            }
            $absolute = $directory . DIRECTORY_SEPARATOR . $path;
            if (!is_file($absolute)) {
                throw new RuntimeException('plugin.json 加载文件不存在：' . $path);
            }
        }
    }
}
