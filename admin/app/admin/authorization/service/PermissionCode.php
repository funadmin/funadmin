<?php

declare(strict_types=1);

namespace app\admin\authorization\service;

/**
 * 后台权限码的唯一推导规则：每个控制器方法对应一个权限，格式 {模块}:{资源}:{操作}，全部 kebab-case。
 *
 * - 宿主 app\admin\{模块}\controller\{类}：模块为业务目录名，资源为类名去掉 System 前缀与 Controller 后缀。
 * - 插件 app\admin\plugins\{插件}\controller\{类}：模块为插件名，资源规则同上。
 * - 代码生成的业务模块 app\admin\business\{业务模块}\controller\{类}：模块固定为 business（与宿主 development:business:* 不同前缀）。
 * - 控制器位于子目录时，子目录名并入资源名（用连字符连接）。
 * - 操作为方法名。
 *
 * 例：system\controller\SystemConfig::index → system:config:index；
 *     plugins\market\controller\OrderController::close → market:order:close。
 */
final class PermissionCode
{
    private const PATTERN = '/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/';

    public static function derive(string $controller, string $action): ?string
    {
        if ($action === '') {
            return null;
        }
        if (preg_match('/^app\\\\admin\\\\plugins\\\\([a-z][a-z0-9]*)\\\\controller\\\\([A-Za-z0-9_\\\\]+)$/', $controller, $match) === 1) {
            $module = $match[1];
            $class = $match[2];
        } elseif (preg_match('/^app\\\\admin\\\\business\\\\[a-z][a-z0-9_]*\\\\controller\\\\([A-Za-z0-9_]+)$/', $controller, $match) === 1) {
            $module = 'business';
            $class = $match[1];
        } elseif (preg_match('/^app\\\\admin\\\\([a-z][a-z0-9_]*)\\\\controller\\\\([A-Za-z0-9_\\\\]+)$/', $controller, $match) === 1
            && $match[1] !== 'plugins') {
            $module = self::kebab($match[1]);
            $class = $match[2];
        } else {
            return null;
        }
        // 控制器放在子目录（子命名空间）时，子目录名依次并入资源名：controller\\admin\\OrderItem → admin-order-item。
        $parts = explode('\\', $class);
        $resource = preg_replace('/Controller$/', '', (string) array_pop($parts)) ?? '';
        if (preg_match('/^System[A-Z]/', $resource) === 1) {
            $resource = substr($resource, 6);
        }
        $resource = implode('-', array_map([self::class, 'kebab'], array_merge($parts, [$resource])));
        $code = $module . ':' . $resource . ':' . self::kebab($action);
        return preg_match(self::PATTERN, $code) === 1 ? $code : null;
    }

    /** @return array{obj: string, act: string} */
    public static function resource(string $code): array
    {
        [$module, $resource, $action] = explode(':', $code, 3);
        return ['obj' => $module . '/' . $resource, 'act' => $action];
    }

    private static function kebab(string $value): string
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $value) ?? $value;
        $value = preg_replace('/([A-Z])([A-Z][a-z])/', '$1-$2', $value) ?? $value;
        return strtolower(str_replace('_', '-', $value));
    }
}
