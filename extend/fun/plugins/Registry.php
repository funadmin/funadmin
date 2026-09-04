<?php

declare(strict_types=1);

namespace fun\plugins;

/**
 * 以磁盘契约发现插件，以数据库状态决定运行时启用集合。
 */
final class Registry
{
    public function __construct(
        private readonly string $pluginsPath,
        private readonly mixed $records
    ) {
    }

    public function discover(): array
    {
        $entries = [];
        foreach (glob(rtrim($this->pluginsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (!is_file($directory . DIRECTORY_SEPARATOR . 'plugin.json') || !is_file($directory . DIRECTORY_SEPARATOR . 'Plugin.php')) {
                continue;
            }
            try {
                $manifest = Manifest::fromDirectory($directory);
                $entries[$manifest->name()] = $manifest;
            } catch (\Throwable) {
                // 不兼容或损坏的插件仅从发现结果隔离，绝不加载其入口代码。
                continue;
            }
        }
        ksort($entries);
        return $entries;
    }

    public function enabled(): array
    {
        $records = ($this->records)();
        $enabled = [];
        foreach ($this->discover() as $name => $manifest) {
            $record = $records[$name] ?? null;
            if (is_array($record) && ($record['lifecycle_state'] ?? '') === 'enabled') {
                $enabled[$name] = $manifest;
            }
        }
        return $enabled;
    }
}
