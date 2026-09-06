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
    private const CORE_READ_ONLY_PERMISSIONS = [
        'system:plugin:list',
    ];

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

    /** 从安装或启停阶段生成的可信运行时快照恢复，不重复执行磁盘和 Schema 校验。 */
    public static function fromCompiled(string $directory, array $data): self
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (($data['name'] ?? '') === '' || basename($directory) !== $data['name']) {
            throw new RuntimeException('插件运行时快照与目录不一致');
        }
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
                $loadFile = self::existingRelativeFile($directory, $path, 'load.' . $type);
                if ($type === 'routes') {
                    self::validateClosureRouteFile($loadFile, 'load.routes');
                }
            }
        }
        $source = (string) file_get_contents($entryFile);
        if (preg_match('/namespace\s+([^;\s]+)\s*;/i', $source, $matches) !== 1 || $matches[1] !== 'plugins\\' . $data['name']) {
            throw new RuntimeException('Plugin.php namespace 必须是 plugins\\' . $data['name']);
        }
        if (preg_match('/\bclass\s+Plugin\b/', $source) !== 1) {
            throw new RuntimeException('Plugin.php 必须声明 Plugin 类');
        }
        self::validateAdminWeb($directory, $data['adminWeb'] ?? null, $data);
        self::validateResourceSources($directory, $data['resources'] ?? []);
        self::validateChannels($directory, $data['channels'] ?? []);
        if (isset($data['migrations']['path'])) {
            self::existingRelativeDirectory($directory, (string) $data['migrations']['path'], 'migrations.path');
        }
        if (isset($data['storage']['path'])) {
            self::existingRelativeDirectory($directory, (string) $data['storage']['path'], 'storage.path');
        }
        self::validatePurgeContract($entryFile, $data);
    }

    private static function validateAdminWeb(string $directory, mixed $adminWeb, array $data): void
    {
        if ($adminWeb === null) {
            return;
        }
        $sourceRoot = self::existingRelativeDirectory($directory, 'resources/admin', 'adminWeb');
        $declaredFiles = [];
        foreach ((array) ($adminWeb['files'] ?? []) as $file) {
            $resolved = self::existingRelativeFile($sourceRoot, (string) $file, 'adminWeb.files');
            if (is_link($resolved)) {
                throw new RuntimeException('plugin.json adminWeb.files 禁止符号链接');
            }
            $declaredFiles[(string) $file] = true;
        }
        $component = (string) ($adminWeb['component'] ?? '');
        if (!isset($declaredFiles[$component])) {
            throw new RuntimeException('plugin.json adminWeb.component 必须包含在 adminWeb.files 中');
        }
        $name = (string) $data['name'];
        $declared = [];
        foreach ((array) ($adminWeb['permissions'] ?? []) as $permission) {
            $code = (string) ($permission['code'] ?? '');
            if (preg_match('/^' . preg_quote($name, '/') . ':[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/', $code) !== 1) {
                throw new RuntimeException('plugin.json adminWeb.permissions.code 必须属于插件命名空间并使用 name:resource:action 格式：' . $code);
            }
            $declared[$code] = true;
        }
        foreach ((array) ($adminWeb['menu'] ?? []) as $menu) {
            self::validatePermissionReference((string) ($menu['permission'] ?? ''), $declared, 'adminWeb.menu.permission');
        }
        foreach ((array) ($adminWeb['routes'] ?? []) as $route) {
            self::validatePermissionReference((string) ($route['meta']['permission'] ?? ''), $declared, 'adminWeb.routes.meta.permission');
        }
    }

    private static function validatePermissionReference(string $code, array $declared, string $field): void
    {
        if ($code === '' || isset($declared[$code]) || in_array($code, self::CORE_READ_ONLY_PERMISSIONS, true)) {
            return;
        }
        throw new RuntimeException('plugin.json ' . $field . ' 只能引用本插件权限或明确的核心只读权限：' . $code);
    }

    private static function validateChannels(string $directory, array $channels): void
    {
        foreach (['api', 'index'] as $channel) {
            $path = $channels[$channel]['routes'] ?? null;
            if (is_string($path)) {
                $file = self::existingRelativeFile($directory, $path, 'channels.' . $channel . '.routes');
                self::validateClosureRouteFile($file, 'channels.' . $channel . '.routes');
            }
        }
    }

    private static function validateClosureRouteFile(string $file, string $field): void
    {
        $tokens = token_get_all((string) file_get_contents($file));
        $returnFound = false;
        foreach ($tokens as $index => $token) {
            if (!is_array($token) || $token[0] !== T_RETURN) {
                continue;
            }
            for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
                $candidate = $tokens[$cursor];
                if (is_array($candidate) && in_array($candidate[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_STATIC], true)) {
                    continue;
                }
                $returnFound = is_array($candidate) && in_array($candidate[0], [T_FUNCTION, T_FN], true);
                break 2;
            }
        }
        if (!$returnFound) {
            throw new RuntimeException('plugin.json ' . $field . ' 必须直接返回 Closure（return function、static function 或 fn）');
        }
    }

    private static function validatePurgeContract(string $entryFile, array $data): void
    {
        if (($data['purge']['supported'] ?? false) !== true) {
            return;
        }
        $tokens = token_get_all((string) file_get_contents($entryFile));
        if (self::classDeclaresMethod($tokens, 'Plugin', 'purgeData')) {
            return;
        }
        throw new RuntimeException('plugin.json purge.supported=true 时 Plugin 必须 override purgeData');
    }

    private static function classDeclaresMethod(array $tokens, string $className, string $methodName): bool
    {
        $inTargetClass = false;
        $classDepth = 0;
        $awaitingClassName = false;
        $awaitingMethodName = false;
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_CLASS) {
                $awaitingClassName = true;
                continue;
            }
            if ($awaitingClassName && is_array($token) && $token[0] === T_STRING) {
                $inTargetClass = strcasecmp($token[1], $className) === 0;
                $awaitingClassName = false;
                continue;
            }
            if (!$inTargetClass) {
                continue;
            }
            if ($token === '{') {
                $classDepth++;
                continue;
            }
            if ($token === '}') {
                $classDepth--;
                if ($classDepth === 0) {
                    $inTargetClass = false;
                }
                continue;
            }
            if (is_array($token) && $token[0] === T_FUNCTION && $classDepth === 1) {
                $awaitingMethodName = true;
                continue;
            }
            if ($awaitingMethodName && is_array($token) && $token[0] === T_STRING) {
                if (strcasecmp($token[1], $methodName) === 0) {
                    return true;
                }
                $awaitingMethodName = false;
                continue;
            }
            if ($awaitingMethodName && $token === '(') {
                $awaitingMethodName = false;
            }
        }
        return false;
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