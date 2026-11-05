<?php

declare(strict_types=1);

namespace app\common\form\schema;

final class FormNode
{
    public function __construct(private readonly array $definition)
    {
    }

    public function id(): string
    {
        return (string) ($this->definition['id'] ?? '');
    }

    public function field(): string
    {
        return (string) ($this->definition['field'] ?? '');
    }

    public function kind(): string
    {
        return (string) ($this->definition['kind'] ?? 'field');
    }

    public function definition(): array
    {
        return $this->definition;
    }
}
