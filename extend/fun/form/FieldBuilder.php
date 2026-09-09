<?php

declare(strict_types=1);

namespace fun\form;

final class FieldBuilder
{
    public function __construct(private array &$node)
    {
    }

    public function required(string $message = ''): self
    {
        $this->node['validation'][] = ['type' => 'required', 'message' => $message ?: $this->node['title'] . '不能为空', 'trigger' => ['blur', 'change']];
        return $this;
    }

    public function validation(?callable $builder = null): ValidationBuilder
    {
        $validation = new ValidationBuilder($this->node['validation']);
        if ($builder !== null) $builder($validation);
        return $validation;
    }

    public function rule(string $type, mixed $value = true, string $message = '', array $trigger = ['blur', 'change']): self
    {
        $rule = ['type' => $type, 'value' => $value, 'trigger' => $trigger];
        if ($message !== '') $rule['message'] = $message;
        $this->node['validation'][] = $rule;
        return $this;
    }

    public function placeholder(string $placeholder): self { return $this->prop('placeholder', $placeholder); }

    public function prop(string $name, mixed $value): self
    {
        $this->node['props'][$name] = $value;
        return $this;
    }

    public function attrs(array $attrs): self { $this->node['attrs'] = $attrs; return $this; }
    public function className(string $className): self { $this->node['className'] = $className; return $this; }
    public function style(array $style): self { $this->node['style'] = $style; return $this; }
    public function info(string $info): self { $this->node['info'] = $info; return $this; }
    public function slot(string $slot): self { $this->node['slot'] = $slot; return $this; }
    public function extension(string $name, mixed $value): self { $this->node['extensions'][$name] = $value; return $this; }
    public function format(string $format): self { return $this->prop('valueFormat', $format); }

    public function column(string $type, bool $nullable = true, bool $unsigned = false, string $index = 'none', string $comment = ''): self
    {
        $this->node['database'] = array_replace($this->node['database'], compact('nullable', 'unsigned', 'index', 'comment'), ['columnType' => $type]);
        return $this;
    }

    public function relation(string $type, string $table = '', string $labelField = '', string $valueField = '', bool $multiple = false, string $onDelete = 'restrict'): self
    {
        $this->node['database']['relation'] = compact('type', 'table', 'labelField', 'valueField', 'multiple', 'onDelete');
        return $this;
    }

    public function span(int $span): self { $this->node['layout']['span'] = $span; return $this; }
    public function default(mixed $value): self { $this->node['defaultValue'] = $value; return $this; }

    public function options(array $options): self
    {
        return $this->dataSource(['kind' => 'static', 'options' => array_values($options)]);
    }

    public function dataSource(array $definition): self { $this->node['dataSource'] = $definition; return $this; }
    public function dataSourceBuilder(string $kind, array $definition = []): DataSourceBuilder
    {
        $this->node['dataSource'] = ['kind' => $kind] + $definition;
        return new DataSourceBuilder($this->node['dataSource']);
    }
    public function dataSourceRef(string $id): self { return $this->dataSource(['ref' => $id]); }

    public function condition(array $when, array $then): self
    {
        $this->node['conditions'][] = compact('when', 'then');
        return $this;
    }

    public function conditions(?callable $builder = null): ConditionBuilder
    {
        $conditions = new ConditionBuilder($this->node['conditions']);
        if ($builder !== null) $builder($conditions);
        return $conditions;
    }

    public function event(string $event): ActionBuilder
    {
        $this->node['events'][$event] ??= [];
        return new ActionBuilder($this->node['events'][$event]);
    }

    public function access(array $read = [], array $write = []): self
    {
        $this->node['access'] = compact('read', 'write');
        return $this;
    }

    public function list(bool $show = true, bool $sort = false, string $filter = '', string $formatter = '', int $width = 0): self
    {
        $this->node['list'] = compact('show', 'sort', 'filter', 'formatter', 'width');
        return $this;
    }

    public function form(bool $hidden = false, bool $disabled = false, int $span = 24, string $group = ''): self
    {
        $this->node['hidden'] = $hidden;
        $this->node['disabled'] = $disabled;
        $this->node['layout'] = compact('span', 'group');
        return $this;
    }

    public function table(string $table): self
    {
        $this->node['dataSource'] ??= ['kind' => 'relation'];
        $this->node['dataSource']['table'] = $table;
        $this->node['database']['relation']['table'] = $table;
        return $this;
    }

    public function valueField(string $field): self
    {
        $this->node['dataSource'] ??= ['kind' => 'relation'];
        $this->node['dataSource']['valueField'] = $field;
        $this->node['database']['relation']['valueField'] = $field;
        return $this;
    }

    public function labelField(string $field): self
    {
        $this->node['dataSource'] ??= ['kind' => 'relation'];
        $this->node['dataSource']['labelField'] = $field;
        $this->node['database']['relation']['labelField'] = $field;
        return $this;
    }

    public function children(callable $builder): self
    {
        $builder(new NodeCollection($this->node['children']));
        return $this;
    }
}
