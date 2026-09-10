<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\BusinessModule;
use app\console\model\CrudGeneration;
use app\console\model\Form;
use app\console\model\GeneratedFileBaseline;
use RuntimeException;
use Throwable;
use think\facade\Db;

/**
 * 使用业务开发中心模型持久化生成基线和提交状态。
 */
final class DatabaseGenerationStateRepository
{
    /** @return list<array<string, mixed>> */
    public function loadBaselines(int $moduleId): array
    {
        if ($moduleId < 1) {
            throw new RuntimeException('业务模块 ID 无效');
        }
        return GeneratedFileBaseline::where('business_module_id', $moduleId)
            ->where('status', 'active')
            ->field('business_module_id,relative_path,artifact_type,base_hash,base_storage_path,target_hash,template_version,definition_hash,generation_id,content_kind,status')
            ->order('relative_path', 'asc')
            ->select()
            ->toArray();
    }

    public function transaction(callable $operation): mixed
    {
        return Db::transaction($operation);
    }

    public function adoptResolvedBaseline(int $moduleId, int $generationId, array $record): array
    {
        return Db::transaction(function () use ($moduleId, $generationId, $record): array {
            $module = BusinessModule::where('id', $moduleId)->lock(true)->find();
            $generation = CrudGeneration::where('id', $generationId)->where('business_module_id', $moduleId)->lock(true)->find();
            if (!$module || !$generation) throw new RuntimeException('业务模块或生成记录不存在');
            if ((string) $generation->status !== 'conflict') throw new RuntimeException('仅冲突生成记录可采纳 baseline');
            $allowed = array_intersect_key($record, array_flip([
                'relative_path', 'artifact_type', 'base_hash', 'base_storage_path', 'target_hash',
                'template_version', 'definition_hash', 'content_kind',
            ]));
            $path = (string) ($allowed['relative_path'] ?? '');
            $hashes = [(string) ($allowed['base_hash'] ?? ''), (string) ($allowed['target_hash'] ?? ''), (string) ($allowed['definition_hash'] ?? '')];
            if ($path === '' || trim((string) ($allowed['artifact_type'] ?? '')) === ''
                || trim((string) ($allowed['base_storage_path'] ?? '')) === ''
                || preg_match('~(^|/)\.\.?(/|$)~', $path) === 1 || str_starts_with($path, '/') || str_contains($path, "\0")
                || preg_match('/^[a-f0-9]{64}$/', $hashes[0]) !== 1
                || preg_match('/^[a-f0-9]{64}$/', $hashes[1]) !== 1
                || preg_match('/^[a-f0-9]{64}$/', $hashes[2]) !== 1) {
                throw new RuntimeException('采纳 baseline 记录无效');
            }
            $baseline = GeneratedFileBaseline::withTrashed()->where('business_module_id', $moduleId)->where('relative_path', $path)->lock(true)->find();
            if (!$baseline) $baseline = new GeneratedFileBaseline();
            if ($baseline->id && $baseline->trashed()) $baseline->restore();
            $baseline->save(array_replace($allowed, [
                'business_module_id' => $moduleId,
                'generation_id' => $generationId,
                'status' => 'active',
                'updated_at' => date('Y-m-d H:i:s'),
                'created_at' => $baseline->id ? $baseline->created_at : date('Y-m-d H:i:s'),
            ]));
            return $baseline->toArray();
        });
    }

    public function isBaselineBlobReferenced(string $relativePath): bool
    {
        return GeneratedFileBaseline::withTrashed()
            ->where('base_storage_path', $relativePath)
            ->find() !== null;
    }

    /**
     * 以数据库中的完整提交标识判定 generation 是否已原子提交。
     */
    public function isGenerationCommitted(
        int $moduleId,
        int $generationId,
        string $transactionId,
        string $planDigest
    ): bool {
        return CrudGeneration::where('id', $generationId)
            ->where('business_module_id', $moduleId)
            ->where('transaction_id', $transactionId)
            ->where('plan_digest', $planDigest)
            ->where('status', 'completed')
            ->where('recovery_status', 'none')
            ->lock(true)
            ->find() !== null;
    }

