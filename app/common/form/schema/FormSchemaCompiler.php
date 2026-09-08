<?php

declare(strict_types=1);

namespace app\common\form\schema;

use JsonException;

final class FormSchemaCompiler
{
    public function __construct(private readonly FormSchemaValidator $validator)
    {
    }

    public function compile(array $schema): FormSchema
    {
        $schema = $this->validator->normalize($schema);
        $this->validator->validate($schema);
        $canonical = $this->canonicalize($schema);
        try {
            $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new FormSchemaException('Schema 无法编码：' . $exception->getMessage(), '/');
        }
        return new FormSchema($canonical, $json, $this->projection($canonical['nodes']));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = $this->canonicalize($item);
        return $value;
    }

    private function projection(array $nodes): array
    {
        $result = [];
        foreach ($nodes as $node) {
            $legacy = (array) (($node['extensions']['legacy'] ?? []));
            $database = (array) ($node['database'] ?? []);
            $relation = (array) ($database['relation'] ?? []);
            $list = (array) ($node['list'] ?? []);
            $layout = (array) ($node['layout'] ?? []);
            $validation = (array) ($node['validation'] ?? []);
            $required = array_filter($validation, static fn (array $rule): bool => ($rule['type'] ?? '') === 'required');
            $rules = [];
            foreach ($validation as $rule) {
                $type = (string) ($rule['type'] ?? '');
                if ($type !== '' && $type !== 'required') $rules[$type] = $rule['value'] ?? true;
            }
            $relationContainer = in_array((string) ($node['type'] ?? ''), ['repeatable', 'subform'], true);
            $result[] = array_merge($legacy, [
                'field_name' => (string) ($node['field'] ?? $node['id']),
                'label' => (string) ($node['title'] ?? ''),
                'type' => (string) ($node['type'] ?? 'input'),
                'column_type' => (string) ($database['columnType'] ?? ''),
                'nullable' => ($database['nullable'] ?? true) ? 1 : 0,
                'default_value' => $node['defaultValue'] ?? '',
                'comment' => (string) ($database['comment'] ?? ''),
                'unsigned' => ($database['unsigned'] ?? false) ? 1 : 0,
                'index_type' => (string) ($database['index'] ?? 'none'),
                'control_props' => (array) ($node['props'] ?? []),
                'validate_rules' => $rules === [] ? null : $rules,
                'link_rules' => ['rules' => (array) ($node['conditions'] ?? [])],
                'options_source' => $node['dataSource'] ?? null,
                'relation_type' => (string) ($relation['type'] ?? 'none'),
                'relation_table' => (string) ($relation['table'] ?? ''),
                'relation_label_field' => (string) ($relation['labelField'] ?? ''),
                'relation_value_field' => (string) ($relation['valueField'] ?? ''),
                'relation_multiple' => ($relation['multiple'] ?? false) ? 1 : 0,
                'relation_on_delete' => (string) ($relation['onDelete'] ?? 'restrict'),
                'list_show' => ($list['show'] ?? false) ? 1 : 0,
                'list_sort' => ($list['sort'] ?? false) ? 1 : 0,
                'list_filter' => (string) ($list['filter'] ?? ''),
                'list_formatter' => (string) ($list['formatter'] ?? ''),
                'list_width' => (int) ($list['width'] ?? 0),
                'form_show' => ($node['hidden'] ?? false) ? 0 : 1,
                'form_required' => $required === [] ? 0 : 1,
                'form_group' => (string) ($layout['group'] ?? ''),
                'form_span' => (int) ($layout['span'] ?? 24),
                'form_readonly' => ($node['disabled'] ?? false) ? 1 : 0,
                'sort_order' => count($result),
            ]);
            if (!$relationContainer) {
                $result = array_merge($result, $this->projection((array) ($node['children'] ?? [])));
            }
        }
        return $result;
    }
}
