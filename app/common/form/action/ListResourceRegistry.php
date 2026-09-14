<?php
declare(strict_types=1);
namespace app\common\form\action;

use InvalidArgumentException;
use app\common\crud\CrudDefinition;

/** 可信部署配置，目录与执行共用；不接受客户端 URL、路径或路由定义。 */
final class ListResourceRegistry
{
    private array $resources = [];
    public function __construct(array $resources = [])
    {
        foreach ($resources as $key => $resource) {
            $this->identifier($key);
            if (!is_array($resource) || array_diff(array_keys($resource), ['type', 'permission', 'route', 'params', 'query', 'origin', 'path', 'paths'])) $this->fail();
            $type = $resource['type'] ?? '';
            if (!in_array($type, ['navigate', 'external'], true) || !is_string($resource['permission'] ?? null) || $resource['permission'] === '') $this->fail();
            foreach (['params', 'query'] as $group) {
                $resource[$group] ??= [];
                if (!is_array($resource[$group]) || !array_is_list($resource[$group]) || count($resource[$group]) > 20) $this->fail();
                foreach ($resource[$group] as $name) $this->identifier($name);
            }
            if (count(array_unique(array_merge($resource['params'], $resource['query']))) !== count($resource['params']) + count($resource['query'])) $this->fail();
            if ($type === 'navigate') {
                $this->identifier($resource['route'] ?? null);
                if (array_intersect(array_keys($resource), ['origin', 'path', 'paths'])) $this->fail();
            } else {
                if ($resource['params'] || isset($resource['route'])) $this->fail();
                $origin = $resource['origin'] ?? '';
                if (!is_string($origin) || !preg_match('~^https://[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?(?::[0-9]{1,5})?$~D', $origin)) $this->fail();
                $paths = $resource['paths'] ?? [];
                if (!is_array($paths) || !array_is_list($paths) || !$paths || count($paths) > 50) $this->fail();
                foreach ($paths as $path) if (!is_string($path) || !preg_match('~^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]*$~D', $path)) $this->fail();
                if (!in_array($resource['path'] ?? null, $paths, true)) $this->fail();
            }
            $resource['capabilityVersion'] = hash('sha256', CrudDefinition::canonicalJson($resource));
            $this->resources[$key] = $resource;
        }
    }
    public function catalog(callable $permission): array
    {
        return array_filter($this->resources, static fn (array $r): bool => $permission($r['permission']));
    }
    public function hash(): string { return hash('sha256', CrudDefinition::canonicalJson($this->resources)); }
    public function definition(array $action, callable $permission): array
    {
        $r = $this->resources[$action['key'] ?? ''] ?? null;
        if (!$r || $r['type'] !== ($action['type'] ?? '') || !$permission($r['permission'])) $this->fail();
        if (isset($action['capabilityVersion']) && $action['capabilityVersion'] !== $r['capabilityVersion']) throw new InvalidArgumentException('FORM_LIST_RESOURCE_STALE');
        return $r;
    }
    public function resolve(array $action, array $values, callable $permission): array
    {
        $r = $this->definition($action, $permission);
        if (array_diff(array_keys($values), array_merge($r['params'], $r['query']))) $this->fail();
        foreach ($values as $name => $value) {
            $this->identifier($name);
            if ((!is_string($value) && !is_int($value)) || !preg_match('/^[\p{L}\p{N}_ -]{1,200}$/uD', (string) $value)) $this->fail();
        }
        foreach ($r['params'] as $name) if (!array_key_exists($name, $values)) $this->fail();
        $query = array_intersect_key($values, array_flip($r['query']));
        if ($r['type'] === 'navigate') return ['type' => 'navigate', 'route' => $r['route'], 'params' => (object) array_intersect_key($values, array_flip($r['params'])), 'query' => (object) $query, 'permission' => $r['permission']];
        return ['type' => 'external', 'url' => $r['origin'] . $r['path'] . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''), 'permission' => $r['permission']];
    }
    private function identifier(mixed $key): void
    {
        if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $key) || in_array($key, ['constructor', 'prototype'], true)) $this->fail();
    }
    private function fail(): never { throw new InvalidArgumentException('FORM_LIST_RESOURCE_FORBIDDEN'); }
}
