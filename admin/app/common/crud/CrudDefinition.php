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

    /** 仅用于新的输入；持久化快照继续使用 fromArray，禁止重算其表身份或 hash。 */
    public static function fromInput(array $data): self
    {
        if (isset($data['formSchema'])) return self::fromArray($data);
        $identity = $data['tableIdentity'] ?? ['source' => 'created', 'kind' => 'logical'];
        if (!is_array($identity) || array_diff(array_keys($identity), ['source', 'kind']) !== []
            || !in_array($identity['source'] ?? null, ['created', 'adopted'], true)
            || !in_array($identity['kind'] ?? null, ['logical', 'physical'], true)
            || ($identity['source'] === 'adopted' && $identity['kind'] !== 'physical')) {
            throw new \InvalidArgumentException('tableIdentity 必须显式区分新建逻辑表与采纳物理表');
        }
        if ($identity['kind'] === 'logical') {
            $connection = (string) ($data['connection'] ?? $data['metadata']['connection'] ?? 'mysql');
            $prefix = (string) (\app\common\service\database\DatabaseConnections::settings($connection)['prefix'] ?? '');
            $table = trim((string) ($data['table'] ?? ''));
            $data['table'] = $table !== '' && $prefix !== '' && !str_starts_with($table, $prefix) ? $prefix . $table : $table;
            $identity['kind'] = 'physical';
        }
        $data['tableIdentity'] = $identity;
        $entity = (string) ($data['entity'] ?? $data['name'] ?? '');
        $paths = $data['generationTargets'] ?? $data['paths'] ?? [];
        if (($data['target']['type'] ?? 'core') === 'core' && preg_match('/^[a-z][a-z0-9-]*$/D', $entity) === 1 && is_array($paths)) {
            $class = self::studly($entity);
            foreach (['model' => '', 'validate' => 'Validate', 'service' => 'Service', 'controller' => 'Controller'] as $layer => $suffix) {
                $file = $class . $suffix . '.php';
                if (!isset($paths[$layer])) {
                    $paths[$layer] = 'app/admin/business/' . str_replace('-', '_', $entity) . '/' . $layer . '/' . $file;
                }
            }
            $data['generationTargets'] = $paths;
        }
        return self::fromArray($data);
    }

    public function isAdopted(): bool
    {
        return ($this->data['formSchema']['database']['source'] ?? $this->data['tableIdentity']['source'] ?? 'created') === 'adopted';
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
        $data['module'] ??= 'business';
        $data['entity'] ??= (string) ($data['name'] ?? '');
        $data['apiPrefix'] ??= (string) ($data['routePath'] ?? '');
        $data['routePath'] ??= (string) $data['apiPrefix'];
        $data['primaryKey'] ??= (string) (($primary[0]['name'] ?? ''));
        $data['timestamps'] ??= in_array('created_at', $fieldNames, true) && in_array('updated_at', $fieldNames, true);
        $data['softDeletes'] ??= true;
        $data['target'] = is_array($data['target'] ?? null) ? $data['target'] : ['type' => 'core'];
        $isPlugin = ($data['target']['type'] ?? 'core') === 'plugin';
        if (!$isPlugin) {
            $data['generationTargets'] ??= is_array($data['paths'] ?? null) ? $data['paths'] : [];
        }
        $data['layoutSchema'] = is_array($data['layoutSchema'] ?? null) ? $data['layoutSchema'] : [];
        // 携带 Schema 时以其顶层 list 为唯一真源，禁止发布配置覆盖。
        $data['list'] = isset($data['formSchema']) ? ($data['formSchema']['list'] ?? []) : ($data['list'] ?? []);
        $entity = (string) $data['entity'];
        $class = self::studly($entity);
        if (!$isPlugin && is_array($data['generationTargets'])) {
            $data['generationTargets']['phpTest'] ??= "tests/business/{$class}GeneratedTest.php";
            $data['generationTargets']['vitestTest'] ??= "web/tests/business/{$entity}.spec.ts";
            $data['generationTargets']['langMigration'] ??= "database/business/{$entity}_lang.sql";
            // 后端语言包跟随模块：app/admin/business/<模块>/lang/<语言>.php，由 GeneratedLanguagePacks 自动发现。
            $data['generationTargets']['langZh'] ??= 'app/admin/business/' . str_replace('-', '_', $entity) . '/lang/zh-cn.php';
            $data['generationTargets']['langEn'] ??= 'app/admin/business/' . str_replace('-', '_', $entity) . '/lang/en-us.php';
        }
        if (!$isPlugin && is_array($data['templates'] ?? null)) {
            $data['templates']['phpTest'] ??= 'tests/php-test.php.tpl';
            $data['templates']['vitestTest'] ??= 'tests/vitest-test.ts.tpl';
            $data['templates']['langMigration'] ??= 'database/lang.sql.tpl';
            $data['templates']['langZh'] ??= 'admin/lang-zh.php.tpl';
            $data['templates']['langEn'] ??= 'admin/lang-en.php.tpl';
        }
        $data['menu'] = self::normalizeMenu($data);
        $data['permission'] = self::normalizePermission($data);
        $memberApi = is_array($data['memberApi'] ?? null) ? $data['memberApi'] : [];
        $data['memberApi'] = [
            'enabled' => ($memberApi['enabled'] ?? false) === true,
            'ownerField' => (string) ($memberApi['ownerField'] ?? 'member_id'),
        ];
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
        $append($enabled('list'), 'index', 'index', '查看列表');
        $listActions = $enabled('list') && !empty($data['formSchema']['key']) && ($data['target']['type'] ?? 'core') === 'core';
        $append($listActions, 'listactions', 'list-actions', '读取列表动作目录');
        $append($listActions, 'listaction', 'list-action', '执行列表动作');
        $leftTree = $enabled('list') && ($data['list']['leftTree']['enabled'] ?? false) === true;
        $append($leftTree, 'lefttree', 'left-tree', '读取来源树');
        $append($leftTree, 'lefttreeform', 'left-tree-form', '读取来源表单');
        $append($leftTree, 'mutatelefttree', 'mutate-left-tree', '操作来源树');
        $append($enabled('detail') && ($features['detail'] ?? true), 'detail', 'detail', '查看详情');
        $append($enabled('create'), 'create', 'create', '新增');
        $append($enabled('update'), 'update', 'update', '编辑');
        $append($enabled('update') && ($features['status'] ?? false), 'status', 'status', '切换状态');
        $hasOptions = ($enabled('form') || ($enabled('list') && ($data['list']['category']['enabled'] ?? false))) && (array) ($data['optionsSource'] ?? []) !== [];
        $append($hasOptions, 'options', 'options', '读取选项');
        $append($delete, 'remove', 'remove', '删除');
        $append($softDelete, 'restore', 'restore', '恢复');
        $append($softDelete, 'destroy', 'destroy', '永久删除');
        $append($delete && ($features['batchDelete'] ?? false), 'recycle', 'recycle', '批量删除');
        $append($softDelete && ($features['batchDelete'] ?? false), 'restoreMany', 'restore-many', '批量恢复');
        $append($softDelete && ($features['batchDelete'] ?? false), 'destroyMany', 'destroy-many', '批量永久删除');
        $append($enabled('import') && ($features['import'] ?? false), 'import', 'import', '导入');
        $append($enabled('export') && ($features['export'] ?? false), 'export', 'export', '导出');
        // 设计器自定义动作：每个动作一条权限（business:{实体}:action-{kebab}），经列表动作宿主执行。
        $custom = is_array($data['list'] ?? null) && is_array($data['list']['customActions'] ?? null) ? $data['list']['customActions'] : [];
        foreach ($custom as $action) {
            if (!is_array($action) || !is_string($action['key'] ?? null)) continue;
            $suffix = \app\common\form\action\CustomActions::codeSuffix($action['key']);
            $append($listActions, $suffix, $suffix, (string) ($action['label'] ?? $action['key']));
        }
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
