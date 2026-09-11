<?php

declare(strict_types=1);

namespace app\console\service;

use Closure;
use RuntimeException;

/** nonce、digest、模式快照与 CAS 共同约束的一次性审批服务。 */
final class AiApprovalService
{
    private readonly Closure $clock;

    public function __construct(private readonly AiSecurityStore $store, ?callable $clock = null)
    {
        $this->clock = $clock === null ? time(...) : Closure::fromCallable($clock);
    }

    public function pending(int $adminId): array
    {
        return $this->store->pendingApprovals($adminId);
    }

    public function request(array $request, int $ttlSeconds = 900): array
    {
        $nonce = bin2hex(random_bytes(32));
        $normalized = ['conversation_id'=>(int)$request['conversation_id'],'task_id'=>(int)$request['task_id'],'tool_call_id'=>(int)$request['tool_call_id'],'operation'=>(string)$request['operation'],'mode_snapshot'=>(string)$request['mode_snapshot'],'arguments'=>$request['arguments'] ?? []];
        $digest = hash('sha256', json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . ':' . $nonce);
        $now = ($this->clock)();
        return $this->store->createApproval(array_merge($normalized, [
            'requested_by'=>(int)$request['requested_by'], 'scope'=>'once', 'risk_reason'=>(string)($request['risk_reason'] ?? ''),
            'impact'=>$request['impact'] ?? [], 'cas_version'=>0, 'nonce'=>$nonce, 'digest'=>$digest, 'status'=>'pending',
            'request'=>$normalized, 'expires_at'=>date('Y-m-d H:i:s', $now + max(1, $ttlSeconds)), 'created_at'=>date('Y-m-d H:i:s', $now),
        ]));
    }

    public function decide(int $id, int $adminId, string $action, string $scope, string $nonce, string $digest, int $version, string $currentMode): array
    {
        if (!in_array($action, ['approve', 'reject'], true) || !in_array($scope, ['once', 'session_operation'], true)) throw new RuntimeException('审批决定参数无效', 400);
        $approval = $this->store->approval($id, $adminId);
        if (!$approval || $approval['status'] !== 'pending' || !hash_equals($approval['nonce'], $nonce) || !hash_equals($approval['digest'], $digest)) throw new RuntimeException('审批不存在、已消费或凭据无效', 409);
        if ($approval['mode_snapshot'] !== $currentMode) throw new RuntimeException('审批模式已改变，旧审批不可放行', 409);
        $now = ($this->clock)();
        if (strtotime((string)$approval['expires_at']) <= $now) {
            $this->store->casApproval($id, $version, 'pending', ['status'=>'expired','decision'=>['reason'=>'expired'],'decided_at'=>date('Y-m-d H:i:s',$now)]);
            throw new RuntimeException('审批已过期', 409);
        }
        $status = $action === 'approve' ? 'approved' : 'denied';
        if (!$this->store->casApproval($id, $version, 'pending', ['status'=>$status,'scope'=>$scope,'decided_by'=>$adminId,'decision'=>['action'=>$action],'decided_at'=>date('Y-m-d H:i:s',$now)])) throw new RuntimeException('审批 CAS 冲突', 409);
        return array_replace($approval, ['status'=>$status,'scope'=>$scope,'cas_version'=>$version + 1]);
    }

    public function hasSessionApproval(int $conversationId, string $operation, string $mode): bool
    {
        return $this->store->approvedSessionOperation($conversationId, $operation, $mode) !== null;
    }
}