    /** @return array<string, mixed> */
    public function createOrReuseGeneration(array $row, string $actor): array
    {
        $operationKey = (string) ($row['operation_key'] ?? '');
        if (preg_match('/^managed:[a-f0-9]{64}$/', $operationKey) !== 1) {
            throw new RuntimeException('generation operation_key 无效');
        }
        try {
            return Db::transaction(function () use ($row, $actor, $operationKey): array {
                $moduleId = (int) ($row['business_module_id'] ?? 0);
                if (!BusinessModule::where('id', $moduleId)->lock(true)->find()) {
                    throw new RuntimeException('业务模块不存在');
                }
                $existing = CrudGeneration::where('operation_key', $operationKey)->find();
                if ($existing) {
                    return $existing->toArray();
                }
                $generation = CrudGeneration::create($row);
                if ((string) ($row['status'] ?? '') === 'planned') {
                    CrudGeneration::where('business_module_id', $moduleId)
                        ->where('status', 'planned')
                        ->where('id', '<>', (int) $generation->id)
                        ->update([
                            'status' => 'superseded',
                            'superseded_by_id' => (int) $generation->id,
                            'actor' => $actor,
                            'completed_at' => date('Y-m-d H:i:s'),
                        ]);
                }
                return $generation->toArray();
            });
        } catch (Throwable $exception) {
            $existing = CrudGeneration::where('operation_key', $operationKey)->find();
            if ($existing) {
                return $existing->toArray();
            }
            throw $exception;
        }
    }

    public function claimGeneration(int $moduleId, int $generationId, string $actor): bool
    {
        return CrudGeneration::where('id', $generationId)
            ->where('business_module_id', $moduleId)
            ->where('status', 'planned')
            ->update([
                'status' => 'running',
                'recovery_status' => 'none',
                'actor' => $actor,
                'started_at' => date('Y-m-d H:i:s'),
                'failed_at' => null,
            ]) === 1;
    }

    public function releaseGenerationClaim(int $moduleId, int $generationId, string $actor): void
    {
        CrudGeneration::where('id', $generationId)
            ->where('business_module_id', $moduleId)
            ->where('status', 'running')
            ->update([
                'status' => 'planned',
                'actor' => $actor,
                'started_at' => null,
            ]);
    }

