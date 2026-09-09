<?php

declare(strict_types=1);

namespace fun\form;

class NodeCollection
{
    private const FIELD_DEFINITIONS = [
        'input' => ['varchar(255)', 'string'], 'password' => ['varchar(255)', 'string'],
        'textarea' => ['text', 'string'], 'mention' => ['varchar(500)', 'string'],
        'number' => ['int', 'number'], 'select' => ['varchar(100)', 'string'],
        'selectV2' => ['varchar(100)', 'string'], 'treeSelect' => ['bigint', 'string'],
        'cascader' => ['varchar(255)', 'string'], 'radio' => ['varchar(100)', 'string'],
        'checkbox' => ['json', 'array'], 'switch' => ['tinyint(1)', 'boolean'],
        'transfer' => ['json', 'array'], 'date' => ['date', 'string'],
        'datetime' => ['datetime', 'string'], 'daterange' => ['json', 'array'],
        'datetimerange' => ['json', 'array'], 'time' => ['time', 'string'],
        'timeSelect' => ['time', 'string'], 'slider' => ['int', 'number'],
        'rate' => ['tinyint', 'number'], 'color' => ['varchar(20)', 'string'],
        'image' => ['varchar(500)', 'string'], 'images' => ['json', 'array'],
        'file' => ['json', 'array'], 'files' => ['json', 'array'],
        'dictionary' => ['varchar(100)', 'string'], 'relation' => ['bigint', 'number'],
        'department' => ['bigint', 'number'], 'user' => ['bigint', 'number'],
        'richtext' => ['longtext', 'string'], 'json' => ['json', 'object'],
        'hidden' => ['varchar(255)', 'string'], 'readonly' => ['varchar(255)', 'string'],
    ];

    public function __construct(protected array &$nodes)
    {
    }

    public function input(string $field, string $title): FieldBuilder { return $this->coreField('input', $field, $title); }
    public function password(string $field, string $title): FieldBuilder { return $this->coreField('password', $field, $title); }
    public function textarea(string $field, string $title): FieldBuilder { return $this->coreField('textarea', $field, $title); }
    public function mention(string $field, string $title): FieldBuilder { return $this->coreField('mention', $field, $title); }
    public function number(string $field, string $title): FieldBuilder { return $this->coreField('number', $field, $title); }
    public function select(string $field, string $title): FieldBuilder { return $this->coreField('select', $field, $title); }
    public function selectV2(string $field, string $title): FieldBuilder { return $this->coreField('selectV2', $field, $title); }
    public function treeSelect(string $field, string $title): FieldBuilder { return $this->coreField('treeSelect', $field, $title); }
    public function cascader(string $field, string $title): FieldBuilder { return $this->coreField('cascader', $field, $title); }
    public function radio(string $field, string $title): FieldBuilder { return $this->coreField('radio', $field, $title); }
    public function checkbox(string $field, string $title): FieldBuilder { return $this->coreField('checkbox', $field, $title); }
    public function switch(string $field, string $title): FieldBuilder { return $this->coreField('switch', $field, $title); }
    public function transfer(string $field, string $title): FieldBuilder { return $this->coreField('transfer', $field, $title); }
    public function date(string $field, string $title): FieldBuilder { return $this->coreField('date', $field, $title); }
    public function datetime(string $field, string $title): FieldBuilder { return $this->coreField('datetime', $field, $title); }
    public function daterange(string $field, string $title): FieldBuilder { return $this->coreField('daterange', $field, $title); }
    public function datetimerange(string $field, string $title): FieldBuilder { return $this->coreField('datetimerange', $field, $title); }
    public function time(string $field, string $title): FieldBuilder { return $this->coreField('time', $field, $title); }
    public function timeSelect(string $field, string $title): FieldBuilder { return $this->coreField('timeSelect', $field, $title); }
    public function slider(string $field, string $title): FieldBuilder { return $this->coreField('slider', $field, $title); }
    public function rate(string $field, string $title): FieldBuilder { return $this->coreField('rate', $field, $title); }
    public function color(string $field, string $title): FieldBuilder { return $this->coreField('color', $field, $title); }
    public function image(string $field, string $title): FieldBuilder { return $this->coreField('image', $field, $title); }
    public function images(string $field, string $title): FieldBuilder { return $this->coreField('images', $field, $title); }
    public function file(string $field, string $title): FieldBuilder { return $this->coreField('file', $field, $title); }
    public function files(string $field, string $title): FieldBuilder { return $this->coreField('files', $field, $title); }
    public function dictionary(string $field, string $title): FieldBuilder { return $this->coreField('dictionary', $field, $title); }
    public function department(string $field, string $title): FieldBuilder { return $this->coreField('department', $field, $title); }
    public function user(string $field, string $title): FieldBuilder { return $this->coreField('user', $field, $title); }
    public function richtext(string $field, string $title): FieldBuilder { return $this->coreField('richtext', $field, $title); }
    public function json(string $field, string $title): FieldBuilder { return $this->coreField('json', $field, $title); }
    public function hidden(string $field, string $title): FieldBuilder { return $this->coreField('hidden', $field, $title)->form(hidden: true); }
    public function readonly(string $field, string $title): FieldBuilder { return $this->coreField('readonly', $field, $title)->form(disabled: true); }

