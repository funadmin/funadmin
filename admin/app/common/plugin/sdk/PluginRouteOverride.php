<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

use RuntimeException;

/** Applies manifest-declared callback replacements without replacing host files or classes. */
final class PluginRouteOverride
{
    /** @param array<int,array<string,mixed>> $definitions @param array<string,Manifest> $manifests */
    public static function apply(array $definitions, array $manifests): array
    {
        $claims = [];
        foreach ($manifests as $code => $manifest) {
            foreach ((array) ($manifest->toArray()['routeOverrides'] ?? []) as $override) {
                $controller = (string) ($override['controller'] ?? '');
                if (!preg_match('/^app\\\\admin\\\\plugins\\\\' . preg_quote((string) $code, '/') . '\\\\controller\\\\[A-Za-z][A-Za-z0-9_]*$/', $controller)) {
                    throw new RuntimeException('routeOverrides.controller 不属于插件 ' . $code . '：' . $controller);
                }
                if (!class_exists($controller)) {
                    throw new RuntimeException('routeOverrides.controller 不存在：' . $controller);
                }
                $action = (string) ($override['action'] ?? '');
                if (!method_exists($controller, $action) || !(new \ReflectionMethod($controller, $action))->isPublic()) {
                    throw new RuntimeException('routeOverrides.action 不存在或不可公开调用：' . $controller . '::' . $action);
                }
                $path = (string) ($override['path'] ?? '');
                foreach ((array) ($override['methods'] ?? []) as $method) {
                    $method = strtoupper((string) $method);
                    $key = $method . ' ' . $path;
                    if (isset($claims[$key])) {
                        throw new RuntimeException('routeOverrides 目标重复：' . $key);
                    }
                    $index = self::findDefinition($definitions, $method, $path);
                    if ($index === null) {
                        throw new RuntimeException('routeOverrides 目标路由不存在：' . $key);
                    }
                    $claims[$key] = true;
                    $definitions[$index]['callback'] = [$controller, $action];
                }
            }
        }
        return $definitions;
    }

    /** @param array<int,array<string,mixed>> $definitions */
    private static function findDefinition(array $definitions, string $method, string $path): ?int
    {
        foreach ($definitions as $index => $definition) {
            if (($definition['path'] ?? null) !== $path) continue;
            if (in_array($method, array_map('strtoupper', (array) ($definition['methods'] ?? [])), true)) return $index;
        }
        return null;
    }
}