    public function bindGenerationTransaction(int $moduleId, int $generationId, string $transactionId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $transactionId) !== 1) throw new RuntimeException('transaction ID 无效');
        $updated = CrudGeneration::where('id', $generationId)
            ->where('business_module_id', $moduleId)
            ->where('status', 'running')
            ->where(function ($query) use ($transactionId): void {
                $query->whereNull('transaction_id')->whereOr('transaction_id', $transactionId);
            })
            ->update(['transaction_id' => $transactionId]);
        if ($updated !== 1) throw new BusinessOperationException('GENERATION_BINDING_CONFLICT');
    }

    public function generationForRecovery(int $generationId): ?array
    {
        $generation = CrudGeneration::where('id', $generationId)->find();
        return $generation ? $generation->toArray() : null;
    }

    public function claimRecovery(int $generationId, string $expectedRecoveryStatus, string $actor): bool
    {
        return CrudGeneration::where('id', $generationId)
            ->where('status', 'failed')
            ->where('recovery_status', $expectedRecoveryStatus)
            ->whereNotNull('transaction_id')
            ->update([
                'status' => 'running',
                'recovery_status' => 'recovering',
                'actor' => $actor,
                'started_at' => date('Y-m-d H:i:s'),
            ]) === 1;
    }

    public function markRunning(int $generationId, string $actor): void
    {
        $this->updateGeneration($generationId, [
            'status' => 'running',
            'recovery_status' => 'none',
            'actor' => $actor,
            'started_at' => date('Y-m-d H:i:s'),
            'failed_at' => null,
        ]);
    }

    public function markFailed(int $generationId, string $failureCode, array $error, string $actor): void
    {
        $this->updateGeneration($generationId, [
            'status' => 'failed',
            'failure_code' => $failureCode,
            'error' => $error,
            'actor' => $actor,
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markConflict(int $generationId, string $failureCode, array $error, string $actor): void
    {
        $this->updateGeneration($generationId, [
            'status' => 'conflict',
            'failure_code' => $failureCode,
            'error' => $error,
            'actor' => $actor,
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markSuperseded(int $generationId, int $supersededById, string $actor): void
    {
        if ($generationId === $supersededById || CrudGeneration::where('id', $supersededById)->find() === null) {
            throw new RuntimeException('替代 generation 无效');
        }
        $this->updateGeneration($generationId, [
            'status' => 'superseded',
            'superseded_by_id' => $supersededById,
            'actor' => $actor,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markRecovering(int $generationId, string $actor): void
    {
        $this->updateGeneration($generationId, [
            'status' => 'running',
            'recovery_status' => 'recovering',
            'actor' => $actor,
        ]);
    }

    public function markRecoveredCompleted(int $generationId, string $actor): void
    {
        $now = date('Y-m-d H:i:s');
        $updated = CrudGeneration::where('id', $generationId)
            ->where('status', 'running')
            ->where('recovery_status', 'recovering')
            ->update([
                'status' => 'completed',
                'recovery_status' => 'recovered_completed',
                'actor' => $actor,
                'failure_code' => null,
                'error' => null,
                'completed_at' => $now,
                'failed_at' => null,
                'recovered_at' => $now,
            ]);
        if ($updated !== 1) throw new BusinessOperationException('GENERATION_RECOVERY_STATUS_CONFLICT');
    }

    public function markRolledBack(int $generationId, string $actor): void
    {
        $now = date('Y-m-d H:i:s');
        $this->updateGeneration($generationId, [
            'status' => 'failed',
            'recovery_status' => 'rolled_back',
            'actor' => $actor,
            'failed_at' => $now,
            'recovered_at' => $now,
        ]);
    }

    public function markRecoveryRequired(int $generationId, string $failureCode, array $error, string $actor): void
    {
        $this->updateGeneration($generationId, [
            'status' => 'failed',
            'recovery_status' => 'recovery_required',
            'failure_code' => $failureCode,
            'error' => $error,
            'actor' => $actor,
            'failed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function completedResult(int $generationId): ?array
    {
        $generation = CrudGeneration::where('id', $generationId)
            ->where('status', 'completed')
            ->find();
        return $generation && is_array($generation->result) ? $generation->result : null;
    }

    /**
     * 在同一数据库事务中提交 baseline、可信结果及模块/表单发布状态。
     */
    public function commitGeneration(
        int $moduleId,
        int $generationId,
        array $records,
        string $transactionId,
        string $planDigest
    ): void {
        Db::transaction(function () use ($moduleId, $generationId, $records, $transactionId, $planDigest): void {
            $module = BusinessModule::where('id', $moduleId)->lock(true)->find();
            if (!$module) {
                throw new RuntimeException('业务模块不存在');
            }
            $generation = CrudGeneration::where('id', $generationId)->lock(true)->find();
            if (!$generation || (int) $generation->business_module_id !== $moduleId) {
                throw new RuntimeException('生成记录与业务模块不匹配');
            }
            $definition = is_array($generation->definition) ? $generation->definition : [];
            $routePath = (string) ($definition['routePath'] ?? '');
            if (preg_match('#^/[a-z][a-z0-9-]*(?:/[a-z][a-z0-9-]*)*$#', $routePath) !== 1) {
                throw new RuntimeException('生成记录缺少可信 routePath');
            }
            $now = date('Y-m-d H:i:s');
            $isRecovery = (string) $generation->recovery_status === 'recovering';
            $result = [
                'generationId' => $generationId,
                'state' => 'completed',
                'resourceApplyStatus' => 'applied',
                'resourceApplyError' => null,
                'routePath' => $routePath,
                'definitionHash' => (string) $generation->definition_hash,
                'schemaHash' => (string) ($definition['formSchemaHash'] ?? ''),
                'transactionId' => $transactionId,
                'planDigest' => $planDigest,
            ];

            $this->replaceBaselines($moduleId, $generationId, $records);
            $generation->save([
                'status' => 'completed',
                'transaction_id' => $transactionId,
                'plan_digest' => $planDigest,
                'recovery_status' => $isRecovery ? 'recovered_completed' : 'none',
                'result' => $result,
                'failure_code' => null,
                'error' => null,
                'completed_at' => $now,
                'failed_at' => null,
                'recovered_at' => $isRecovery ? $now : null,
            ]);
            $module->save([
                'lifecycle_status' => 'published',
                'published_schema_hash' => $result['schemaHash'] !== '' ? $result['schemaHash'] : null,
                'published_schema_version' => isset($definition['formSchemaVersion']) ? (int) $definition['formSchemaVersion'] : null,
                'module_route' => $routePath,
                'current_generation_id' => $generationId,
                'last_success_generation_id' => $generationId,
                'generation_status' => 'completed',
            ]);
            if ((int) $generation->form_id > 0) {
                $form = Form::where('id', (int) $generation->form_id)->lock(true)->find();
                if (!$form || ((int) $module->form_id > 0 && (int) $module->form_id !== (int) $form->id)) {
                    throw new RuntimeException('生成记录与表单不匹配');
                }
                $form->save([
                    'publish_mode' => 'generated',
                    'publish_status' => 'published',
                    'published_at' => $now,
                    'crud_generation_id' => $generationId,
                    'published_definition_hash' => (string) $generation->definition_hash,
                    'published_schema_hash' => $result['schemaHash'] !== '' ? $result['schemaHash'] : null,
                ]);
            }
        });
    }

    private function updateGeneration(int $generationId, array $attributes): void
    {
        $generation = CrudGeneration::where('id', $generationId)->find();
        if (!$generation) {
            throw new RuntimeException('生成记录不存在');
        }
        $generation->save($attributes);
    }

    private function replaceBaselines(int $moduleId, int $generationId, array $records): void
    {
        $byPath = [];
        foreach ($records as $record) {
            $path = (string) ($record['relative_path'] ?? '');
            if ($path === '' || (int) ($record['business_module_id'] ?? 0) !== $moduleId
                || (int) ($record['generation_id'] ?? 0) !== $generationId
                || trim((string) ($record['artifact_type'] ?? '')) === '') {
                throw new RuntimeException('生成 baseline 记录无效或跨模块');
            }
            if (isset($byPath[$path])) {
                throw new RuntimeException('生成 baseline 路径重复：' . $path);
            }
            $byPath[$path] = $record;
        }

        $existing = GeneratedFileBaseline::withTrashed()
            ->where('business_module_id', $moduleId)
            ->lock(true)
            ->select();
        foreach ($existing as $baseline) {
            $path = (string) $baseline->relative_path;
            if (!isset($byPath[$path])) {
                if (!$baseline->trashed()) {
                    $baseline->delete();
                }
                continue;
            }
            $record = $byPath[$path];
            unset($byPath[$path], $record['id'], $record['created_at'], $record['updated_at'], $record['deleted_at']);
            if ($baseline->trashed()) {
                $baseline->restore();
            }
            $baseline->save(array_replace($record, [
                'business_module_id' => $moduleId,
                'generation_id' => $generationId,
                'status' => 'active',
            ]));
        }
        foreach ($byPath as $record) {
            unset($record['id'], $record['created_at'], $record['updated_at'], $record['deleted_at']);
            GeneratedFileBaseline::create(array_replace($record, [
                'business_module_id' => $moduleId,
                'generation_id' => $generationId,
                'status' => 'active',
            ]));
        }
    }
}