    public function relation(string $field, string $title): FieldBuilder
    {
        return $this->coreField('relation', $field, $title)->dataSource(['kind' => 'relation'])->relation('belongs_to');
    }

    public function custom(string $type, string $field, string $title): FieldBuilder
    {
        if (!str_contains($type, ':')) {
            throw new \InvalidArgumentException('自定义组件类型必须使用插件命名空间');
        }
        return $this->field($type, $field, $title, 'json', 'object');
    }

    public function group(string $id, string $title, ?callable $children = null): FieldBuilder { return $this->layoutNode('group', $id, $title, $children); }
    public function grid(string $id, string $title, ?callable $children = null): FieldBuilder { return $this->layoutNode('grid', $id, $title, $children); }
    public function tabs(string $id, string $title, ?callable $children = null): FieldBuilder { return $this->layoutNode('tabs', $id, $title, $children); }
    public function collapse(string $id, string $title, ?callable $children = null): FieldBuilder { return $this->layoutNode('collapse', $id, $title, $children); }
    public function divider(string $id, string $title): FieldBuilder { return $this->layoutNode('divider', $id, $title); }
    public function text(string $id, string $title): FieldBuilder { return $this->layoutNode('text', $id, $title); }

    public function slot(string $id, string $title, string $slot): FieldBuilder
    {
        return $this->layoutNode('text', $id, $title)->slot($slot);
    }

    public function repeatable(string $field, string $title, string $table, string $valueField, ?callable $children = null): FieldBuilder
    {
        return $this->relationContainer('repeatable', $field, $title, $table, $valueField, $children);
    }

    public function subform(string $field, string $title, string $table, string $valueField, ?callable $children = null): FieldBuilder
    {
        return $this->relationContainer('subform', $field, $title, $table, $valueField, $children);
    }

    public function field(string $type, string $field, string $title, string $columnType, string $valueType = 'string'): FieldBuilder
    {
        $this->nodes[] = $this->baseNode('field', $type, $field, $title, $columnType, $valueType);
        return new FieldBuilder($this->nodes[array_key_last($this->nodes)]);
    }

    private function coreField(string $type, string $field, string $title): FieldBuilder
    {
        [$columnType, $valueType] = self::FIELD_DEFINITIONS[$type];
        return $this->field($type, $field, $title, $columnType, $valueType);
    }

    private function layoutNode(string $type, string $id, string $title, ?callable $children = null): FieldBuilder
    {
        $node = $this->baseNode('layout', $type, '', $title, null, 'object');
        $node['id'] = $this->id($id);
        $this->nodes[] = $node;
        $builder = new FieldBuilder($this->nodes[array_key_last($this->nodes)]);
        if ($children !== null) $builder->children($children);
        return $builder;
    }

    private function relationContainer(string $type, string $field, string $title, string $table, string $valueField, ?callable $children): FieldBuilder
    {
        $builder = $this->field($type, $field, $title, '', 'array')
            ->default([])
            ->relation('has_many', $table, '', $valueField, true);
        if ($children !== null) $builder->children($children);
        return $builder;
    }

    protected function baseNode(string $kind, string $type, string $field, string $title, ?string $columnType, string $valueType): array
    {
        return [
            'id' => $this->id($field !== '' ? $field : $type . '_' . count($this->nodes)),
            'kind' => $kind, 'type' => $type, 'field' => $kind === 'field' ? $field : null, 'title' => $title,
            'defaultValue' => null, 'valueType' => $valueType, 'props' => [], 'attrs' => [], 'className' => '',
            'style' => [], 'hidden' => false, 'disabled' => false, 'native' => false, 'info' => '', 'slot' => null,
            'children' => [], 'validation' => [], 'dataSource' => null, 'conditions' => [], 'events' => [], 'access' => [],
            'database' => [
                'columnType' => $columnType ?? '', 'nullable' => true, 'unsigned' => false, 'index' => 'none', 'comment' => '',
                'relation' => ['type' => 'none', 'table' => '', 'labelField' => '', 'valueField' => '', 'multiple' => false, 'onDelete' => 'restrict'],
            ],
            'list' => ['show' => $kind === 'field', 'sort' => false, 'filter' => '', 'formatter' => '', 'width' => 0],
            'layout' => ['span' => 24, 'group' => ''], 'extensions' => [],
        ];
    }

    private function id(string $seed): string
    {
        return 'node_' . substr(hash('sha256', $seed . ':' . count($this->nodes)), 0, 16);
    }
}
