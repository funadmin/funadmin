<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/** 将生成清单内的前端路径映射到实际源码目录，私有日志仍保留在后端。 */
final class ArtifactPaths
{
    public static function resolve(string $root, string $path, string $label = '生成路径'): string
    {
        $path = str_replace('\\', '/', $path);
        // 先验证清单路径，不能借助根目录映射绕过 ../ 或绝对路径检查。
        $local = PathGuard::resolve($root, $path, $label);
        if (!str_starts_with($path, 'web/')) return $local;
        $relative = substr($path, 10);
        if (preg_match('~^(?:src/admin/(?:api|views)/generated/|tests/generated/)~D', $relative) !== 1) {
            throw new InvalidArgumentException('前端生成路径必须位于标准 generated 目录');
        }
        $frontend = self::frontendRoot($root);
        self::assertRoot($root, $frontend);
        return PathGuard::resolve($frontend, $relative, $label);
    }

    public static function rootsHash(string $root, array $paths): ?string
    {
        foreach ($paths as $path) {
            if (str_starts_with(str_replace('\\', '/', (string) $path), 'web/')) {
                $frontend = self::frontendRoot($root);
                self::assertRoot($root, $frontend);
                return hash('sha256', CrudDefinition::canonicalJson([rtrim($root, DIRECTORY_SEPARATOR), $frontend]));
            }
        }
        return null;
    }

    public static function assertRoots(string $root, array $paths, mixed $expected): void
    {
        $actual = self::rootsHash($root, $paths);
        if ($actual !== null && (!is_string($expected) || !hash_equals($actual, $expected))) {
            throw new \RuntimeException('生成源码目录已变化，请恢复原目录配置或重新预览');
        }
    }

    public static function relative(string $root, string $absolute): string
    {
        $backend = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolute, $backend)) return PathGuard::relative($root, $absolute);
        $frontend = self::frontendRoot($root);
        $path = 'web/' . PathGuard::relative($frontend, $absolute);
        self::resolve($root, $path);
        return $path;
    }

    /** SafeCommit helper 仍以单一目录 fd 操作两个受限根下的 stage/target/backup。 */
    public static function commitRoot(string $root): string
    {
        $backend = rtrim($root, DIRECTORY_SEPARATOR);
        $frontend = self::frontendRoot($root);
        $common = $backend;
        while ($common !== DIRECTORY_SEPARATOR && $frontend !== $common && !str_starts_with($frontend, $common . DIRECTORY_SEPARATOR)) {
            $parent = dirname($common);
            if ($parent === $common) throw new InvalidArgumentException('生成目录缺少共同根目录');
            $common = $parent;
        }
        return $common;
    }

    private static function frontendRoot(string $root): string
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        if (function_exists('base_path') && realpath($root) === realpath(base_path())) {
            $configured = (string) config('plugins.admin_web_path', dirname($root) . '/web');
            if ($configured !== '') return rtrim($configured, DIRECTORY_SEPARATOR);
        }
        $sibling = dirname($root) . '/web';
        return is_dir($sibling) || is_link($sibling) ? $sibling : $root . '/web';
    }

    private static function assertRoot(string $backend, string $root): void
    {
        if (!str_starts_with($root, DIRECTORY_SEPARATOR) || str_contains($root, "\0")) {
            throw new InvalidArgumentException('前端源码根必须为绝对路径');
        }
        $common = self::commitRoot($backend);
        if ($root !== $common) PathGuard::resolve($common, PathGuard::relative($common, $root), '前端源码根');
        $current = $root;
        while (!is_dir($current) && !is_link($current)) $current = dirname($current);
        if (is_link($current)) throw new InvalidArgumentException('前端源码根禁止符号链接');
        $backendStat = stat($backend);
        $frontendStat = stat($current);
        if ($backendStat !== false && $frontendStat !== false && $backendStat['dev'] !== $frontendStat['dev']) {
            throw new InvalidArgumentException('前后端源码必须位于同一文件系统，以保证原子提交和回滚');
        }
    }
}
