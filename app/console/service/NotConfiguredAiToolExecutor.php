<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\ai\provider\AiProviderException;

/** 阶段三工具执行能力未配置时拒绝执行。 */
final class NotConfiguredAiToolExecutor implements AiToolExecutor
{
    public function execute(array $call): array
    {
        throw new AiProviderException('not_configured', 'AI 工具执行能力尚未配置');
    }
}
