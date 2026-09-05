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
        return is_string($path) ? $this->directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path) : null;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    private static function validate(string $directory, array $data): void
    {
        $schema = __DIR__ . DIRECTORY_SEPARATOR . 'schema' . DIRECTORY_SEPARATOR . 'plugin.schema.json';
        JsonSchemaValidator::fromFile($schema)->validate($data);
        if (basename($directory) !== $data['name']) {
            throw new RuntimeException('插件目录名与 plugin.json name 不一致');
        }
        $expectedClass = 'plugins\\' . $data['name'] . '\\Plugin';
        if (($data['entry']['class'] ?? '') !== $expectedClass) {
            throw new RuntimeException('plugin.json entry namespace 必须是 ' . $expectedClass);
        }
        $entryFile = self::existingRelativeFile($directory, (string) $data['entry']['file'], 'entry.file');
        foreach (['services', 'events', 'routes'] as $type) {
            $path = $data['load'][$type] ?? null;
            if (is_string($path)) {
                self::existingRelativeFile($directory, $path, 'load.' . $type);
            }
        }
        $source = (string) file_get_contents($entryFile);
        if (preg_match('/namespace\s+([^;\s]+)\s*;/i', $source, $matches) !== 1 || $matches[1] !== 'plugins\\' . $data['name']) {
            throw new RuntimeException('Plugin.php namespace 必须是 plugins\\' . $data['name']);
        }
        if (preg_match('/\bclass\s+Plugin\b/', $source) !== 1) {
            throw new RuntimeException('Plugin.php 必须声明 Plugin 类');
        }
        self::validateAdminWeb($directory, $data['admin_web'] ?? null);
        self::validateResourceSources($directory, $data['resources'] ?? []);
        if (isset($data['migrations']['path'])) {
            self::existingRelativeDirectory($directory, (string) $data['migrations']['path'], 'migrations.path');
        }
    }

    private static function validateAdminWeb(string $directory, mixed $adminWeb): void
    {
        if ($adminWeb === null) {
            return;
        }
        $sourceEntry = $directory . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $adminWeb['entry']);
        if (!is_file($sourceEntry)) {
            throw new RuntimeException('plugin.json admin_web.entry 发布源文件不存在：' . $adminWeb['entry']);
        }
    }

    private static function validateResourceSources(string $directory, array $resources): void
    {
        foreach ($resources as $resource) {
            self::existingRelativeDirectory($directory, (string) $resource['source'], 'resources.source');
        }
    }

    private static function existingRelativeFile(string $directory, string $path, string $field): string
    {
        $resolved = self::resolveExistingPath($directory, $path, $field);
        if (!is_file($resolved)) {
            throw new RuntimeException('plugin.json ' . $field . ' 文件不存在：' . $path);
        }
        return $resolved;
    }

    private static function existingRelativeDirectory(string $directory, string $path, string $field): string
    {
        $resolved = self::resolveExistingPath($directory, $path, $field);
        if (!is_dir($resolved)) {
            throw new RuntimeException('plugin.json ' . $field . ' 目录不存在：' . $path);
        }
        return $resolved;
    }

    private static function resolveExistingPath(string $directory, string $path, string $field): string
    {
        $root = realpath($directory);
        $resolved = realpath($directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path));
        if ($root === false || $resolved === false || ($resolved !== $root && !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('plugin.json ' . $field . ' 路径不存在或越界：' . $path);
        }
        return $resolved;
    }
}
