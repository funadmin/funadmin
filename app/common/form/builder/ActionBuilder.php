<?php

declare(strict_types=1);

namespace app\common\form\builder;

class ActionBuilder
{
    private array $steps;

    public function __construct(array &$action)
    {
        if (array_is_list($action)) {
            $this->steps =& $action;
            return;
        }
        $action['steps'] ??= [];
        $this->steps =& $action['steps'];
    }

    public function step(string $type, array $parameters = []): self
    {
        $this->steps[] = ['type' => $type] + $parameters;
        return $this;
    }

    public function setValue(string $target, mixed $value): self { return $this->step('setValue', compact('target', 'value')); }
    public function copyValue(string $source, string $target): self { return $this->step('copyValue', compact('source', 'target')); }
    public function clearValue(string $target): self { return $this->step('clearValue', compact('target')); }
    public function show(string $target): self { return $this->step('show', compact('target')); }
    public function hide(string $target): self { return $this->step('hide', compact('target')); }
    public function enable(string $target): self { return $this->step('enable', compact('target')); }
    public function disable(string $target): self { return $this->step('disable', compact('target')); }
    public function setRequired(string $target, bool $value = true): self { return $this->step('setRequired', compact('target', 'value')); }
    public function validate(?string $target = null): self { return $this->step('validate', $target === null ? [] : compact('target')); }

    public function request(string $key, array $parameters = [], ?string $concurrency = null): self
    {
        $payload = ['key' => $key, 'parameters' => $parameters];
        if ($concurrency !== null) $payload['concurrency'] = $concurrency;
        return $this->step('request', $payload);
    }

    public function notify(string $message, string $tone = 'success'): self { return $this->step('notify', compact('message', 'tone')); }
    public function openDialog(string $key, array $params = []): self { return $this->step('openDialog', compact('key', 'params')); }
    public function navigate(string $route, array $params = [], array $query = []): self { return $this->step('navigate', compact('route', 'params', 'query')); }
    public function submit(): self { return $this->step('submit'); }
    public function reset(): self { return $this->step('reset'); }
}
