<?php

declare(strict_types=1);

namespace app\admin\ai\queue;

use app\admin\ai\job\AiAgentJob;
use InvalidArgumentException;
use Webman\RedisQueue\Consumer;

final class AiAgentConsumer implements Consumer
{
    public string $queue = 'ai-agent';
    public string $connection = 'ai-agent';

    public function __construct(private readonly ?AiAgentJob $job = null)
    {
    }

    public function consume($data): void
    {
        if (!is_array($data) || (int) ($data['taskId'] ?? 0) <= 0 || !is_string($data['operationToken'] ?? null) || $data['operationToken'] === '') {
            throw new InvalidArgumentException('AI 队列消息格式无效');
        }
        ($this->job ?? new AiAgentJob())->consume($data);
    }
}
