<?php

declare(strict_types=1);

namespace app\identity\service;

use app\common\model\identity\BackchannelLogoutDelivery;
use Throwable;

/** 使用数据库条件更新实现 backchannel 投递的单 owner claim。 */
final class BackchannelLogoutWorker
{
    public function __construct(private readonly ?string $workerId = null, private readonly int $leaseSeconds = 30)
    {
    }

    public function work(int $limit = 100): int
    {
        $workerId = $this->workerId ?: bin2hex(random_bytes(16));
        $claimed = $this->claim($workerId, max(1, min(1000, $limit)));
        $completed = 0;
        foreach ($claimed as $row) {
            $lockToken = (string) $row['lock_token'];
            try {
                $responseStatus = (new BackchannelLogoutDispatcher())->send($row);
                $completed += $this->complete((int) $row['id'], $workerId, $lockToken, $responseStatus);
            } catch (Throwable $exception) {
                $this->fail((int) $row['id'], $workerId, $lockToken, (int) $row['attempts'], substr($exception->getMessage(), 0, 64));
            }
        }
        return $completed;
    }

    public function retry(int $deliveryId): bool
    {
        $now = date('Y-m-d H:i:s');
        BackchannelLogoutDelivery::where('id', $deliveryId)->whereIn('status', ['pending', 'dead'])->update([
            'status' => 'pending', 'attempts' => 0, 'dead_at' => null, 'next_attempt_at' => $now,
            'worker_id' => null, 'lock_token' => null, 'lease_expires_at' => null,
        ]);
        $workerId = $this->workerId ?: bin2hex(random_bytes(16));
        $row = $this->claimOne($deliveryId, $workerId);
        if ($row === null) return false;
        $lockToken = (string) $row['lock_token'];
        try {
            $status = (new BackchannelLogoutDispatcher())->send($row);
            return $this->complete($deliveryId, $workerId, $lockToken, $status) === 1;
        } catch (Throwable $exception) {
            $this->fail($deliveryId, $workerId, $lockToken, (int) $row['attempts'], substr($exception->getMessage(), 0, 64));
            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    public function claim(string $workerId, int $limit): array
    {
        $now = date('Y-m-d H:i:s');
        $lease = date('Y-m-d H:i:s', time() + $this->leaseSeconds);
        $rows = BackchannelLogoutDelivery::whereRaw("(`status` = 'pending' AND `next_attempt_at` <= ?) OR (`status` = 'processing' AND `lease_expires_at` <= ?)", [$now, $now])
            ->order('id')->limit($limit)->select()->toArray();
        $claimed = [];
        foreach ($rows as $row) {
            $lockToken = hash('sha256', $workerId . '|' . $row['id'] . '|' . bin2hex(random_bytes(16)));
            $updated = BackchannelLogoutDelivery::where('id', (int) $row['id'])
                ->whereRaw("(`status` = 'pending' AND `next_attempt_at` <= ?) OR (`status` = 'processing' AND `lease_expires_at` <= ?)", [$now, $now])
                ->update(['status' => 'processing', 'worker_id' => $workerId, 'lock_token' => $lockToken, 'lease_expires_at' => $lease, 'locked_at' => $now, 'updated_at' => $now]);
            if ($updated === 1) $claimed[] = array_replace($row, ['status' => 'processing', 'worker_id' => $workerId, 'lock_token' => $lockToken, 'lease_expires_at' => $lease]);
        }
        return $claimed;
    }

    private function claimOne(int $deliveryId, string $workerId): ?array
    {
        $now = date('Y-m-d H:i:s');
        $lease = date('Y-m-d H:i:s', time() + $this->leaseSeconds);
        $lockToken = hash('sha256', $workerId . '|' . $deliveryId . '|' . bin2hex(random_bytes(16)));
        $claimable = "(`status` = 'pending' AND `next_attempt_at` <= ?) OR (`status` = 'processing' AND `lease_expires_at` <= ?)";
        $updated = BackchannelLogoutDelivery::where('id', $deliveryId)->whereRaw($claimable, [$now, $now])->update([
            'status' => 'processing', 'worker_id' => $workerId, 'lock_token' => $lockToken,
            'lease_expires_at' => $lease, 'locked_at' => $now, 'updated_at' => $now,
        ]);
        if ($updated !== 1) return null;
        $row = BackchannelLogoutDelivery::where('id', $deliveryId)->where('worker_id', $workerId)->where('lock_token', $lockToken)->find();
        return $row?->toArray();
    }

    public function heartbeat(int $id, string $workerId, string $lockToken): bool
    {
        return BackchannelLogoutDelivery::where('id', $id)->where('status', 'processing')->where('worker_id', $workerId)->where('lock_token', $lockToken)->update([
            'lease_expires_at' => date('Y-m-d H:i:s', time() + $this->leaseSeconds),
            'updated_at' => date('Y-m-d H:i:s'),
        ]) === 1;
    }

    private function complete(int $id, string $workerId, string $lockToken, int $status): int
    {
        return BackchannelLogoutDelivery::where('id', $id)->where('status', 'processing')->where('worker_id', $workerId)->where('lock_token', $lockToken)->update([
            'status' => 'delivered', 'delivered_at' => date('Y-m-d H:i:s'), 'response_status' => $status,
            'worker_id' => null, 'lock_token' => null, 'lease_expires_at' => null, 'last_error_code' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function fail(int $id, string $workerId, string $lockToken, int $attempts, string $error): void
    {
        $attempt = $attempts + 1;
        $dead = $attempt >= 5;
        BackchannelLogoutDelivery::where('id', $id)->where('status', 'processing')->where('worker_id', $workerId)->where('lock_token', $lockToken)->update([
            'status' => $dead ? 'dead' : 'pending', 'attempts' => $attempt,
            'next_attempt_at' => date('Y-m-d H:i:s', time() + min(3600, 30 * (2 ** ($attempt - 1)))),
            'dead_at' => $dead ? date('Y-m-d H:i:s') : null, 'last_error_code' => $error,
            'worker_id' => null, 'lock_token' => null, 'lease_expires_at' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
