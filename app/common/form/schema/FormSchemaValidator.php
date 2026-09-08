<?php

declare(strict_types=1);

namespace app\common\form\schema;

final class FormSchemaValidator
{
    public const COMPONENTS = [
        'input', 'password', 'textarea', 'mention', 'number', 'select', 'selectV2', 'treeSelect', 'cascader',
        'radio', 'checkbox', 'switch', 'transfer', 'date', 'datetime', 'daterange', 'datetimerange', 'time',
        'timeSelect', 'slider', 'rate', 'color', 'image', 'images', 'file', 'files', 'dictionary', 'relation',
        'department', 'user', 'richtext', 'json', 'hidden', 'readonly', 'group', 'grid', 'divider', 'text',
        'collapse', 'tabs', 'repeatable', 'subform',
    ];

    private const ACTIONS = [
        'setValue', 'copyValue', 'clearValue', 'show', 'hide', 'enable', 'disable', 'setRequired',
        'validate', 'request', 'notify', 'openDialog', 'navigate', 'submit', 'reset',
    ];
    private const EVENTS = ['change', 'blur', 'focus', 'click', 'clear', 'select', 'submit', 'reset', 'mounted'];
    private const MAX_DEPTH = 20;
    private const MAX_NODES = 1000;

    public function validate(array $schema): void
    {
        if ((int) ($schema['schemaVersion'] ?? 0) !== 2) {
            throw new FormSchemaException('仅支持 FormSchema v2', '/schemaVersion', 'FORM_SCHEMA_VERSION_UNSUPPORTED');
        }
        if (!preg_match('/^[a-z][a-z0-9_]{0,60}$/', (string) ($schema['key'] ?? ''))) {
            throw new FormSchemaException('表单标识不合法', '/key');
        }
        if (!is_array($schema['nodes'] ?? null)) {
            throw new FormSchemaException('nodes 必须为数组', '/nodes');
        }
        $ids = [];
        $fields = [];
        $nodeCount = 0;
        $writers = [];
        $this->validateNodes($schema['nodes'], '/nodes', 1, $ids, $fields, $nodeCount, $writers);
        if ($nodeCount > self::MAX_NODES) {
            throw new FormSchemaException('节点数量超过限制', '/nodes', 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
    }

    private function validateNodes(array $nodes, string $path, int $depth, array &$ids, array &$fields, int &$count, array &$writers): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new FormSchemaException('节点嵌套超过限制', $path, 'FORM_SCHEMA_LIMIT_EXCEEDED');
        }
        foreach ($nodes as $index => $node) {
            $nodePath = $path . '/' . $index;
            if (!is_array($node)) throw new FormSchemaException('节点必须为对象', $nodePath);
            $count++;
            $id = (string) ($node['id'] ?? '');
            if ($id === '' || isset($ids[$id])) throw new FormSchemaException('节点 ID 为空或重复', $nodePath . '/id');
            $ids[$id] = true;
            $type = (string) ($node['type'] ?? '');
            if (!in_array($type, self::COMPONENTS, true) && !str_contains($type, ':')) {
                throw new FormSchemaException('组件未注册：' . $type, $nodePath . '/type', 'FORM_COMPONENT_NOT_REGISTERED');
            }
            $field = (string) ($node['field'] ?? '');
            if (in_array($type, ['repeatable', 'subform'], true)) {
                $relation = (array) (($node['database']['relation'] ?? []));
                if (($relation['type'] ?? '') !== 'has_many') {
                    throw new FormSchemaException('重复行或子表单必须绑定 has_many', $nodePath . '/database/relation/type');
                }
                foreach (['table', 'valueField'] as $required) {
                    if (!preg_match('/^[a-z_][a-z0-9_]*$/', (string) ($relation[$required] ?? ''))) {
                        throw new FormSchemaException('子表关系配置不完整', $nodePath . '/database/relation/' . $required);
                    }
                }
            }
            if (($node['kind'] ?? 'field') === 'field') {
                if (!preg_match('/^[a-z][a-z0-9_]{0,60}$/', $field) || isset($fields[$field])) {
                    throw new FormSchemaException('字段为空、不合法或重复', $nodePath . '/field');
                }
                $fields[$field] = true;
            }
            $this->validateAttributes((array) ($node['attrs'] ?? []), $nodePath . '/attrs');
            $this->validateEvents((array) ($node['events'] ?? []), $nodePath . '/events');
            $this->validateConditions((array) ($node['conditions'] ?? []), $field, $nodePath . '/conditions', $writers);
            $children = $node['children'] ?? [];
            if (!is_array($children)) throw new FormSchemaException('children 必须为数组', $nodePath . '/children');
            if (in_array($type, ['repeatable', 'subform'], true)) {
                $childFields = [];
                $this->validateNodes($children, $nodePath . '/children', $depth + 1, $ids, $childFields, $count, $writers);
            } else {
                $this->validateNodes($children, $nodePath . '/children', $depth + 1, $ids, $fields, $count, $writers);
            }
        }
    }

    private function validateAttributes(array $attributes, string $path): void
    {
        foreach ($attributes as $name => $value) {
            $normalized = strtolower((string) $name);
            if (str_starts_with($normalized, 'on') || in_array($normalized, ['innerhtml', 'is'], true)) {
                throw new FormSchemaException('危险属性不允许使用', $path . '/' . $name, 'FORM_SCHEMA_UNSAFE');
            }
            if (is_string($value) && preg_match('/javascript\s*:/i', $value)) {
                throw new FormSchemaException('脚本协议不允许使用', $path . '/' . $name, 'FORM_SCHEMA_UNSAFE');
            }
        }
    }

    private function validateEvents(array $events, string $path): void
    {
        foreach ($events as $event => $actions) {
            if (!in_array($event, self::EVENTS, true)) throw new FormSchemaException('事件未注册', $path . '/' . $event);
            if (!is_array($actions)) throw new FormSchemaException('动作链必须为数组', $path . '/' . $event);
            foreach ($actions as $index => $action) {
                $type = is_array($action) ? (string) ($action['type'] ?? '') : '';
                if (!in_array($type, self::ACTIONS, true)) {
                    throw new FormSchemaException('动作未注册：' . $type, $path . '/' . $event . '/' . $index . '/type', 'FORM_ACTION_NOT_REGISTERED');
                }
            }
        }
    }

    private function validateConditions(array $conditions, string $field, string $path, array &$writers): void
    {
        foreach ($conditions as $index => $condition) {
            $then = is_array($condition) ? (array) ($condition['then'] ?? []) : [];
            if (($then['action'] ?? '') !== 'setValue') continue;
            $target = (string) ($then['target'] ?? $field);
            $source = (string) (($condition['when']['field'] ?? ''));
            if ($target === $source) {
                throw new FormSchemaException('条件不能写回触发自身的字段', $path . '/' . $index . '/then/target', 'FORM_CONDITION_CYCLE');
            }
            $writers[$source][] = $target;
        }
    }
}
