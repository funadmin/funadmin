<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\console\ai\contract\AiConversationStore;
use RuntimeException;

/** 发行单次短期 HMAC 票据，并只读取已持久化事件。 */
final class AiEventStreamService
{
    private readonly mixed $clock;

    public function __construct(private readonly AiConversationStore $store, private readonly string $secret, ?callable $clock = null)
    {
        if (strlen($secret) < 32) throw new RuntimeException('SSE ticket secret 长度不足');
        $this->clock = $clock ?? time(...);
    }

    public function issueTicket(int $adminId, int $taskId, int $ttl = 60): string
    {
        $payload = ['adminId' => $adminId, 'taskId' => $taskId, 'expiresAt' => ($this->clock)() + max(1, min(300, $ttl)), 'nonce' => bin2hex(random_bytes(16))];
        $encoded = $this->encode(json_encode($payload, JSON_THROW_ON_ERROR));
        return $encoded . '.' . $this->encode(hash_hmac('sha256', $encoded, $this->secret, true));
    }

    public function read(string $ticket, int $adminId, int $taskId, int $cursor, int $limit = 100): array
    {
        $payload = $this->verify($ticket);
        if (($payload['adminId'] ?? 0) !== $adminId || ($payload['taskId'] ?? 0) !== $taskId || ($payload['expiresAt'] ?? 0) < ($this->clock)()) {
            throw new RuntimeException('SSE ticket 无效或已过期', 403);
        }
        if (!$this->store->consumeNonce((string) $payload['nonce'], (int) $payload['expiresAt'])) {
            throw new RuntimeException('SSE ticket 已使用', 403);
        }
        return $this->store->events($taskId, max(0, $cursor), max(1, min(500, $limit)));
    }

    private function verify(string $ticket): array
    {
        [$encoded, $signature] = array_pad(explode('.', $ticket, 2), 2, '');
        $expected = $this->encode(hash_hmac('sha256', $encoded, $this->secret, true));
        if ($encoded === '' || !hash_equals($expected, $signature)) throw new RuntimeException('SSE ticket 签名无效', 403);
        $payload = json_decode($this->decode($encoded), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || !isset($payload['nonce'])) throw new RuntimeException('SSE ticket 载荷无效', 403);
        return $payload;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
