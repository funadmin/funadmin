<?php

declare(strict_types=1);

namespace fun\form;

class NodeCollection
{
    public function __construct(protected array &$nodes)
    {
    }

    public function input(string $field, string $title): FieldBuilder
    {
        return $this->field('input', $field, $title, 'varchar(255)');
    }

    public function number(string $field, string $title): FieldBuilder
    {
        return $this->field('number', $field, $title, 'int', 'number');
    }

    public function select(string $field, string $title): FieldBuilder
    {
        return $this->field('select', $field, $title, 'varchar(100)');
    }

    public function date(string $field, string $title): FieldBuilder
    {
        return $this->field('date', $field, $title, 'date');
    }

    public function relation(string $field, string $title): FieldBuilder
    {
        $builder = $this->field('relation', $field, $title, 'bigint', 'number');
        $node =& $this->nodes[array_key_last($this->nodes)];
        $node['dataSource'] = ['mode' => 'relation'];
        $node['database']['relation']['type'] = 'belongs_to';
        return $builder;
    }

    public function custom(string $type, string $field, string $title): FieldBuilder
    {
        if (!str_contains($type, ':')) throw new \InvalidArgumentException('自定义组件类型必须使用插件命名空间');
        return $this->field($type, $field, $title, 'json', 'object');
    }

    public function group(string $id, string $title, ?callable $children = null): FieldBuilder
    {
        $node = $this->baseNode('layout', 'group', '', $title, null, 'string');
        $node['id'] = $this->id($id);
        $this->nodes[] = $node;
        $builder = new FieldBuilder($this->nodes[array_key_last($this->nodes)]);
        if ($children !== null) $builder->children($children);
        return $builder;
    }

    public function field(string $type, string $field, string $title, string $columnType, string $valueType = 'string'): FieldBuilder
    {
        $this->nodes[] = $this->baseNode('field', $type, $field, $title, $columnType, $valueType);
        return new FieldBuilder($this->nodes[array_key_last($this->nodes)]);
    }

    protected function baseNode(string $kind, string $type, string $field, string $title, ?string $columnType, string $valueType): array
    {
        return [
            'id' => $this->id($field !== '' ? $field : $type . '_' . count($this->nodes)),
            'kind' => $kind,
            'type' => $type,
            'field' => $kind === 'field' ? $field : null,
            'title' => $title,
            'defaultValue' => null,
            'valueType' => $valueType,
            'props' => [],
            'attrs' => [],
            'className' => '',
            'style' => [],
            'hidden' => false,
            'disabled' => false,
            'native' => false,
            'info' => '',
            'slot' => null,
            'children' => [],
            'validation' => [],
            'dataSource' => null,
            'conditions' => [],
            'events' => [],
            'access' => [],
            'database' => [
                'columnType' => $columnType ?? '',
                'nullable' => true,
                'unsigned' => false,
                'index' => 'none',
                'comment' => '',
                'relation' => ['type' => 'none', 'table' => '', 'labelField' => '', 'valueField' => '', 'multiple' => false, 'onDelete' => 'restrict'],
            ],
            'list' => ['show' => $kind === 'field', 'sort' => false, 'filter' => '', 'formatter' => '', 'width' => 0],
            'layout' => ['span' => 24, 'group' => ''],
            'extensions' => [],
        ];
    }

    private function id(string $seed): string
    {
        return 'node_' . substr(hash('sha256', $seed . ':' . count($this->nodes)), 0, 16);
    }
}
