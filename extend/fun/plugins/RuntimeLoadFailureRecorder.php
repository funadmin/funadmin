<?php

declare(strict_types=1);

namespace fun\plugins;

/** 将插件运行时边界失败转换为可持久化记录。 */
final class RuntimeLoadFailureRecorder
{
    public function __construct(private readonly mixed $persist)
    {
    }

    public function record(string $plugin, string $boundary, \Throwable $exception): void
    {
        try {
            ($this->persist)([
                'plugin_name' => $plugin,
                'operation' => 'runtime_load',
                'stage' => $boundary,
                'error_stage' => $boundary,
                'progress' => 0,
                'result' => 'failed',
                'error_message' => substr($exception->getMessage(), 0, 2000),
                'source' => 'runtime',
                'package_hash' => str_repeat('0', 64),
                'status' => 1,
            ]);
        } catch (\Throwable $recordException) {
            error_log(sprintf('记录插件 %s 的 %s 加载失败异常：%s', $plugin, $boundary, $recordException->getMessage()));
        }
    }
}
