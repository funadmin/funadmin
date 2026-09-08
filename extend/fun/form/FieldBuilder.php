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
        $this->node['validation'][] = [
            'type' => 'required',
            'message' => $message !== '' ? $message : $this->node['title'] . '不能为空',
            'trigger' => ['blur', 'change'],
        ];
        return $this;
    }

    public function rule(string $type, mixed $value = true, string $message = '', array $trigger = ['blur', 'change']): self
    {
        $rule = ['type' => $type, 'value' => $value, 'trigger' => $trigger];
        if ($message !== '') $rule['message'] = $message;
        $this->node['validation'][] = $rule;
        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->node['props']['placeholder'] = $placeholder;
        return $this;
    }

    public function prop(string $name, mixed $value): self
    {
        $this->node['props'][$name] = $value;
        return $this;
    }

    public function format(string $format): self
    {
        return $this->prop('valueFormat', $format);
    }

    public function column(string $type, bool $nullable = true, bool $unsigned = false, string $index = 'none'): self
    {
        $this->node['database']['columnType'] = $type;
        $this->node['database']['nullable'] = $nullable;
        $this->node['database']['unsigned'] = $unsigned;
        $this->node['database']['index'] = $index;
        return $this;
    }

    public function span(int $span): self
    {
        $this->node['layout']['span'] = $span;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->node['defaultValue'] = $value;
        return $this;
    }

    public function options(array $options): self
    {
        $this->node['dataSource'] = ['mode' => 'static', 'options' => array_values($options)];
        return $this;
    }

    public function table(string $table): self
    {
        $this->node['dataSource']['table'] = $table;
        $this->node['database']['relation']['table'] = $table;
        return $this;
    }

    public function valueField(string $field): self
    {
        $this->node['dataSource']['value_field'] = $field;
        $this->node['database']['relation']['valueField'] = $field;
        return $this;
    }

    public function labelField(string $field): self
    {
        $this->node['dataSource']['label_field'] = $field;
        $this->node['database']['relation']['labelField'] = $field;
        return $this;
    }

    public function children(callable $builder): self
    {
        $container = new NodeCollection($this->node['children']);
        $builder($container);
        return $this;
    }
}
