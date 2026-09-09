<?php

declare(strict_types=1);

namespace fun\form;

/** 构建字段验证 AST，不执行验证。 */
final class ValidationBuilder
{
    public function __construct(private array &$rules)
    {
    }

    public function add(string $type, mixed $value = true, string $message = '', array $trigger = ['blur', 'change']): self
    {
        $rule = ['type' => $type, 'value' => $value, 'trigger' => $trigger];
        if ($message !== '') $rule['message'] = $message;
        $this->rules[] = $rule;
        return $this;
    }

    public function required(string $message = ''): self
    {
        return $this->add('required', true, $message);
    }

    public function async(string $key, array $params = [], int $debounce = 300, int $timeout = 5000): self
    {
        $this->rules[] = [
            'type' => 'async',
            'validator' => compact('key', 'params', 'debounce', 'timeout'),
            'trigger' => ['blur', 'change'],
        ];
        return $this;
    }
}
