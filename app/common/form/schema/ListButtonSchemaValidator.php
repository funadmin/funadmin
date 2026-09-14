<?php

declare(strict_types=1);

namespace app\common\form\schema;

/** 列表按钮的纯声明协议；不授予权限，不执行动作或解析任意路径。 */
final class ListButtonSchemaValidator
{
    public const LOCATIONS = ['toolbar', 'row', 'categoryToolbar', 'categoryNode'];
    public const TOOLS = ['refresh', 'search', 'columns', 'density', 'fullscreen'];
    private const BUILTINS = [
        'toolbar' => ['create', 'batchDelete', 'import', 'export', 'recycle'],
        'row' => ['detail', 'edit', 'delete', 'restore', 'destroy'],
        'categoryToolbar' => ['create'],
        'categoryNode' => ['addChild', 'edit', 'delete'],
    ];

    public function validate(array $list, array $fields): void
    {
        if (array_key_exists('tools', $list)) {
            $tools = $this->object($list['tools'], self::TOOLS, '/list/tools');
            foreach ($tools as $key => $value) if (!is_bool($value)) $this->fail('工具开关必须为布尔值', '/list/tools/' . $key);
        }
        if (!array_key_exists('buttons', $list)) return;
        $collections = $this->object($list['buttons'], self::LOCATIONS, '/list/buttons');
        foreach ($collections as $location => $buttons) {
            $path = '/list/buttons/' . $location;
            if (!is_array($buttons) || !array_is_list($buttons) || count($buttons) > 50) $this->fail('按钮必须为最多 50 项的有序集合', $path);
            $ids = [];
            foreach ($buttons as $index => $button) {
                $at = $path . '/' . $index;
                $button = $this->object($button, ['id', 'label', 'icon', 'color', 'size', 'tips', 'placement', 'order', 'hidden', 'disabled', 'disabledReason', 'permission', 'visibleWhen', 'disabledWhen', 'interaction', 'action', 'params', 'success'], $at);
                $this->identifier($button['id'] ?? null, $at . '/id');
                if (isset($ids[$button['id']])) $this->fail('按钮 ID 重复', $at . '/id');
                $ids[$button['id']] = true;
                $this->text($button['label'] ?? null, $at . '/label', 100);
                foreach (['tips', 'disabledReason', 'permission'] as $key) if (array_key_exists($key, $button)) $this->text($button[$key], $at . '/' . $key, 500);
                if (isset($button['icon'])) $this->identifier($button['icon'], $at . '/icon');
                foreach (['color' => ['default', 'primary', 'success', 'warning', 'danger', 'info'], 'size' => ['small', 'default', 'large'], 'placement' => ['inline', 'more']] as $key => $allowed) {
                    if (array_key_exists($key, $button)) $this->oneOf($button[$key], $allowed, $at . '/' . $key);
                }
                foreach (['hidden', 'disabled'] as $key) if (array_key_exists($key, $button) && !is_bool($button[$key])) $this->fail('开关必须为布尔值', $at . '/' . $key);
                if (array_key_exists('order', $button) && (!is_int($button['order']) || abs($button['order']) > 10000)) $this->fail('排序值不合法', $at . '/order');
                foreach (['visibleWhen', 'disabledWhen'] as $key) {
                    if (array_key_exists($key, $button)) {
                        $budget = 100;
                        $this->condition($button[$key], $fields, $at . '/' . $key, 0, $budget);
                    }
                }
                $action = $this->object($button['action'] ?? null, ['type', 'key', 'capabilityVersion'], $at . '/action');
                $this->oneOf($action['type'] ?? null, ['builtin', 'registered', 'form', 'detail', 'navigate', 'external', 'copy', 'download', 'refresh'], $at . '/action/type');
                if ($action['type'] === 'builtin') $this->oneOf($action['key'] ?? null, self::BUILTINS[$location], $at . '/action/key');
                elseif (in_array($action['type'], ['registered', 'navigate', 'external', 'download'], true)) $this->identifier($action['key'] ?? null, $at . '/action/key');
                elseif (array_key_exists('key', $action)) $this->fail('该动作不接受 key', $at . '/action/key');
                if ($action['type'] === 'registered') $this->text($action['capabilityVersion'] ?? null, $at . '/action/capabilityVersion', 64);
                elseif (array_key_exists('capabilityVersion', $action)) $this->fail('仅注册动作接受版本', $at . '/action/capabilityVersion');
                if (array_key_exists('interaction', $button)) $this->interaction($button['interaction'], $at . '/interaction');
                $params = $this->map($button['params'] ?? [], $at . '/params');
                if (count($params) > 30) $this->fail('参数过多', $at . '/params');
                foreach ($params as $name => $binding) {
                    $this->identifier($name, $at . '/params');
                    $this->binding($binding, $fields, $button['interaction']['fields'] ?? [], $at . '/params/' . $name);
                }
                if (array_key_exists('success', $button)) {
                    $effects = $this->object($button['success'], ['message', 'refresh', 'clearSelection', 'close'], $at . '/success');
                    foreach ($effects as $key => $value) {
                        if ($key === 'message') $this->text($value, $at . '/success/message', 500);
                        elseif (!is_bool($value)) $this->fail('成功效果必须为布尔值', $at . '/success/' . $key);
                    }
                }
            }
        }
    }

