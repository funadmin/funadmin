<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/**
 * 对不存在的目标也执行词法路径约束，并拒绝符号链接穿透。
 */
final class PathGuard
{
    public static function resolve(string $root, string $relativePath, string $label = '路径'): string
    {
        if ($relativePath === '' || str_contains($relativePath, "\0") || self::isAbsolute($relativePath)) {
            throw new InvalidArgumentException($label . ' 路径不合法');
        }
        $segments = preg_split('#[\\\\/]#', $relativePath) ?: [];
        if (in_array('..', $segments, true) || in_array('', $segments, true)) {
            throw new InvalidArgumentException($label . ' 路径禁止逃逸');
        }
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/');
        $candidate = $normalizedRoot . '/' . implode('/', $segments);
        self::assertNoSymlink($normalizedRoot, $segments, $label);
        return str_replace('/', DIRECTORY_SEPARATOR, $candidate);
    }

    public static function relative(string $root, string $absolutePath): string
    {
        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $normalizedPath = str_replace('\\', '/', $absolutePath);
        if (!str_starts_with($normalizedPath, $normalizedRoot)) {
            throw new InvalidArgumentException('目标不在项目目录内');
        }
        return substr($normalizedPath, strlen($normalizedRoot));
    }

    private static function assertNoSymlink(string $root, array $segments, string $label): void
    {
        $current = $root;
        foreach ($segments as $segment) {
            $current .= '/' . $segment;
            if (is_link($current)) {
                throw new InvalidArgumentException($label . ' 禁止经过符号链接');
            }
            if (!file_exists($current)) {
                return;
            }
        }
    }

    private static function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\')
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}
