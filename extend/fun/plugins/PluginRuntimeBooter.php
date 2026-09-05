<?php

declare(strict_types=1);

namespace fun\plugins;

/** 对每个插件运行时边界单独隔离，单个失败不阻塞其他插件。 */
final class PluginRuntimeBooter
{
    public function __construct(private readonly mixed $report)
    {
    }

    public function each(iterable $manifests, string $boundary, callable $load): void
    {
        foreach ($manifests as $manifest) {
            try {
                $load($manifest);
            } catch (\Throwable $exception) {
                ($this->report)($manifest->name(), $boundary, $exception);
            }
        }
    }
}
