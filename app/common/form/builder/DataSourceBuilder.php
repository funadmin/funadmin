<?php

declare(strict_types=1);

namespace app\common\form\builder;

final class DataSourceBuilder
{
    public function __construct(private array &$source)
    {
    }

    public function parameter(string $name, mixed $value): self
    {
        $this->source['params'][$name] = $value;
        return $this;
    }

    public function mapping(string $label = 'label', string $value = 'value', string $children = 'children'): self
    {
        $this->source['mapping'] = compact('label', 'value', 'children');
        return $this;
    }

    public function dependsOn(array $fields, string $staleValue = 'clear'): self
    {
        $this->source['dependsOn'] = array_values($fields);
        $this->source['staleValue'] = $staleValue;
        return $this;
    }

    public function search(bool $enabled = true, int $debounce = 300): self
    {
        $this->source['search'] = compact('enabled', 'debounce');
        return $this;
    }

    public function pagination(int $pageSize = 20): self
    {
        $this->source['pagination'] = ['enabled' => true, 'pageSize' => $pageSize];
        return $this;
    }
}
