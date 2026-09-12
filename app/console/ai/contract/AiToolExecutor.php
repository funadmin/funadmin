<?php

declare(strict_types=1);

namespace app\console\ai\contract;

/** 阶段三工具执行端口；阶段二仅允许显式注入 stub。 */
interface AiToolExecutor
{
    public function execute(array $call): array;
}
