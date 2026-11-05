<?php

declare(strict_types=1);

namespace app\admin\ai\service;

use app\admin\ai\model\AiOutbox;
use Closure;
use Webman\RedisQueue\Redis;
use RuntimeException;
use Throwable;

/** 以短租约原子认领 AI outbox，并通过 owner fencing 防止旧投递器覆盖接管结果。 */
final class AiOutboxDispatcher
{
    private readonly Closure $claim;
    private readonly Closure $enqueue;
    private readonly Closure $transition;
    private readonly string $owner;
    private readonly Closure $clock;
    private readonly int $leaseSeconds;

    public function __construct(callable $claim, callable $enqueue, callable $transition, ?string $owner = null, ?callable $clock = null, int $leaseSeconds = 30)
    {
        $this->claim = Closure::fromCallable($claim);
        $this->enqueue = Closure::fromCallable($enqueue);
        $this->transition = Closure::fromCallable($transition);
        $this->owner = $owner ?? bin2hex(random_bytes(16));
        $this->clock = $clock === null ? static fn (): int => time() : Closure::fromCallable($clock);
        $this->leaseSeconds = $leaseSeconds;
    }

    public static function database(?callable $enqueue = null): self
    {
        return new self(
            static function (int $limit, string $owner, int $now, int $leaseExpiresAt): array {
                $nowAt = date('Y-m-d H:i:s', $now);
                $claimable = static function ($query) use ($nowAt): void {
                    $query->where(static fn ($pending) => $pending->where('status', 'pending')->where('available_at', '<=', $nowAt))
                        ->orWhere(static fn ($processing) => $processing->where('status', 'processing')->where('lease_expires_at', '<=', $nowAt));
                };
                $candidates = AiOutbox::where($claimable)->orderBy('id')->limit($limit)->get()->toArray();
                $claimed = [];
                foreach ($candidates as $candidate) {
                    $updated = AiOutbox::where('id', (int) $candidate['id'])->where($claimable)->update([
                        'status' => 'processing',
                        'lease_owner' => $owner,
                        'lease_expires_at' => date('Y-m-d H:i:s', $leaseExpiresAt),
                        'updated_at' => $nowAt,
                    ]);
                    if ($updated === 1) {
                        $claimed[] = array_replace($candidate, [
                            'status' => 'processing',
                            'lease_owner' => $owner,
                            'lease_expires_at' => $leaseExpiresAt,
                        ]);
                    }
                }
                return $claimed;
            },
            $enqueue ?? static fn (array $payload): bool => Redis::connection('ai-agent')->send('ai-agent', $payload),
            static fn (int $id, string $owner, array $data): bool => AiOutbox::where('id', $id)->where('status', 'processing')->where('lease_owner', $owner)->update(array_merge($data, ['updated_at' => date('Y-m-d H:i:s')])) === 1
        );
    }

    public function dispatch(int $limit = 100): int
    {
        $now = (int) ($this->clock)();
        $rows = ($this->claim)($limit, $this->owner, $now, $now + $this->leaseSeconds);
        $dispatched = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            try {
                if (($this->enqueue)((array) $row['payload']) !== true) {
                    throw new RuntimeException('AI 队列投递未确认');
                }
                if (($this->transition)($id, $this->owner, [
                    'status' => 'dispatched',
                    'dispatched_at' => date('Y-m-d H:i:s', $now),
                    'lease_owner' => null,
                    'lease_expires_at' => null,
                    'last_error' => null,
                ])) {
                    $dispatched++;
                }
            } catch (Throwable $exception) {
                ($this->transition)($id, $this->owner, [
                    'status' => 'pending',
                    'attempts' => (int) ($row['attempts'] ?? 0) + 1,
                    'last_error' => mb_substr($exception->getMessage(), 0, 1000),
                    'available_at' => date('Y-m-d H:i:s', $now + 5),
                    'lease_owner' => null,
                    'lease_expires_at' => null,
                ]);
            }
        }
        return $dispatched;
    }
}
