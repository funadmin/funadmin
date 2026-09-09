<?php

declare(strict_types=1);

namespace fun\form;

/** 构建字段条件 AST。 */
final class ConditionBuilder
{
    public function __construct(private array &$conditions)
    {
    }

    public function when(array $condition, string $action, ?string $target = null, mixed $value = null): self
    {
        $then = ['action' => $action];
        if ($target !== null) $then['target'] = $target;
        if ($value !== null) $then['value'] = $value;
        $this->conditions[] = ['when' => $condition, 'then' => $then];
        return $this;
    }

    public function show(array $condition, ?string $target = null): self { return $this->when($condition, 'show', $target); }
    public function hide(array $condition, ?string $target = null): self { return $this->when($condition, 'hide', $target); }
    public function enable(array $condition, ?string $target = null): self { return $this->when($condition, 'enable', $target); }
    public function disable(array $condition, ?string $target = null): self { return $this->when($condition, 'disable', $target); }
    public function required(array $condition, ?string $target = null): self { return $this->when($condition, 'setRequired', $target, true); }
}
