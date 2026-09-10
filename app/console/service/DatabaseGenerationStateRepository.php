<?php

declare(strict_types=1);

namespace app\console\service;

use app\console\model\BusinessModule;
use app\console\model\CrudGeneration;
use app\console\model\GeneratedFileBaseline;
use RuntimeException;
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

    /**
     * 锁定模块与生成记录，仅允许提交属于同一模块的 generation。
     */
    public function commitGeneration(
        int $moduleId,
        int $generationId,
        array $records,
        string $transactionId,
        string $planDigest
    ): void {
        $module = BusinessModule::where('id', $moduleId)->lock(true)->find();
        if (!$module) {
            throw new RuntimeException('业务模块不存在');
        }
        $generation = CrudGeneration::where('id', $generationId)->lock(true)->find();
        if (!$generation || (int) $generation->business_module_id !== $moduleId) {
            throw new RuntimeException('生成记录与业务模块不匹配');
        }

        $this->replaceBaselines($moduleId, $generationId, $records);
        $generation->save([
            'status' => 'completed',
            'transaction_id' => $transactionId,
            'plan_digest' => $planDigest,
            'recovery_status' => 'none',
        ]);
        $module->save([
            'current_generation_id' => $generationId,
            'last_success_generation_id' => $generationId,
            'generation_status' => 'generated',
        ]);
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
