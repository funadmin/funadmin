<?php

declare(strict_types=1);

namespace app\common\crud;

use JsonSerializable;

/**
 * 与入口无关的不可变 CRUD 定义。
 */
final class CrudDefinition implements JsonSerializable
{
    private function __construct(private readonly array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(self::normalize($data));
    }

    private static function normalize(array $data): array
    {
        $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
        $fieldNames = array_column(array_filter($fields, 'is_array'), 'name');
        $primary = array_values(array_filter(
            $fields,
            static fn (mixed $field): bool => is_array($field) && ($field['primary'] ?? false) === true
        ));
        $data['connection'] ??= (string) (($data['metadata']['connection'] ?? 'mysql'));
        $data['module'] ??= 'generated';
        $data['entity'] ??= (string) ($data['name'] ?? '');
        $data['apiPrefix'] ??= (string) ($data['routePath'] ?? '');
        $data['routePath'] ??= (string) $data['apiPrefix'];
        $data['primaryKey'] ??= (string) (($primary[0]['name'] ?? ''));
        $data['timestamps'] ??= in_array('created_at', $fieldNames, true) && in_array('updated_at', $fieldNames, true);
        $data['softDeletes'] ??= true;
        $data['generationTargets'] ??= is_array($data['paths'] ?? null) ? $data['paths'] : [];
        $entity = (string) $data['entity'];
        $class = self::studly($entity);
        if (is_array($data['generationTargets'])) {
            $data['generationTargets']['phpTest'] ??= "tests/generated/{$class}GeneratedTest.php";
            $data['generationTargets']['vitestTest'] ??= "admin-web/tests/generated/{$entity}.spec.ts";
        }
        if (is_array($data['templates'] ?? null)) {
            $data['templates']['phpTest'] ??= 'tests/php-test.php.tpl';
            $data['templates']['vitestTest'] ??= 'tests/vitest-test.ts.tpl';
        }
        $data['menu'] = self::normalizeMenu($data);
        $data['permission'] = self::normalizePermission($data);
        unset($data['name'], $data['paths'], $data['metadata']);
        return $data;
    }

    private static function normalizeMenu(array $data): array
    {
        $menu = is_array($data['menu'] ?? null) ? $data['menu'] : [];
        return [
            'enabled' => (bool) ($menu['enabled'] ?? true),
            'parentId' => isset($menu['parentId']) ? (int) $menu['parentId'] : null,
            'parentSourceName' => (string) ($menu['parentSourceName'] ?? ''),
            'name' => (string) ($menu['name'] ?? $data['title'] ?? ''),
            'icon' => (string) ($menu['icon'] ?? 'i-ep-document'),
            'sortOrder' => (int) ($menu['sortOrder'] ?? 999),
            'hidden' => (bool) ($menu['hidden'] ?? false),
            'keepAlive' => (bool) ($menu['keepAlive'] ?? true),
            'affix' => (bool) ($menu['affix'] ?? false),
            'target' => (string) ($menu['target'] ?? '_self'),
        ];
    }

    private static function normalizePermission(array $data): array
    {
        $permission = is_array($data['permission'] ?? null) ? $data['permission'] : [];
        $labels = [];
        foreach ((array) ($permission['actions'] ?? []) as $action) {
            if (is_array($action) && isset($action['action'], $action['label'])) {
                $labels[(string) $action['action']] = (string) $action['label'];
            }
        }
        $actions = [];
        foreach (self::resourceActions($data) as [$action, $suffix, $defaultLabel]) {
            $actions[] = ['action' => $action, 'codeSuffix' => $suffix, 'label' => $labels[$action] ?? $defaultLabel];
        }
        return [
            'enabled' => (bool) ($permission['enabled'] ?? true),
            'groupName' => (string) ($permission['groupName'] ?? $data['title'] ?? ''),
            'actions' => $actions,
        ];
    }

    private static function resourceActions(array $data): array
    {
        $capabilities = (array) ($data['capabilities'] ?? []);
        $features = (array) ($data['features'] ?? []);
        $enabled = static fn (string $name): bool => ($capabilities[$name] ?? true) === true;
        $delete = $enabled('delete');
        $softDelete = $delete && ($data['softDeletes'] ?? true) === true;
        $actions = [];
        $append = static function (bool $condition, string $action, string $suffix, string $label) use (&$actions): void {
            if ($condition) $actions[] = [$action, $suffix, $label];
        };
        $append($enabled('list'), 'index', 'list', '查看列表');
        $append($enabled('detail') && ($features['detail'] ?? true), 'detail', 'detail', '查看详情');
        $append($enabled('create'), 'create', 'create', '新增');
        $append($enabled('update'), 'update', 'update', '编辑');
        $append($enabled('update') && ($features['status'] ?? false), 'status', 'status', '切换状态');
        $hasOptions = $enabled('form') && (array) ($data['optionsSource'] ?? []) !== [];
        $append($hasOptions, 'options', 'options', '读取选项');
        $append($delete, 'remove', 'delete', '删除');
        $append($softDelete, 'restore', 'restore', '恢复');
        $append($softDelete, 'destroy', 'destroy', '永久删除');
        $append($delete && ($features['batchDelete'] ?? false), 'recycle', 'batch-delete', '批量删除');
        $append($softDelete && ($features['batchDelete'] ?? false), 'restoreMany', 'batch-restore', '批量恢复');
        $append($softDelete && ($features['batchDelete'] ?? false), 'destroyMany', 'batch-destroy', '批量永久删除');
        $append($enabled('import') && ($features['import'] ?? false), 'import', 'import', '导入');
        $append($enabled('export') && ($features['export'] ?? false), 'export', 'export', '导出');
        return $actions;
    }

    public function schemaVersion(): string
    {
        return (string) ($this->data['schemaVersion'] ?? '');
    }

    public function fields(): array
    {
        return is_array($this->data['fields'] ?? null) ? $this->data['fields'] : [];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function toArray(): array
    {
        return $this->data;
    }

    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function hash(): string
    {
        return hash('sha256', self::canonicalJson($this->data));
    }

    private static function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    public static function canonicalJson(array $data): string
    {
        $normalize = static function (mixed $value) use (&$normalize): mixed {
            if (!is_array($value)) {
                return $value;
            }
            if (!array_is_list($value)) {
                ksort($value, SORT_STRING);
            }
            return array_map($normalize, $value);
        };
        return json_encode($normalize($data), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
