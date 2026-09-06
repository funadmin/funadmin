<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/** 将业务数据 purge 与卸载分离，并保证每次结果都有独立审计。 */
final class PluginPurgeCoordinator
{
    public function __construct(
        private readonly mixed $loadPlugin,
        private readonly mixed $audit,
        private readonly ?LifecycleLock $lock = null,
        private readonly mixed $supported = null
    ) {
    }

    public function purge(string $name, string $confirmation, ?callable $cleanup = null): void
    {
        if ($confirmation !== $name) {
            throw new RuntimeException('彻底清理数据时必须输入插件名称二次确认');
        }
        if (is_callable($this->supported) && ($this->supported)($name) !== true) {
            throw new RuntimeException('插件 manifest 未声明支持彻底清理数据');
        }
        $lock = null;
        try {
            $lock = $this->lock?->acquire($name);
            $plugin = ($this->loadPlugin)($name);
            if (!method_exists($plugin, 'purgeData') || $plugin->purgeData() === false) {
                throw new RuntimeException('插件拒绝或无法清理业务数据');
            }
            $cleanup?->__invoke();
            ($this->audit)(['name' => $name, 'operation' => 'purge', 'result' => 'success']);
        } catch (\Throwable $exception) {
            ($this->audit)([
                'name' => $name,
                'operation' => 'purge',
                'result' => 'failed',
                'error' => substr($exception->getMessage(), 0, 2000),
            ]);
            throw $exception;
        } finally {
            $lock?->release();
        }
    }
}
