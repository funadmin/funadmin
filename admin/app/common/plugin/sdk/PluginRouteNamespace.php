<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use InvalidArgumentException;

/** Canonical and legacy URL namespaces for plugin-owned admin routes. */
final class PluginRouteNamespace
{
    public static function canonical(string $path, ?string $code = null): string
    {
        $path = self::normalize($path);
        foreach (['/admin/plugin/', '/admin/plugins/', '/plugin/', '/plugins/'] as $prefix) {
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
        return $path;
    }

    public static function legacyAlias(string $path, ?string $code = null): ?string
    {
        $path = self::normalize($path);
        if ($path === '/plugins/hooks/frontend' || $path === '/admin/plugins/hooks/frontend') return null;
        $canonical = self::canonical($path, $code);
        if ($canonical === $path && !self::isPluginPath($path)) return null;
        if (str_starts_with($canonical, '/admin/plugins/')) return '/admin/plugin/' . substr($canonical, strlen('/admin/plugins/'));
        if (str_starts_with($canonical, '/plugins/')) return '/plugin/' . substr($canonical, strlen('/plugins/'));
        return null;
    }

    public static function isPluginAdminPath(string $path, ?string $code = null): bool
    {
        $path = self::normalize($path);
        if (!str_starts_with($path, '/admin/plugin/') && !str_starts_with($path, '/admin/plugins/')) return false;
        try {
            self::canonical($path, $code);
            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private static function isPluginPath(string $path): bool
    {
        return str_starts_with($path, '/admin/plugin/')
            || str_starts_with($path, '/admin/plugins/')
            || str_starts_with($path, '/plugin/')
            || str_starts_with($path, '/plugins/');
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
