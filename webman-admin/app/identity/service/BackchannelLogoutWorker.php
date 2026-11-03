<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\BackchannelLogoutDelivery;
use Throwable;

/** 使用数据库条件更新实现 backchannel 投递的单 owner claim。 */
final class BackchannelLogoutWorker
{
    private readonly ?\Closure $sender;

    public function __construct(private readonly ?string $workerId = null, private readonly int $leaseSeconds = 30, ?callable $sender = null)
    {
        $this->sender = $sender === null ? null : \Closure::fromCallable($sender);
    }

    public function work(int $limit = 100, ?int $tenantId = null): int
    {
        $workerId = $this->workerId ?: bin2hex(random_bytes(16));
        $claimed = $this->claim($workerId, max(1, min(1000, $limit)), $tenantId);
        $completed = 0;
        foreach ($claimed as $row) {
            $lockToken = (string) $row['lock_token'];
            try {
                if (!(new IdentitySsoConfigService())->allowsBackchannelLogout((int) $row['tenant_id'])) {
                    $completed += $this->cancel((int) $row['tenant_id'], (int) $row['id'], $workerId, $lockToken);
                    continue;
                }
                $responseStatus = $this->sender !== null ? ($this->sender)($row) : (new BackchannelLogoutDispatcher())->send($row);
                $completed += $this->complete((int) $row['tenant_id'], (int) $row['id'], $workerId, $lockToken, $responseStatus);
            } catch (Throwable $exception) {
                $this->fail((int) $row['tenant_id'], (int) $row['id'], $workerId, $lockToken, (int) $row['attempts'], substr($exception->getMessage(), 0, 64));
            }
        }
        return $completed;
    }

