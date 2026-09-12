<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use app\console\ai\service\AiOutboxDispatcher;
use app\console\ai\service\AiResumeOutboxService;

function aiReliabilityExpect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$task = ['id'=>9, 'status'=>'paused', 'operation_token'=>str_repeat('a', 64)];
$approval = ['id'=>4, 'status'=>'pending'];
$outbox = [];
$transactionCalls = 0;
$service = new AiResumeOutboxService(
    static function (callable $operation) use (&$transactionCalls): mixed { $transactionCalls++; return $operation(); },
    static function () use (&$approval): array { $approval['status'] = 'approved'; return $approval; },
    static function (int $taskId, string $from, array $data) use (&$task): bool {
        if ($task['id'] !== $taskId || $task['status'] !== $from) return false;
        $task = array_replace($task, $data);
        return true;
    },
    static function (array $event) use (&$outbox): void { $outbox[] = $event; }
);
$result = $service->approveAndSchedule(4, 7, ['action'=>'approve'], $task);
aiReliabilityExpect($transactionCalls === 1, '审批、task CAS 与 outbox 必须位于同一事务');
aiReliabilityExpect($result['status'] === 'approved' && $task['status'] === 'resume_pending', '审批通过必须原子标记 resume_pending');
aiReliabilityExpect(count($outbox) === 1 && $outbox[0]['event_type'] === 'ai.task.resume', '审批通过必须原子写入 resume outbox');

$now = time();
$rows = [1 => ['id'=>1, 'status'=>'pending', 'attempts'=>0, 'available_at'=>$now, 'lease_owner'=>null, 'lease_expires_at'=>null, 'payload'=>['taskId'=>9, 'operationToken'=>str_repeat('a', 64)]]];
$claim = static function (int $limit, string $owner, int $claimedAt, int $leaseExpiresAt) use (&$rows): array {
    $claimed = [];
    foreach ($rows as &$row) {
        $availableAt = is_int($row['available_at']) ? $row['available_at'] : (strtotime((string)$row['available_at']) ?: PHP_INT_MAX);
        $leaseExpiresAtValue = is_int($row['lease_expires_at']) ? $row['lease_expires_at'] : (strtotime((string)($row['lease_expires_at'] ?? '')) ?: 0);
        $available = $row['status'] === 'pending' && $availableAt <= $claimedAt;
        $expired = $row['status'] === 'processing' && $leaseExpiresAtValue <= $claimedAt;
        if ((!$available && !$expired) || count($claimed) >= $limit) continue;
        $row = array_replace($row, ['status'=>'processing', 'lease_owner'=>$owner, 'lease_expires_at'=>$leaseExpiresAt]);
        $claimed[] = $row;
    }
    unset($row);
    return $claimed;
};
$transition = static function (int $id, string $owner, array $data) use (&$rows): bool {
    if (($rows[$id]['status'] ?? '') !== 'processing' || ($rows[$id]['lease_owner'] ?? '') !== $owner) return false;
    $rows[$id] = array_replace($rows[$id], $data);
    return true;
};
$queueAttempts = 0;
$dispatcher = new AiOutboxDispatcher(
    $claim,
    static function (array $payload) use (&$queueAttempts): void { $queueAttempts++; if ($queueAttempts === 1) throw new RuntimeException('queue unavailable'); },
    $transition,
    'dispatcher-a',
    static function () use (&$now): int { return $now; }
);
aiReliabilityExpect($dispatcher->dispatch() === 0 && $rows[1]['status'] === 'pending' && $rows[1]['attempts'] === 1, 'queue 失败必须由 lease owner CAS 回 pending 并递增 attempts');
$now += 5;
aiReliabilityExpect($dispatcher->dispatch() === 1 && $rows[1]['status'] === 'dispatched' && $queueAttempts === 2, 'queue 恢复后必须重新 claim 并成功投递');
aiReliabilityExpect($approval['status'] === 'approved' && $task['status'] === 'resume_pending', '投递失败不得回滚或丢失审批决定');

$rows[2] = ['id'=>2, 'status'=>'pending', 'attempts'=>0, 'available_at'=>$now, 'lease_owner'=>null, 'lease_expires_at'=>null, 'payload'=>['taskId'=>9, 'operationToken'=>str_repeat('a', 64)]];
$concurrentPushes = 0;
$dispatcherB = new AiOutboxDispatcher($claim, static function () use (&$concurrentPushes): void { $concurrentPushes++; }, $transition, 'dispatcher-b', static fn (): int => $now);
$dispatcherA = new AiOutboxDispatcher(
    $claim,
    static function () use (&$concurrentPushes, $dispatcherB): void { $concurrentPushes++; $dispatcherB->dispatch(); },
    $transition,
    'dispatcher-a',
    static function () use (&$now): int { return $now; }
);
aiReliabilityExpect($dispatcherA->dispatch() === 1 && $concurrentPushes === 1, '双 dispatcher 并发时只有原子 claim 成功者允许 push');

$rows[3] = ['id'=>3, 'status'=>'processing', 'attempts'=>0, 'available_at'=>$now, 'lease_owner'=>'dead-dispatcher', 'lease_expires_at'=>$now - 1, 'payload'=>['taskId'=>9, 'operationToken'=>str_repeat('a', 64)]];
aiReliabilityExpect($dispatcherB->dispatch() === 1 && $rows[3]['status'] === 'dispatched', '过期 processing 必须可由新 dispatcher 接管');

$rows[4] = ['id'=>4, 'status'=>'pending', 'attempts'=>0, 'available_at'=>$now, 'lease_owner'=>null, 'lease_expires_at'=>null, 'payload'=>['taskId'=>9, 'operationToken'=>str_repeat('a', 64)]];
$dispatcherAfterExpiry = new AiOutboxDispatcher($claim, static function (): void {}, $transition, 'dispatcher-b', static fn (): int => $now + 31);
$staleDispatcher = new AiOutboxDispatcher(
    $claim,
    static function () use ($dispatcherAfterExpiry): void { $dispatcherAfterExpiry->dispatch(); },
    $transition,
    'dispatcher-a',
    static function () use (&$now): int { return $now; }
);
aiReliabilityExpect($staleDispatcher->dispatch() === 0 && $rows[4]['status'] === 'dispatched' && $rows[4]['lease_owner'] === null, '旧 lease owner 不得覆盖接管者的 dispatched 结果');

$root = dirname(__DIR__);
$migration = $root . '/database/migrations/106_ai_outbox_dispatch_leases.sql';
aiReliabilityExpect(is_file($migration), '必须使用最大编号加一新增 106 outbox lease migration');
$migrationSql = (string)file_get_contents($migration);
foreach (['processing', 'lease_owner', 'lease_expires_at', 'idx_ai_outbox_claim'] as $contract) {
    aiReliabilityExpect(str_contains($migrationSql, $contract), "106 migration 缺少 {$contract}");
}
aiReliabilityExpect(!preg_match('/\b(?:DROP|TRUNCATE|RENAME|DELETE)\b/i', preg_replace('/^\s*--.*$/m', '', $migrationSql) ?? $migrationSql), '106 migration 必须 forward-only');
$jobSource = (string)file_get_contents($root . '/app/console/ai/job/AiAgentJob.php');
aiReliabilityExpect(str_contains($jobSource, 'compareAndSetTaskOperation'), 'Job 必须以 operation_token 原子 claim，拒绝崩溃窗口产生的重复消息');

echo "AI phase 3 reliability tests: PASS\n";
