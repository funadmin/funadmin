<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use InvalidArgumentException;

/** Canonical URL namespace for plugin-owned routes. */
final class PluginRouteNamespace
{
    public static function canonical(string $path, ?string $code = null): string
    {
        $path = self::normalize($path);
        foreach (['/admin/plugins/', '/plugins/'] as $prefix) {
            if (!str_starts_with($path, $prefix)) continue;
            $rest = substr($path, strlen($prefix));
            $segments = explode('/', $rest);
            $candidate = (string) ($segments[0] ?? '');
            if ($candidate === '' || preg_match('/^[a-z][a-z0-9]*$/', $candidate) !== 1) {
                throw new InvalidArgumentException('插件路由代码无效：' . $path);
            }
            if ($code !== null && $candidate !== strtolower($code)) {
                throw new InvalidArgumentException('插件路由不属于当前插件：' . $path);
            }
            $base = str_starts_with($prefix, '/admin/') ? '/admin/plugins/' : '/plugins/';
            return $base . $rest;
        }
        if ($code !== null && (str_starts_with($path, '/admin/plugin/') || str_starts_with($path, '/plugin/'))) {
            throw new InvalidArgumentException('插件路由必须使用复数命名空间：' . $path);
        }
        return $path;
    }

    public static function isPluginAdminPath(string $path, ?string $code = null): bool
    {
        $path = self::normalize($path);
        if (!str_starts_with($path, '/admin/plugins/')) return false;
        try {
            self::canonical($path, $code);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private static function normalize(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path[0] !== '/') {
            throw new InvalidArgumentException('插件路由必须是绝对路径：' . $path);
        }
        return '/' . ltrim($path, '/');
    }
}
