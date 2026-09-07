<?php

declare(strict_types=1);

namespace fun\plugins;

/** 插件应用归属索引的只读可信状态。 */
final class PluginOwnershipSnapshot
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

    public function owns(string $code, string $application): bool
    {
        return ($this->plugins[$code]['applications'][$application] ?? false) === true;
    }
}
