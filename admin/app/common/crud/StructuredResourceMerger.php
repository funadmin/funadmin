<?php

declare(strict_types=1);

namespace app\common\crud;

use InvalidArgumentException;

/**
 * 以业务稳定键而非数据库 ID 合并菜单和权限资源。
 */
final class StructuredResourceMerger
{
    private const ABSENT = "\0funadmin-absent\0";

    /** @return array{status: string, resources: list<array>, conflicts: list<array>} */
    public function merge(array $base, array $local, array $remote): array
    {
        $sets = [$this->index($base), $this->index($local), $this->index($remote)];
        $keys = array_unique(array_merge(array_keys($sets[0]), array_keys($sets[1]), array_keys($sets[2])));
        sort($keys, SORT_STRING);
        $resources = [];
        $conflicts = [];
        foreach ($keys as $key) {
            $merged = $this->mergeResource($sets[0][$key] ?? null, $sets[1][$key] ?? null, $sets[2][$key] ?? null, $key);
            if ($merged['conflicts'] !== []) {
                array_push($conflicts, ...$merged['conflicts']);
            }
            if ($merged['resource'] !== null) {
                $resources[] = ['resourceKey' => $key] + $merged['resource'];
            }
        }
        return ['status' => $conflicts === [] ? 'auto-merged' : 'conflict', 'resources' => $resources, 'conflicts' => $conflicts];
    }

    private function index(array $resources): array
    {
        $indexed = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) {
                throw new InvalidArgumentException('结构化资源必须为对象数组');
            }
            unset($resource['id'], $resource['resourceKey']);
            $key = $this->key($resource);
            if (isset($indexed[$key])) {
                throw new InvalidArgumentException('结构化资源稳定键重复：' . $key);
            }
            $indexed[$key] = $resource;
        }
        return $indexed;
    }

    private function key(array $resource): string
    {
        $type = (string) ($resource['resourceType'] ?? $resource['type'] ?? '');
        $source = (string) ($resource['sourceName'] ?? $resource['source_name'] ?? '');
        $identity = $type === 'permission'
            ? (string) ($resource['code'] ?? '')
            : (string) ($resource['href'] ?? $resource['path'] ?? '');
        if (!in_array($type, ['menu', 'permission'], true) || $source === '' || $identity === '') {
            throw new InvalidArgumentException('菜单/权限资源缺少稳定键字段');
        }
        return $type . '|' . $source . '|' . $identity;
    }

    /** @return array{resource: ?array, conflicts: list<array>} */
    private function mergeResource(?array $base, ?array $local, ?array $remote, string $key): array
    {
        if ($local === $remote) return ['resource' => $local, 'conflicts' => []];
        if ($local === $base) return ['resource' => $remote, 'conflicts' => []];
        if ($remote === $base) return ['resource' => $local, 'conflicts' => []];
        if ($base === null) {
            if ($local === null) return ['resource' => $remote, 'conflicts' => []];
            if ($remote === null) return ['resource' => $local, 'conflicts' => []];
        }
        if ($local === null || $remote === null) {
            return ['resource' => $local ?? $remote, 'conflicts' => [['resourceKey' => $key, 'field' => '*', 'reason' => 'delete-modify']]];
        }

        $fields = array_unique(array_merge(array_keys($base ?? []), array_keys($local), array_keys($remote)));
        sort($fields, SORT_STRING);
        $resource = [];
        $conflicts = [];
        foreach ($fields as $field) {
            if (in_array($field, ['id', 'resourceKey'], true)) continue;
            $baseValue = array_key_exists($field, $base ?? []) ? $base[$field] : self::ABSENT;
            $localValue = array_key_exists($field, $local) ? $local[$field] : self::ABSENT;
            $remoteValue = array_key_exists($field, $remote) ? $remote[$field] : self::ABSENT;
            if ($localValue === $remoteValue) {
                $value = $localValue;
            } elseif ($localValue === $baseValue) {
                $value = $remoteValue;
            } elseif ($remoteValue === $baseValue) {
                $value = $localValue;
            } else {
                $conflicts[] = ['resourceKey' => $key, 'field' => $field, 'base' => $baseValue, 'local' => $localValue, 'remote' => $remoteValue];
                $value = $localValue;
            }
            if ($value !== self::ABSENT) $resource[$field] = $value;
        }
        ksort($resource, SORT_STRING);
        return ['resource' => $resource, 'conflicts' => $conflicts];
    }
}
