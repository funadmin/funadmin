<?php

declare(strict_types=1);

namespace app\console\development\service;

use RuntimeException;
use think\facade\Db;

/**
 * 在同一数据库事务中应用业务生成的菜单与权限资源。
 */
final class GenerationResourceTransaction
{
    private bool $active = false;

    public function __construct(private readonly ?ResourceRegistryService $registry = null)
    {
    }

    public function begin(): void
    {
        if ($this->active) {
            throw new RuntimeException('生成资源事务已开始');
        }
        Db::startTrans();
        $this->active = true;
    }

    public function apply(array $resources): void
    {
        $this->assertActive();
        $registry = $this->registry ?? new ResourceRegistryService();
        foreach ($this->groupBySource($resources) as $sourceName => $group) {
            $registry->removeSource('generated', $sourceName);
            $registry->registerPermissions($group['permissions'], 'generated', $sourceName);
            if ($group['menus'] !== []) {
                $registry->registerTree($group['menus'], sourceType: 'generated', sourceName: $sourceName);
            }
        }
    }

    public function commit(): void
    {
        $this->assertActive();
        try {
            Db::commit();
        } finally {
            $this->active = false;
        }
    }

    public function rollback(): void
    {
        if (!$this->active) {
            return;
        }
        try {
            Db::rollback();
        } finally {
            $this->active = false;
        }
    }

    /** @return array<string, array{permissions:list<array>,menus:list<array>}> */
    private function groupBySource(array $resources): array
    {
        $groups = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                throw new RuntimeException('生成资源记录无效');
            }
            [$type, $sourceName, $identity] = $this->identity($resource);
            $groups[$sourceName] ??= ['permissions' => [], 'menus' => []];
            if ($type === 'permission') {
                $groups[$sourceName]['permissions'][] = [
                    'code' => (string) ($resource['code'] ?? $identity),
                    'name' => (string) ($resource['name'] ?? $identity),
                    'sort' => (int) ($resource['sortOrder'] ?? $resource['sort'] ?? 999),
                ];
                continue;
            }
            if ($type !== 'menu') {
                throw new RuntimeException('不支持的生成资源类型：' . $type);
            }
            $groups[$sourceName]['menus'][] = [
                'name' => (string) ($resource['name'] ?? $sourceName),
                'href' => (string) ($resource['href'] ?? $identity),
                'permission' => (string) ($resource['permission'] ?? ''),
                'icon' => (string) ($resource['icon'] ?? 'i-ep-menu'),
                'sort' => (int) ($resource['sortOrder'] ?? $resource['sort'] ?? 999),
                'visible' => (int) ($resource['visible'] ?? 1),
                'status' => (int) ($resource['status'] ?? 1),
                'appName' => (string) ($resource['appName'] ?? 'console'),
            ];
        }
        ksort($groups, SORT_STRING);
        return $groups;
    }

    /** @return array{string,string,string} */
    private function identity(array $resource): array
    {
        $parts = explode('|', (string) ($resource['resourceKey'] ?? ''), 3);
        $type = strtolower((string) ($resource['resourceType'] ?? $parts[0] ?? ''));
        $sourceName = trim((string) ($resource['sourceName'] ?? $parts[1] ?? ''));
        $identity = trim((string) ($parts[2] ?? ''));
        if ($type === '' || $sourceName === '' || $identity === '') {
            throw new RuntimeException('生成资源稳定键无效');
        }
        return [$type, $sourceName, $identity];
    }

    private function assertActive(): void
    {
        if (!$this->active) {
            throw new RuntimeException('生成资源事务尚未开始');
        }
    }
}
