<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.funadmin.com/
 * ----------------------------------------------------------------------------
 * 采用最新webman实现
 * ============================================================================
 */

namespace app\admin\authorization\service;

use InvalidArgumentException;

/**
 * 旧推导规则（升级前的 obj/act/code）：只供历史迁移文件和"旧资源路径 → 推导码"换算使用。
 * 运行时鉴权统一使用 PermissionCode（每个方法一个权限码），不要在新代码中调用本类的推导方法。
 */
class PermissionResource
{
    public static function fromRequest($request, ?string $app = null): array
    {
        $controller = (string) ($request->controller ?? '');
        if (preg_match('/^app\\\\admin\\\\(?:(.+)\\\\)?controller\\\\(.+)$/', $controller, $matches) === 1) {
            $module = $matches[1];
            if (preg_match('/^plugins\\\\([a-z][a-z0-9]*)$/', $module, $plugin) === 1) {
                $module = 'plugin\\' . $plugin[1];
            } elseif (preg_match('/^business\\\\[a-z][a-z0-9_]*$/', $module) === 1) {
                $module = 'business';
            }
            $controller = ($module !== '' ? $module . '\\' : '') . $matches[2];
        }
        return self::fromParts($app ?: 'admin', $controller, (string) ($request->action ?: 'index'));
    }

    public static function fromRoute(string $appName, string $href): ?array
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
        $appName = self::normalizeSegment($appName);
        if (count($segments) >= 3 && self::normalizeSegment($segments[0]) === $appName) {
            array_shift($segments);
        }

        if (count($segments) === 1) {
            return self::fromParts($appName, $segments[0], 'index');
        }
        if (count($segments) !== 2) {
            return null;
        }

        return self::fromParts($appName, $segments[0], $segments[1]);
    }

    public static function canonicalCode(string $code): string
    {
        $code = strtolower(trim($code));
        return str_starts_with($code, 'admin/') ? substr($code, 6) : $code;
    }

    public static function fromParts(string $appName, string $controller, string $action): array
    {
        $appName = self::normalizeSegment($appName);
        $controller = self::normalizeController($controller);
        $action = self::normalizeSegment($action ?: 'index');
        if ($appName === 'admin' && $controller === 'development.business' && $action === 'targets') {
            $action = 'modules';
        }
        if ($appName === 'admin' && $controller === 'systemoperationlog' && $action === 'clear') {
            $action = 'delete';
        }
        if ($appName === 'admin' && $controller === 'form.data' && in_array($action, ['lefttree', 'mutatelefttree', 'lefttreeform'], true)) {
            $action = 'index';
        }
        if ($appName === 'admin' && $controller === 'development.ai') {
            $action = match ($action) {
                'conversationgroupindex' => 'conversationindex',
                'conversationgroupcreate' => 'conversationcreate',
                'conversationgroupupdate', 'conversationstateupdate' => 'conversationupdate',
                'conversationgroupdelete' => 'conversationdelete',
                default => $action,
            };
        }
        if ($appName === '' || $controller === '' || $action === '') {
            throw new InvalidArgumentException('权限资源不能为空');
        }

        return [
            'obj' => $appName . '/' . $controller,
            'act' => $action,
            'code' => self::canonicalCode($controller . ':' . $action),
        ];
    }

    public static function normalizeController(string $controller): string
    {
        $controller = str_replace(['\\', '/'], '.', trim($controller));
        $parts = array_filter(explode('.', $controller), static fn ($item) => $item !== '');
        $normalized = implode('.', array_map([self::class, 'normalizeSegment'], $parts));

        return match ($normalized) {
            'auth.adminauth', 'authentication.adminauth' => 'adminauth',
            'auth.adminprofile', 'authentication.adminprofile' => 'adminprofile',
            'ai.ai' => 'development.ai',
            'development.devcrud' => 'devcrud',
            'plugin.devplugin' => 'development.devplugin',
            'system.adminupload' => 'adminupload',
            default => preg_replace('/^(?:system|authorization)\.(system[a-z0-9_]+)$/', '$1', $normalized) ?? $normalized,
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
