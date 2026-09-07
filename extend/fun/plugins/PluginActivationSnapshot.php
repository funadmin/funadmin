<?php

declare(strict_types=1);

namespace fun\plugins;

/** 插件激活清单的只读可信状态。 */
final class PluginActivationSnapshot
{
    private function __construct(
        private readonly bool $trusted,
        private readonly array $plugins = []
    ) {
    }

    public static function trusted(array $plugins): self
    {
        return new self(true, $plugins);
    }

    public static function untrusted(): self
    {
        return new self(false);
    }

    public function isTrusted(): bool
    {
        return $this->trusted;
    }

    public function plugins(): array
    {
        return $this->plugins;
    }
}
