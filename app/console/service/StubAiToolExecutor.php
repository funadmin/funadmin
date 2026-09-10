<?php

declare(strict_types=1);

namespace app\console\service;

/** 阶段二工具占位实现，明确拒绝真实执行。 */
final class StubAiToolExecutor implements AiToolExecutor
{
    public function execute(array $call): array
    {
        return ['status' => 'stubbed', 'sideEffect' => false, 'message' => '工具执行将在阶段三启用'];
    }
}
