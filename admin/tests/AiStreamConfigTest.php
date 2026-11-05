<?php

declare(strict_types=1);

namespace Tests;

use app\admin\ai\contract\AiConversationStore;
use app\admin\ai\service\AiEventStreamService;
use PHPUnit\Framework\TestCase;

/** AI 会话控制器用 ai.stream.ticket_secret 构造 SSE 票据服务，未配置时不能让整个接口 500。 */
final class AiStreamConfigTest extends TestCase
{
    public function test_default_ticket_secret_is_long_enough_for_the_stream_service(): void
    {
        $secret = (string) config('ai.stream.ticket_secret', '');
        self::assertGreaterThanOrEqual(32, strlen($secret));
        $store = $this->createStub(AiConversationStore::class);
        self::assertInstanceOf(AiEventStreamService::class, new AiEventStreamService($store, $secret));
    }

    public function test_explicit_environment_secret_wins(): void
    {
        putenv('AI_STREAM_TICKET_SECRET=' . str_repeat('k', 40));
        try {
            $config = require dirname(__DIR__) . '/config/ai.php';
            self::assertSame(str_repeat('k', 40), $config['stream']['ticket_secret']);
        } finally {
            putenv('AI_STREAM_TICKET_SECRET');
        }
    }
}
