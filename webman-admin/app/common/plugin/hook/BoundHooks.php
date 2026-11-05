<?php

declare(strict_types=1);
namespace app\common\plugin\hook;

/** Nested calls inherit their dispatch chain; new root calls remain independent. */
final class BoundHooks
{
    public function __construct(private readonly HookRuntime $runtime, private readonly array $chain) {}
    public function action(string $point, mixed $payload = null, array $context = []): void
    {
        $this->runtime->dispatch('action', $point, $payload, $context, $this->chain);
    }
    public function filter(string $point, mixed $value, array $context = []): mixed
    {
        return $this->runtime->dispatch('filter', $point, $value, $context, $this->chain);
    }
}