    public function retry(int $tenantId, int $deliveryId): bool
    {
        $now = date('Y-m-d H:i:s');
        BackchannelLogoutDelivery::forTenant($tenantId)->where('id', $deliveryId)->whereIn('status', ['pending', 'dead'])->update([
            'status' => 'pending', 'attempts' => 0, 'dead_at' => null, 'next_attempt_at' => $now,
            'worker_id' => null, 'lock_token' => null, 'lease_expires_at' => null,
        ]);
        $workerId = $this->workerId ?: bin2hex(random_bytes(16));
        $row = $this->claimOne($tenantId, $deliveryId, $workerId);
        if ($row === null) return false;
        $lockToken = (string) $row['lock_token'];
        try {
            if (!(new IdentitySsoConfigService())->allowsBackchannelLogout((int) $row['tenant_id'])) {
                return $this->cancel($tenantId, $deliveryId, $workerId, $lockToken) === 1;
            }
            $status = $this->sender !== null ? ($this->sender)($row) : (new BackchannelLogoutDispatcher())->send($row);
            return $this->complete($tenantId, $deliveryId, $workerId, $lockToken, $status) === 1;
        } catch (Throwable $exception) {
            $this->fail($tenantId, $deliveryId, $workerId, $lockToken, (int) $row['attempts'], substr($exception->getMessage(), 0, 64));
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    public function claim(string $workerId, int $limit, ?int $tenantId = null): array
    {
        $now = date('Y-m-d H:i:s');
        $lease = date('Y-m-d H:i:s', time() + $this->leaseSeconds);
        $query = BackchannelLogoutDelivery::where(self::claimable($now));
        if ($tenantId !== null) $query->where('tenant_id', $tenantId);
        $rows = $query->orderBy('id')->limit($limit)->get()->toArray();
        $claimed = [];
        foreach ($rows as $row) {
            $lockToken = hash('sha256', $workerId . '|' . $row['id'] . '|' . bin2hex(random_bytes(16)));
            $updated = BackchannelLogoutDelivery::forTenant((int) $row['tenant_id'])->where('id', (int) $row['id'])
                ->where(self::claimable($now))
                ->update(['status' => 'processing', 'worker_id' => $workerId, 'lock_token' => $lockToken, 'lease_expires_at' => $lease, 'locked_at' => $now, 'updated_at' => $now]);
            if ($updated === 1) $claimed[] = array_replace($row, ['status' => 'processing', 'worker_id' => $workerId, 'lock_token' => $lockToken, 'lease_expires_at' => $lease]);
        }
        return $claimed;
    }

    public function preview(int $tenantId, int $limit = 100): int
    {
        $now = date('Y-m-d H:i:s');
        return BackchannelLogoutDelivery::forTenant($tenantId)
            ->where(self::claimable($now))
            ->limit(max(1, min(1000, $limit)))->pluck('id')->count();
    }

    private function claimOne(int $tenantId, int $deliveryId, string $workerId): ?array
    {
        $now = date('Y-m-d H:i:s');
        $lease = date('Y-m-d H:i:s', time() + $this->leaseSeconds);
        $lockToken = hash('sha256', $workerId . '|' . $deliveryId . '|' . bin2hex(random_bytes(16)));
        $updated = BackchannelLogoutDelivery::forTenant($tenantId)->where('id', $deliveryId)->where(self::claimable($now))->update([
            'status' => 'processing', 'worker_id' => $workerId, 'lock_token' => $lockToken,
            'lease_expires_at' => $lease, 'locked_at' => $now, 'updated_at' => $now,
        ]);
        if ($updated !== 1) return null;
        $row = BackchannelLogoutDelivery::forTenant($tenantId)->where('id', $deliveryId)->where('worker_id', $workerId)->where('lock_token', $lockToken)->first();
        return $row?->toArray();
    }

    private static function claimable(string $now): \Closure
    {
        return static function ($query) use ($now): void {
            $query->where(static fn ($pending) => $pending->where('status', 'pending')->where('next_attempt_at', '<=', $now))
                ->orWhere(static fn ($processing) => $processing->where('status', 'processing')->where('lease_expires_at', '<=', $now));
        };
    }

    public function heartbeat(int $tenantId, int $id, string $workerId, string $lockToken): bool
    {
        return BackchannelLogoutDelivery::forTenant($tenantId)->where('id', $id)->where('status', 'processing')->where('worker_id', $workerId)->where('lock_token', $lockToken)->update([
            'lease_expires_at' => date('Y-m-d H:i:s', time() + $this->leaseSeconds),
            'updated_at' => date('Y-m-d H:i:s'),
        ]) === 1;
    }

    private function cancel(int $tenantId, int $id, string $workerId, string $lockToken): int
    {
        return BackchannelLogoutDelivery::forTenant($tenantId)->where('id', $id)->where('status', 'processing')->where('worker_id', $workerId)->where('lock_token', $lockToken)->update([
            'status' => 'dead', 'dead_at' => date('Y-m-d H:i:s'), 'last_error_code' => 'backchannel_disabled',
            'worker_id' => null, 'lock_token' => null, 'lease_expires_at' => null, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function complete(int $tenantId, int $id, string $workerId, string $lockToken, int $status): int
    {
        return BackchannelLogoutDelivery::forTenant($tenantId)->where('id', $id)->where('status', 'processing')->where('worker_id', $workerId)->where('lock_token', $lockToken)->update([
            'status' => 'delivered', 'delivered_at' => date('Y-m-d H:i:s'), 'response_status' => $status,
            'worker_id' => null, 'lock_token' => null, 'lease_expires_at' => null, 'last_error_code' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function fail(int $tenantId, int $id, string $workerId, string $lockToken, int $attempts, string $error): void
    {
        $attempt = $attempts + 1;
        $dead = $attempt >= 5;
        BackchannelLogoutDelivery::forTenant($tenantId)->where('id', $id)->where('status', 'processing')->where('worker_id', $workerId)->where('lock_token', $lockToken)->update([
            'status' => $dead ? 'dead' : 'pending', 'attempts' => $attempt,
            'next_attempt_at' => date('Y-m-d H:i:s', time() + min(3600, 30 * (2 ** ($attempt - 1)))),
            'dead_at' => $dead ? date('Y-m-d H:i:s') : null, 'last_error_code' => $error,
            'worker_id' => null, 'lock_token' => null, 'lease_expires_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
