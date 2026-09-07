<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;
use JsonException;

/** 将 CRUD AdminWeb 声明确定性合并到 Manifest v2。 */
final class ManifestMerger
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    public function merge(CrudDefinition $definition, bool $adminWeb): string
    {
        $target = (array) $definition->get('target', []);
        $plugin = (string) ($target['plugin'] ?? '');
        $path = PathGuard::resolve($this->projectRoot, "plugins/{$plugin}/plugin.json", '插件目录');
        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('插件 Manifest JSON 无效', 0, $exception);
        }
        if (!is_array($data) || !$adminWeb) {
            return $this->encode($data);
        }

        $entity = (string) $definition->get('entity', '');
        $class = self::studly($entity);
        $permission = $this->listPermission($definition);
        $admin = (array) ($data['adminWeb'] ?? []);
        $admin['components'] = $this->mergeMap((array) ($admin['components'] ?? []), $class, "{$entity}/index.vue", 'component');
        $admin['permissions'] = (array) ($admin['permissions'] ?? []);
        foreach ($this->permissions($definition) as $item) {
            $admin['permissions'] = $this->mergeList($admin['permissions'], $item, 'code', 'permission');
        }
        $route = [
            'path' => "/plugin/{$plugin}/{$entity}",
            'name' => 'Plugin_' . $plugin . '_' . $class,
            'component' => $class,
            'meta' => ['title' => (string) $definition->get('title')],
        ];
        if ($permission !== '') {
            $route['meta']['permission'] = $permission;
        }
        $admin['routes'] = $this->mergeList((array) ($admin['routes'] ?? []), $route, 'path', 'route');
        $menu = (array) $definition->get('menu', []);
        if (($menu['enabled'] ?? false) === true) {
            $menuItem = ['name' => (string) $menu['name'], 'path' => $route['path']];
            if ($permission !== '') {
                $menuItem['permission'] = $permission;
            }
            $admin['menu'] = $this->mergeList((array) ($admin['menu'] ?? []), $menuItem, 'path', 'menu');
        }
        $data['adminWeb'] = $admin;
        return $this->encode($data);
    }

    private function mergeMap(array $items, string $key, string $value, string $label): array
    {
        if (isset($items[$key]) && $items[$key] !== $value) {
            throw new InvalidArgumentException("Manifest {$label} 冲突：{$key}");
        }
        $items[$key] = $value;
        ksort($items, SORT_STRING);
        return $items;
    }

    private function mergeList(array $items, array $candidate, string $key, string $label): array
    {
        foreach ($items as $item) {
            if (!is_array($item) || ($item[$key] ?? null) !== $candidate[$key]) continue;
            if (CrudDefinition::canonicalJson($item) !== CrudDefinition::canonicalJson($candidate)) {
                throw new InvalidArgumentException("Manifest {$label} 冲突：" . $candidate[$key]);
            }
            return $items;
        }
        $items[] = $candidate;
        usort($items, static fn (array $left, array $right): int => strcmp((string) ($left[$key] ?? ''), (string) ($right[$key] ?? '')));
        return $items;
    }

    private function permissions(CrudDefinition $definition): array
    {
        if (((array) $definition->get('permission', []))['enabled'] !== true) {
            return [];
        }
        $prefix = (string) $definition->get('permissionPrefix');
        return array_map(static fn (array $action): array => [
            'code' => $prefix . ':' . $action['codeSuffix'],
            'name' => (string) $action['label'],
        ], (array) $definition->get('permission', [])['actions']);
    }

    private function listPermission(CrudDefinition $definition): string
    {
        $permission = (array) $definition->get('permission', []);
        if (($permission['enabled'] ?? false) !== true) {
            return '';
        }
        foreach ((array) ($permission['actions'] ?? []) as $action) {
            if (($action['action'] ?? '') === 'index') {
                return (string) $definition->get('permissionPrefix') . ':' . $action['codeSuffix'];
            }
        }
        return (string) $definition->get('permissionPrefix') . ':list';
    }

    private function encode(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
