<?php

declare(strict_types=1);

namespace fun\form;

final class ActionBuilder
{
    public function __construct(private array &$action)
    {
    }

    public function request(string $key, array $parameters = []): self
    {
        $this->action['steps'][] = ['type' => 'request', 'key' => $key, 'parameters' => $parameters];
        return $this;
    }

    public function notify(string $message, string $tone = 'success'): self
    {
        $this->action['steps'][] = ['type' => 'notify', 'message' => $message, 'tone' => $tone];
        return $this;
    }

    public function reset(): self
    {
        $this->action['steps'][] = ['type' => 'reset'];
        return $this;
    }
}
