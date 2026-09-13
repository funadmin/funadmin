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

    private array $conflictPaths = [];
    private bool $planning = false;

    public function plan(CrudDefinition $definition, array $base = []): array
    {
        $this->planning = true;
        $this->conflictPaths = [];
        try {
            $content = $this->merge($definition, true, $base);
            $remote = $this->projection($definition);
            $path = 'plugins/' . $definition->get('target')['plugin'] . '/plugin.json';
            $local = json_decode((string) file_get_contents(PathGuard::resolve($this->projectRoot, $path, '插件目录')), true, 512, JSON_THROW_ON_ERROR);
            return ['content' => $content, 'conflictPaths' => $this->conflictPaths,
                'baseContent' => $this->encode($base),
                'localContent' => $this->encode($this->ownedProjection($local, $base, $remote)),
                'remoteContent' => $this->encode($remote)];
        } finally { $this->planning = false; }
    }

    public function ownedProjection(array $local, array $base, array $remote): array
    {
        $result = [];
        foreach (['components' => null, 'permissions' => 'code', 'routes' => 'path', 'menu' => 'path', 'externalTables' => 'module'] as $section => $key) {
            $a = $section === 'externalTables' ? ($base[$section] ?? []) : ($base['adminWeb'][$section] ?? []);
            $b = $section === 'externalTables' ? ($remote[$section] ?? []) : ($remote['adminWeb'][$section] ?? []);
            $items = $section === 'externalTables' ? ($local[$section] ?? []) : ($local['adminWeb'][$section] ?? []);
            $ids = $key === null ? array_unique(array_merge(array_keys($a), array_keys($b))) : array_unique(array_merge(array_column($a, $key), array_column($b, $key)));
            $selected = $key === null ? array_intersect_key($items, array_flip($ids)) : array_values(array_filter($items, static fn (array $item): bool => in_array($item[$key] ?? null, $ids, true)));
            if ($section === 'externalTables') { if ($ids !== []) $result[$section] = $selected; }
            else $result['adminWeb'][$section] = $selected;
        }
        return $result;
    }

    public function merge(CrudDefinition $definition, bool $adminWeb, array $base = []): string
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

        $remote = $this->projection($definition);
        $this->assertProjectionScope($definition, $base);
        foreach (['components' => null, 'permissions' => 'code', 'routes' => 'path', 'menu' => 'path'] as $section => $key) {
            $data['adminWeb'][$section] = $this->mergeOwned(
                (array) ($data['adminWeb'][$section] ?? []),
                (array) ($base['adminWeb'][$section] ?? []),
                (array) ($remote['adminWeb'][$section] ?? []), $key, 'adminWeb.' . $section
            );
        }
        if (isset($remote['externalTables']) || isset($base['externalTables'])) {
            $data['externalTables'] = $this->mergeOwned((array) ($data['externalTables'] ?? []),
                (array) ($base['externalTables'] ?? []), (array) ($remote['externalTables'] ?? []), 'module', 'externalTables');
        }
        return $this->encode($data);
    }

    /** 仅返回该业务受管键，不保存整个插件 Manifest 的所有权。 */
    public function projection(CrudDefinition $definition): array
    {
        $data = [];
        $plugin = (string) $definition->get('target')['plugin'];
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
        if (($definition->get('formSchema', [])['database']['source'] ?? '') === 'adopted') {
            $data['externalTables'] = $this->mergeList((array) ($data['externalTables'] ?? []),
                \app\common\plugin\sdk\ExternalTableRequirements::fromDefinition($definition), 'module', 'externalTable');
        }
        return $data;
    }

    private function assertProjectionScope(CrudDefinition $definition, array $base): void
    {
        $entity = (string) $definition->get('entity');
        $plugin = (string) $definition->get('target')['plugin'];
        if (array_diff(array_keys($base), ['adminWeb', 'externalTables']) !== []
            || array_diff(array_keys($base['adminWeb'] ?? []), ['components', 'permissions', 'routes', 'menu']) !== []) {
            throw new InvalidArgumentException('BUSINESS_ARTIFACT_PATH_FORBIDDEN');
        }
        foreach ($base['adminWeb']['components'] ?? [] as $name => $value) {
            if ($name !== self::studly($entity) || $value !== $entity . '/index.vue') throw new InvalidArgumentException('BUSINESS_ARTIFACT_PATH_FORBIDDEN');
        }
        foreach (['routes', 'menu'] as $section) {
            foreach ($base['adminWeb'][$section] ?? [] as $item) {
                if (($item['path'] ?? '') !== '/plugin/' . $plugin . '/' . $entity) throw new InvalidArgumentException('BUSINESS_ARTIFACT_PATH_FORBIDDEN');
            }
        }
        foreach ($base['adminWeb']['permissions'] ?? [] as $item) {
            if (!str_starts_with((string) ($item['code'] ?? ''), $plugin . ':' . $entity . ':')) throw new InvalidArgumentException('BUSINESS_ARTIFACT_PATH_FORBIDDEN');
        }
        foreach ($base['externalTables'] ?? [] as $item) {
            if (($item['module'] ?? '') !== $entity) throw new InvalidArgumentException('BUSINESS_ARTIFACT_PATH_FORBIDDEN');
        }
    }

    /** 对受管键做三方比较；未受管键原样保留，同键人工修改拒绝覆盖。 */
    private function mergeOwned(array $local, array $base, array $remote, ?string $key, string $section): array
    {
        $index = static function (array $items) use ($key): array {
            if ($key === null) return $items;
            $result = [];
            foreach ($items as $item) {
                $id = $item[$key] ?? null;
                if (!is_string($id) || isset($result[$id])) throw new InvalidArgumentException('Manifest 重复或无效键');
                $result[$id] = $item;
            }
            return $result;
        };
        $local = $index($local);
        $base = $index($base);
        $remote = $index($remote);
        foreach (array_unique(array_merge(array_keys($base), array_keys($remote))) as $id) {
            $current = $local[$id] ?? null;
            $before = $base[$id] ?? null;
            $next = $remote[$id] ?? null;
            if (CrudDefinition::canonicalJson([$current]) !== CrudDefinition::canonicalJson([$before])
                && CrudDefinition::canonicalJson([$current]) !== CrudDefinition::canonicalJson([$next])) {
                if (!$this->planning) throw new InvalidArgumentException('Manifest 受管键冲突：' . $id);
                $this->conflictPaths[] = $section . '[' . $id . ']';
                continue;
            }
            if ($next === null) unset($local[$id]);
            else $local[$id] = $next;
        }
        return $key === null ? $local : array_values($local);
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
        if (($data['requires']['plugins'] ?? null) === []) {
            $data['requires']['plugins'] = (object) [];
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}
