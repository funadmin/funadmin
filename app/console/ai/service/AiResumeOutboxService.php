<?php

declare(strict_types=1);

namespace app\console\ai\service;

use Closure;
use RuntimeException;

/** 在同一事务内固化审批决定、恢复状态与可靠投递意图。 */
final class AiResumeOutboxService
{
    private readonly Closure $transaction;
    private readonly Closure $decide;
    private readonly Closure $casTask;
    private readonly Closure $appendOutbox;

    public function __construct(callable $transaction, callable $decide, callable $casTask, callable $appendOutbox)
    {
        $this->transaction = Closure::fromCallable($transaction);
        $this->decide = Closure::fromCallable($decide);
        $this->casTask = Closure::fromCallable($casTask);
        $this->appendOutbox = Closure::fromCallable($appendOutbox);
    }

    public function approveAndSchedule(int $approvalId, int $adminId, array $decision, array $task): array
    {
        return ($this->transaction)(function () use ($approvalId, $adminId, $decision, $task): array {
            $approval = ($this->decide)($approvalId, $adminId, $decision);
            if (($approval['status'] ?? '') !== 'approved') return $approval;
            if (!(($this->casTask)((int)$task['id'], 'paused', ['status'=>'resume_pending']))) {
                throw new RuntimeException('AI task 恢复状态 CAS 冲突', 409);
            }
            ($this->appendOutbox)([
                'aggregate_type'=>'ai_task', 'aggregate_id'=>(int)$task['id'], 'event_type'=>'ai.task.resume',
                'idempotency_key'=>'approval-'.$approvalId.'-resume',
                'payload'=>['taskId'=>(int)$task['id'], 'operationToken'=>(string)$task['operation_token']],
                'status'=>'pending', 'attempts'=>0, 'available_at'=>date('Y-m-d H:i:s'), 'created_at'=>date('Y-m-d H:i:s'),
            ]);
            return $approval;
        });
    }
}
