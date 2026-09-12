<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\console\ai\job\AiAgentJob;
use app\console\ai\model\AiOutbox;
use Closure;
use think\facade\Queue;
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

    public static function database(): self
    {
        return new self(
            static function (int $limit, string $owner, int $now, int $leaseExpiresAt): array {
                $nowAt = date('Y-m-d H:i:s', $now);
                $claimable = '(`status` = \'pending\' AND `available_at` <= ?) OR (`status` = \'processing\' AND `lease_expires_at` <= ?)';
                $candidates = AiOutbox::whereRaw($claimable, [$nowAt, $nowAt])->order('id')->limit($limit)->select()->toArray();
                $claimed = [];
                foreach ($candidates as $candidate) {
                    $updated = AiOutbox::where('id', (int) $candidate['id'])->whereRaw($claimable, [$nowAt, $nowAt])->update([
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
            static fn (array $payload) => Queue::connection('ai-agent')->push(AiAgentJob::class . '@fire', $payload, 'ai-agent'),
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
                ($this->enqueue)((array) $row['payload']);
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
