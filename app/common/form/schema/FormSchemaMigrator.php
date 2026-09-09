<?php

declare(strict_types=1);

namespace app\common\form\schema;

final class FormSchemaMigrator
{
    private const LAYOUT_TYPES = ['group', 'grid', 'divider', 'text', 'collapse', 'tabs'];

    public function fromV1(array $definition): array
    {
        if ((int) ($definition['schemaVersion'] ?? 0) === 2) {
            return $definition;
        }
        if (is_array($definition['schema_document'] ?? null)
            && (int) ($definition['schema_document']['schemaVersion'] ?? 0) === 2) {
            return $definition['schema_document'];
        }

        $nodes = [];
        foreach (array_values((array) ($definition['fields'] ?? [])) as $index => $field) {
            $nodes[] = $this->node($field, $index);
        }

        return [
            'schemaVersion' => 2,
            'key' => trim((string) ($definition['form_key'] ?? '')),
            'title' => trim((string) ($definition['name'] ?? '')),
            'model' => is_array($definition['model_config'] ?? null) ? $definition['model_config'] : [],
            'layout' => is_array($definition['layout_config'] ?? null) ? $definition['layout_config'] : [],
            'nodes' => $nodes,
            'dataSources' => is_array($definition['data_sources'] ?? null) ? $definition['data_sources'] : [],
            'actions' => is_array($definition['actions'] ?? null) ? $definition['actions'] : [],
            'form' => is_array($definition['form_config'] ?? null) ? $definition['form_config'] : [],
            'submit' => is_array($definition['submit_config'] ?? null) ? $definition['submit_config'] : [],
            'list' => is_array($definition['list_config'] ?? null) ? $definition['list_config'] : [],
            'database' => [
                'table' => trim((string) ($definition['table_name'] ?? '')),
                'connection' => trim((string) ($definition['connection'] ?? 'mysql')) ?: 'mysql',
                'source' => (string) ($definition['source_type'] ?? 'created'),
            ],
            'extensions' => [
                'legacy' => array_diff_key($definition, array_flip([
                    'form_key', 'name', 'table_name', 'connection', 'source_type', 'fields', 'form_config', 'list_config',
                ])),
            ],
        ];
    }

    private function node(array $field, int $index): array
    {
        $type = (string) ($field['type'] ?? 'input');
        $kind = in_array($type, self::LAYOUT_TYPES, true) ? 'layout' : 'field';
        $name = trim((string) ($field['field_name'] ?? 'node_' . $index));
        $validation = [];
        if ((int) ($field['form_required'] ?? 0) === 1) {
            $validation[] = ['type' => 'required', 'message' => (string) ($field['label'] ?? $name) . '不能为空', 'trigger' => ['blur', 'change']];
        }
        foreach ((array) ($field['validate_rules'] ?? []) as $rule => $value) {
            $validation[] = ['type' => (string) $rule, 'value' => $value];
        }

        return [
            'id' => 'node_' . substr(hash('sha256', $name . ':' . $index), 0, 16),
            'kind' => $kind,
            'type' => $type,
            'field' => $kind === 'field' ? $name : null,
            'title' => (string) ($field['label'] ?? $name),
            'defaultValue' => $field['default_value'] ?? null,
            'valueType' => $this->valueType($type, (string) ($field['column_type'] ?? '')),
            'props' => is_array($field['control_props'] ?? null) ? $field['control_props'] : [],
            'attrs' => [],
            'className' => '',
            'style' => [],
            'hidden' => (int) ($field['form_show'] ?? 1) !== 1,
            'disabled' => (int) ($field['form_readonly'] ?? 0) === 1,
            'native' => false,
            'info' => '',
            'slot' => null,
            'children' => [],
            'validation' => $validation,
            'dataSource' => $field['options_source'] ?? null,
            'conditions' => array_values((array) (($field['link_rules']['rules'] ?? []))),
            'events' => [],
            'access' => [],
            'database' => [
                'columnType' => (string) ($field['column_type'] ?? ''),
                'nullable' => (int) ($field['nullable'] ?? 1) === 1,
                'unsigned' => (int) ($field['unsigned'] ?? 0) === 1,
                'index' => (string) ($field['index_type'] ?? 'none'),
                'comment' => (string) ($field['comment'] ?? ''),
                'relation' => [
                    'type' => (string) ($field['relation_type'] ?? 'none'),
                    'table' => (string) ($field['relation_table'] ?? ''),
                    'labelField' => (string) ($field['relation_label_field'] ?? ''),
                    'valueField' => (string) ($field['relation_value_field'] ?? ''),
                    'multiple' => (int) ($field['relation_multiple'] ?? 0) === 1,
                    'onDelete' => (string) ($field['relation_on_delete'] ?? 'restrict'),
                ],
            ],
            'list' => [
                'show' => (int) ($field['list_show'] ?? 0) === 1,
                'sort' => (int) ($field['list_sort'] ?? 0) === 1,
                'filter' => (string) ($field['list_filter'] ?? ''),
                'formatter' => (string) ($field['list_formatter'] ?? ''),
                'width' => (int) ($field['list_width'] ?? 0),
            ],
            'layout' => ['span' => (int) ($field['form_span'] ?? 24), 'group' => (string) ($field['form_group'] ?? '')],
            'extensions' => ['legacy' => $field],
        ];
    }

    private function valueType(string $type, string $columnType): string
    {
        if (in_array($type, ['checkbox', 'transfer', 'images', 'files', 'daterange', 'datetimerange'], true)) return 'array';
        if (in_array($type, ['number', 'slider', 'rate'], true) || preg_match('/^(?:tinyint|smallint|mediumint|int|bigint|decimal|float|double)/', $columnType)) return 'number';
        if ($type === 'switch') return 'boolean';
        if ($type === 'json') return 'object';
        return 'string';
    }
}
