<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com/
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 */

namespace app\backend\service;

use InvalidArgumentException;

/**
 * 将 ThinkPHP 的应用/多级控制器/操作规范化为 Casbin 资源。
 */
class PermissionResource
{
    public static function fromRequest($request, ?string $app = null): array
    {
        return self::fromParts(
            $app ?: app('http')->getName(),
            (string) $request->controller(true),
            (string) ($request->action() ?: 'index')
        );
    }

    public static function fromRoute(string $module, string $href): ?array
    {
        $href = self::normalizePath($href);
        if ($href === '' || self::isExternal($href)) {
            return null;
        }

        $path = trim((string) (parse_url($href, PHP_URL_PATH) ?: ''), '/');
        $suffix = trim((string) config('route.url_html_suffix', ''));
        if ($suffix !== '' && str_ends_with(strtolower($path), '.' . strtolower($suffix))) {
            $path = substr($path, 0, -strlen($suffix) - 1);
        }
        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn ($item) => $item !== ''));
        $module = self::normalizeSegment($module);
        if (count($segments) >= 3 && self::normalizeSegment($segments[0]) === $module) {
            array_shift($segments);
        }

        if (count($segments) === 1) {
            return self::fromParts($module, $segments[0], 'index');
        }
        if (count($segments) !== 2) {
            return null;
        }

        return self::fromParts($module, $segments[0], $segments[1]);
    }

    public static function fromParts(string $module, string $controller, string $action): array
    {
        $module = self::normalizeSegment($module);
        $controller = self::normalizeController($controller);
        $action = self::normalizeSegment($action ?: 'index');
        if ($module === '' || $controller === '' || $action === '') {
            throw new InvalidArgumentException('权限资源不能为空');
        }

        return [
            'obj' => $module . '/' . $controller,
            'act' => $action,
            'code' => $module . '/' . $controller . ':' . $action,
        ];
    }

    public static function normalizeController(string $controller): string
    {
        $controller = str_replace(['\\', '/'], '.', trim($controller));
        $parts = array_filter(explode('.', $controller), static fn ($item) => $item !== '');
        $normalized = implode('.', array_map([self::class, 'normalizeSegment'], $parts));

        return match ($normalized) {
            'auth.adminauth' => 'adminauth',
            'auth.adminprofile' => 'adminprofile',
            'development.devcrud' => 'devcrud',
            'system.adminupload' => 'adminupload',
            default => preg_replace('/^system\.(system[a-z0-9_]+)$/', '$1', $normalized) ?? $normalized,
        };
    }

    public static function subject(int $adminId): string
    {
        return 'admin:' . $adminId;
    }

    public static function role(int $groupId): string
    {
        return 'role:' . $groupId;
    }

    public static function domain(?string $domain = null): string
    {
        $domain = trim((string) ($domain ?: config('funadmin.auth_domain', 'default')));
        return $domain !== '' ? strtolower($domain) : 'default';
    }

    private static function normalizePath(string $href): string
    {
        return trim(str_replace('\\', '/', trim($href)), '/');
    }

    private static function normalizeSegment(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        return strtolower($value);
    }

    private static function isExternal(string $href): bool
    {
        return preg_match('#^(?:https?:)?//#i', $href) === 1;
    }
}
