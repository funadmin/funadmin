<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 将业务数据 purge 与卸载分离，并保证每次结果都有独立审计。 */
final class PluginPurgeCoordinator
{
    public function __construct(
        private readonly mixed $loadPlugin,
        private readonly mixed $audit
    ) {
    }

    public function purge(string $name, string $confirmation): void
    {
        if ($confirmation !== $name) {
            throw new RuntimeException('彻底清理数据时必须输入插件名称二次确认');
        }
        try {
            $plugin = ($this->loadPlugin)($name);
            if (!method_exists($plugin, 'purgeData') || $plugin->purgeData() === false) {
                throw new RuntimeException('插件拒绝或无法清理业务数据');
            }
            ($this->audit)(['name' => $name, 'operation' => 'purge', 'result' => 'success']);
        } catch (\Throwable $exception) {
            ($this->audit)([
                'name' => $name,
                'operation' => 'purge',
                'result' => 'failed',
                'error' => substr($exception->getMessage(), 0, 2000),
            ]);
            throw $exception;
        }
    }
}