    private function binding(mixed $binding, array $fields, array $inputs, string $path): void
    {
        $binding = $this->object($binding, ['source', 'field', 'value'], $path);
        $source = $binding['source'] ?? null;
        $this->oneOf($source, ['row', 'selection', 'filter', 'category', 'form', 'literal'], $path . '/source');
        if ($source === 'literal') {
            if (!array_key_exists('value', $binding) || array_key_exists('field', $binding)) $this->fail('常量绑定必须仅提供 value', $path);
            $value = $binding['value'];
            if (!is_null($value) && !is_scalar($value)) $this->fail('常量只支持 JSON 标量', $path . '/value');
            return;
        }
        if (array_key_exists('value', $binding)) $this->fail('字段绑定不接受 value', $path);
        if ($source === 'selection') {
            if (($binding['field'] ?? null) !== 'ids') $this->fail('批量上下文只支持当前页已选 ids', $path . '/field');
        } elseif ($source === 'form') {
            $this->identifier($binding['field'] ?? null, $path . '/field');
            if (!in_array($binding['field'], array_column($inputs, 'name'), true)) $this->fail('输入字段未声明', $path . '/field');
        } else {
            $this->readableField($binding['field'] ?? null, $fields, $path . '/field');
        }
    }

    private function condition(mixed $condition, array $fields, string $path, int $depth, int &$budget): void
    {
        if ($depth > 8 || --$budget < 0) $this->fail('条件复杂度超过限制', $path);
        $condition = $this->object($condition, ['op', 'field', 'value', 'conditions', 'condition'], $path);
        $op = $condition['op'] ?? null;
        $this->oneOf($op, ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in', 'notIn', 'contains', 'startsWith', 'endsWith', 'empty', 'notEmpty', 'and', 'or', 'not'], $path . '/op');
        if (in_array($op, ['and', 'or'], true)) {
            $this->object($condition, ['op', 'conditions'], $path);
            $children = $condition['conditions'] ?? null;
            if (!is_array($children) || !array_is_list($children) || !$children || count($children) > 20) $this->fail('条件组不合法', $path);
            foreach ($children as $index => $child) $this->condition($child, $fields, $path . '/conditions/' . $index, $depth + 1, $budget);
        } elseif ($op === 'not') {
            $this->object($condition, ['op', 'condition'], $path);
            $this->condition($condition['condition'] ?? null, $fields, $path . '/condition', $depth + 1, $budget);
        } else {
            $this->object($condition, ['op', 'field', 'value'], $path);
            $this->readableField($condition['field'] ?? null, $fields, $path . '/field');
            if (in_array($op, ['empty', 'notEmpty'], true)) return;
            if (!array_key_exists('value', $condition)) $this->fail('条件缺少值', $path . '/value');
            $values = in_array($op, ['in', 'notIn'], true) ? $condition['value'] : [$condition['value']];
            if (!is_array($values) || !array_is_list($values) || count($values) > 100) $this->fail('条件值不合法', $path . '/value');
            foreach ($values as $value) if (!is_null($value) && !is_scalar($value)) $this->fail('条件值必须为标量', $path . '/value');
        }
    }

    private function interaction(mixed $value, string $path): void
    {
        $value = $this->object($value, ['type', 'presentation', 'title', 'message', 'fields'], $path);
        $this->oneOf($value['type'] ?? null, ['none', 'confirm', 'input', 'form'], $path . '/type');
        if (array_key_exists('presentation', $value)) $this->oneOf($value['presentation'], ['dialog', 'drawer'], $path . '/presentation');
        foreach (['title', 'message'] as $key) if (array_key_exists($key, $value)) $this->text($value[$key], $path . '/' . $key, 500);
        $fields = $value['fields'] ?? [];
        if (!is_array($fields) || !array_is_list($fields) || count($fields) > 20) $this->fail('输入字段不合法', $path . '/fields');
        if (in_array($value['type'], ['none', 'confirm'], true) && $fields) $this->fail('该交互不接受输入字段', $path . '/fields');
        if ($value['type'] === 'input' && count($fields) !== 1) $this->fail('输入交互必须声明一个字段', $path . '/fields');
        $names = [];
        foreach ($fields as $index => $field) {
            $at = $path . '/fields/' . $index;
            $field = $this->object($field, ['name', 'label', 'type', 'required', 'min', 'max', 'maxLength', 'options'], $at);
            $this->identifier($field['name'] ?? null, $at . '/name');
            if (isset($names[$field['name']])) $this->fail('输入字段重复', $at . '/name');
            $names[$field['name']] = true;
            $this->text($field['label'] ?? null, $at . '/label', 100);
            $this->oneOf($field['type'] ?? null, ['input', 'textarea', 'number', 'select', 'switch', 'date'], $at . '/type');
            if (array_key_exists('required', $field) && !is_bool($field['required'])) $this->fail('required 必须为布尔值', $at);
            foreach (['min', 'max', 'maxLength'] as $key) if (array_key_exists($key, $field) && (!is_int($field[$key]) && !is_float($field[$key]))) $this->fail('校验界限必须为数字', $at . '/' . $key);
            if (isset($field['min'], $field['max']) && $field['min'] > $field['max']) $this->fail('最小值不能超过最大值', $at);
            if (isset($field['maxLength']) && (!is_int($field['maxLength']) || $field['maxLength'] < 1 || $field['maxLength'] > 10000)) $this->fail('长度限制不合法', $at);
            if (array_key_exists('options', $field)) {
                if (!is_array($field['options']) || !array_is_list($field['options']) || count($field['options']) > 100) $this->fail('选项不合法', $at);
                foreach ($field['options'] as $option) {
                    $option = $this->object($option, ['label', 'value'], $at . '/options');
                    $this->text($option['label'] ?? null, $at . '/options', 100);
                    if (!is_string($option['value'] ?? null) && !is_int($option['value'] ?? null)) $this->fail('选项值不合法', $at . '/options');
                }
            }
        }
    }

    private function readableField(mixed $name, array $fields, string $path): void
    {
        $this->identifier($name, $path);
        if (str_contains($name, '.') || str_contains($name, '-')) $this->fail('必须使用数据库字段名，不支持属性路径', $path);
        if ($name === 'id' && !isset($fields[$name])) return;
        $node = $fields[$name] ?? null;
        if (!$node || empty($node['database']['columnType']) || ($node['type'] ?? '') === 'password'
            || ($node['props']['sensitive'] ?? false) || ($node['props']['writeOnly'] ?? false)
            || ($node['access'] ?? $node['props']['schemaAccess'] ?? []) !== []
            || in_array($node['valueType'] ?? '', ['array', 'object'], true)
            || ($node['database']['relation']['type'] ?? 'none') !== 'none') $this->fail('必须引用可读非敏感数据库字段', $path);
    }

    private function identifier(mixed $value, string $path): void
    {
        if (!is_string($value) || !preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $value)
            || array_intersect(explode('.', $value), ['__proto__', 'prototype', 'constructor'])) $this->fail('标识不合法', $path);
    }

    private function text(mixed $value, string $path, int $max): void
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/', $value)) $this->fail('文本不合法', $path);
    }

    private function oneOf(mixed $value, array $allowed, string $path): void
    {
        if (!in_array($value, $allowed, true)) $this->fail('配置值不受支持', $path);
    }

    private function map(mixed $value, string $path): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) $this->fail('必须为对象', $path);
        return $value;
    }

    private function object(mixed $value, array $keys, string $path): array
    {
        $value = $this->map($value, $path);
        if (array_diff(array_keys($value), $keys)) $this->fail('包含未授权配置', $path);
        return $value;
    }

    private function fail(string $message, string $path): never
    {
        throw new FormSchemaException($message, $path, 'FORM_LIST_BUTTON_INVALID');
    }
}
