<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\console\ai\exception\AiChangeSetInterruptionException;
use app\console\ai\model\AiChangeSet;
use app\console\ai\repository\DatabaseAiConversationStore;
use Closure;
use RuntimeException;
use Throwable;

/** 以数据库 CAS 编排 ChangeSet 应用结果，并保证审计绑定资源归属。 */
final class AiChangeSetApplicationService
{
    public function __construct(
        private readonly Closure $reader,
        private readonly Closure $compareAndSet,
        private readonly Closure $audit
    ) {
    }

    public static function production(): self
    {
        $store = new DatabaseAiConversationStore();
        return new self(
            static fn (int $id): array => AiChangeSet::find($id)?->toArray() ?? [],
            static function (int $id, array $from, array $data, int $adminId, array $conditions = []): bool {
                $query = AiChangeSet::where('id', $id)->where('created_by', $adminId)->whereIn('status', $from);
                foreach ($conditions as $field => $value) $query->where($field, $value);
                return $query->update($data) === 1;
            },
            static fn (string $event, array $payload) => $store->appendEvent((int) $payload['task_id'], $event, $payload)
        );
    }

    public function recordPreview(int $changeSetId, int $adminId, array $selection, string $planDigest): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $planDigest) !== 1) {
            throw new RuntimeException('ChangeSet plan digest 不合法');
        }
        $record = $this->ownedRecord($changeSetId, $adminId);
        if (($this->compareAndSet)($changeSetId, [(string) ($record['status'] ?? '')], [
            'selection' => array_values($selection),
            'plan_digest' => $planDigest,
            'updated_at' => date('Y-m-d H:i:s'),
        ], $adminId) !== true) {
            throw new RuntimeException('ChangeSet preview CAS 冲突', 409);
        }
        ($this->audit)('ai.change_set.previewed', $this->auditPayload($record, $changeSetId, $adminId, ['plan_digest' => $planDigest]));
    }

    public function run(int $changeSetId, int $adminId, array $expectedStatuses, callable $operation, array $metadata = []): array
    {
        $record = $this->ownedRecord($changeSetId, $adminId);
        $applying = array_intersect_key($metadata, array_flip(['final_approval_id']));
        $applying['status'] = 'applying';
        $applying['applied_by'] = $adminId;
        $applying['updated_at'] = date('Y-m-d H:i:s');
        if (($this->compareAndSet)($changeSetId, $expectedStatuses, $applying, $adminId) !== true) {
            throw new RuntimeException('ChangeSet 状态已被其他执行者获取', 409);
        }
        ($this->audit)('ai.change_set.applying', $this->auditPayload($record, $changeSetId, $adminId));
        try {
            $result = $operation();
            $target = $this->outcomeStatus((string) ($result['state'] ?? ''));
            $this->persistOutcome($changeSetId, ['applying'], $target, $result, $adminId);
            ($this->audit)('ai.change_set.' . $target, $this->auditPayload($record, $changeSetId, $adminId, $result));
            return $result;
        } catch (Throwable $exception) {
            $target = $exception instanceof AiChangeSetInterruptionException
                ? 'recovery_required'
                : ($exception->getCode() === 409 ? 'conflict' : 'failed');
            $this->persistOutcome($changeSetId, ['applying'], $target, ['error' => $exception->getMessage()], $adminId);
            ($this->audit)('ai.change_set.' . $target, $this->auditPayload($record, $changeSetId, $adminId, ['error' => $exception->getMessage()]));
            throw $exception;
        }
    }

    public function recover(int $changeSetId, int $adminId, int $expectedVersion, callable $operation): array
    {
        $record = $this->ownedRecord($changeSetId, $adminId);
        if ((int) ($record['recovery_version'] ?? 0) !== $expectedVersion) {
            throw new RuntimeException('ChangeSet recovery CAS 冲突', 409);
        }
        $nextVersion = $expectedVersion + 1;
        if (($this->compareAndSet)($changeSetId, ['recovery_required'], [
            'status' => 'applying',
            'recovery_status' => 'recovering',
            'recovery_version' => $nextVersion,
            'updated_at' => date('Y-m-d H:i:s'),
        ], $adminId, ['recovery_version' => $expectedVersion]) !== true) {
            throw new RuntimeException('ChangeSet recovery CAS 冲突', 409);
        }
        $result = $operation();
        $status = $this->outcomeStatus((string) ($result['state'] ?? ''));
        $recoveryStatus = in_array($status, ['applied', 'failed'], true) ? 'recovered' : 'recovery_required';
        $this->persistOutcome($changeSetId, ['applying'], $status, $result, $adminId, [
            'recovery_status' => $recoveryStatus,
        ], ['recovery_version' => $nextVersion]);
        ($this->audit)('ai.change_set.recovered', $this->auditPayload($record, $changeSetId, $adminId, $result));
        return $result;
    }

    private function ownedRecord(int $changeSetId, int $adminId): array
    {
        $record = ($this->reader)($changeSetId);
        if (!is_array($record) || (int) ($record['created_by'] ?? 0) !== $adminId) {
            throw new RuntimeException('资源不存在', 404);
        }
        return $record;
    }

    private function outcomeStatus(string $state): string
    {
        return match ($state) {
            'completed' => 'applied',
            'recovery_required' => 'recovery_required',
            'rolled_back' => 'failed',
            default => 'failed',
        };
    }

    private function persistOutcome(int $id, array $from, string $status, array $result, int $adminId, array $extra = [], array $conditions = []): void
    {
        $data = array_merge($extra, ['status' => $status, 'applied_by' => $adminId, 'updated_at' => date('Y-m-d H:i:s')]);
        $transactionId = $result['transactionId'] ?? $result['transaction_id'] ?? null;
        if ($transactionId !== null) $data['transaction_id'] = (string) $transactionId;
        if ($status === 'applied') $data['applied_at'] = date('Y-m-d H:i:s');
        if (($this->compareAndSet)($id, $from, $data, $adminId, $conditions) !== true) {
            throw new RuntimeException('ChangeSet 状态 CAS 冲突', 409);
        }
    }

    private function auditPayload(array $record, int $changeSetId, int $adminId, array $extra = []): array
    {
        return array_merge([
            'admin_id' => $adminId,
            'conversation_id' => (int) ($record['conversation_id'] ?? 0),
            'task_id' => (int) ($record['task_id'] ?? 0),
            'change_set_id' => $changeSetId,
        ], $extra);
    }
}
