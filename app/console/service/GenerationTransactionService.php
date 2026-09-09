<?php

declare(strict_types=1);

namespace app\console\service;

use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\PathGuard;
use app\common\crud\SafeCommit;
use Closure;
use RuntimeException;
use Throwable;

/**
 * 以私有 WAL 协调 managed 文件、结构化资源、baseline blob 与数据库状态。
 */
final class GenerationTransactionService
{
    private const STATES = [
        'prepared', 'staged', 'writing', 'files_written', 'resources_applying', 'resources_applied',
        'baselines_committing', 'completed', 'rolling_back', 'rolled_back', 'recovery_required',
    ];

    /** @var Closure(): array<string, mixed> */
    private readonly Closure $currentState;

    /** @var null|Closure(string, int, string): void */
    private readonly ?Closure $faultHook;

    public function __construct(
        private readonly string $projectRoot,
        private readonly ConfirmationToken $tokens,
        private readonly GeneratedFileBaselineRepository $baselines,
        private readonly mixed $stateRepository,
        private readonly mixed $resources,
        callable $currentState,
        ?callable $faultHook = null
    ) {
        $this->currentState = Closure::fromCallable($currentState);
        $this->faultHook = $faultHook === null ? null : Closure::fromCallable($faultHook);
    }

    /**
     * 构造可直接用于生产恢复的完整服务；执行路径可注入实时状态读取器。
     */
    public static function production(?string $projectRoot = null, ?callable $currentState = null): self
    {
        $root = rtrim($projectRoot ?? self::defaultProjectRoot(), DIRECTORY_SEPARATOR);
        $state = new DatabaseGenerationStateRepository();
        return new self(
            $root,
            new ConfirmationToken($root),
            new GeneratedFileBaselineRepository($root, $state),
            $state,
            new GenerationResourceTransaction(),
            $currentState ?? static function (): array {
                throw new RuntimeException('production execute 必须注入实时 generation current state');
            }
        );
    }

    public static function bundleDigest(array $bundle): string
    {
        $copy = $bundle;
        unset($copy['confirmToken'], $copy['token']);
        return hash('sha256', CrudDefinition::canonicalJson($copy));
    }

    public function execute(int $moduleId, int $generationId, array $bundle, string $confirmToken): array
    {
        $this->assertTrustedBundle($bundle);
        if (($bundle['plan']['blocked'] ?? true) === true) {
            throw new RuntimeException('冲突 managed plan 拒绝执行');
        }
        return $this->locked(function () use ($moduleId, $generationId, $bundle, $confirmToken): array {
            $digest = self::bundleDigest($bundle);
            $claims = $this->tokens->verify($confirmToken, $digest);
            $this->assertCurrentState($bundle);
            $this->assertTargetsCurrent($bundle['plan']['files']);
            $this->tokens->consume((string) $claims['nonce'], (int) $claims['expiresAt']);

            $transactionId = bin2hex(random_bytes(16));
            $journal = $this->newJournal($transactionId, $moduleId, $generationId, $bundle, $digest);
            $this->writeJournal($journal);
            $resourcesBegun = false;
            try {
                $journal = $this->stage($journal, $bundle);
                $journal = $this->writeFiles($journal);
                $this->fault('after_files_written', -1, '');

                $journal = $this->checkpoint($journal, 'resources_applying');
                $this->resourceCall('begin');
                $resourcesBegun = true;
                $this->fault('before_resources', -1, '');
                $this->resourceCall('apply', [(array) $bundle['resources']]);
                $this->fault('after_resources', -1, '');
                $journal = $this->checkpoint($journal, 'resources_applied');

                [$journal, $records] = $this->prepareBaselines($journal, $bundle, $moduleId, $generationId);
                $journal = $this->checkpoint($journal, 'baselines_committing');
                $this->fault('before_blob_commit', -1, '');
                $this->baselines->commit((array) $journal['prepared_blobs']);
                $this->fault('after_blob_commit', -1, '');
                $this->stateRepository->transaction(function () use ($moduleId, $generationId, $records, $transactionId, $bundle): void {
                    $this->stateRepository->commitGeneration(
                        $moduleId,
                        $generationId,
                        $records,
                        $transactionId,
                        (string) $bundle['plan']['planDigest']
                    );
                    $this->fault('before_db_commit', -1, '');
                });
                $this->resourceCall('commit');
                $resourcesBegun = false;
                $this->fault('after_resource_commit', -1, '');
                $journal = $this->checkpoint($journal, 'completed');
                $this->cleanupTransaction($journal);
                return ['state' => 'completed', 'transactionId' => $transactionId, 'written' => count($journal['applied'])];
            } catch (GenerationInterruptionException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $journal = $this->inspect($transactionId);
                if (!$resourcesBegun && $this->isGenerationCommitted($journal)) {
                    throw $exception;
                }
                $errors = $this->rollbackJournal($journal, $resourcesBegun);
                if ($errors !== []) {
                    throw new RuntimeException($exception->getMessage() . '；回滚失败：' . implode('；', $errors), 0, $exception);
                }
                throw $exception;
            }
        });
    }

    public function inspect(string $transactionId): array
    {
        $this->assertTransactionId($transactionId);
        $file = $this->walFile($transactionId);
        if (!is_file($file) || is_link($file)) {
            throw new RuntimeException('generation WAL 不存在');
        }
        try {
            $journal = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('generation WAL 损坏', 0, $exception);
        }
        if (!is_array($journal) || ($journal['transaction_id'] ?? '') !== $transactionId
            || !in_array($journal['state'] ?? '', self::STATES, true)) {
            throw new RuntimeException('generation WAL 内容无效');
        }
        return $journal;
    }

    /** @return list<array<string, mixed>> */
    public function stale(): array
    {
        $directory = $this->privateRoot() . DIRECTORY_SEPARATOR . 'wal';
        if (!is_dir($directory)) {
            return [];
        }
        $journals = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
            $id = pathinfo($file, PATHINFO_FILENAME);
            try {
                $journal = $this->inspect($id);
            } catch (Throwable) {
                continue;
            }
            if (!in_array($journal['state'], ['completed', 'rolled_back'], true)) {
                $journals[] = $journal;
            }
        }
        usort($journals, static fn (array $left, array $right): int => strcmp((string) $left['updated_at'], (string) $right['updated_at']));
        return $journals;
    }

    public function recover(string $transactionId): array
    {
        return $this->locked(function () use ($transactionId): array {
            $journal = $this->inspect($transactionId);
            if ($journal['state'] === 'recovery_required') {
                throw new RuntimeException('事务需要人工恢复，拒绝自动猜测');
            }
            if ($journal['state'] === 'completed') {
                $this->cleanupTransaction($journal);
                return $journal;
            }
            if ($journal['state'] === 'rolled_back') {
                return $journal;
            }
            if ($this->isGenerationCommitted($journal)) {
                return $this->finalizeCommitted($journal);
            }
            if (in_array($journal['state'], ['prepared', 'staged'], true)) {
                $journal = $this->checkpoint($journal, 'rolling_back');
                $this->cleanupTransaction($journal);
                return $this->checkpoint($journal, 'rolled_back');
            }
            $journal = $this->resolveInterruptedCommits($journal);
            if (!$this->canRollback($journal)) {
                $journal = $this->checkpoint($journal, 'recovery_required');
                throw new RuntimeException('无法证明文件状态，事务需要人工恢复');
            }
            $errors = $this->rollbackJournal($journal, false);
            if ($errors !== []) {
                $journal = $this->inspect($transactionId);
                $this->checkpoint($journal, 'recovery_required');
                throw new RuntimeException('自动恢复失败，事务需要人工恢复：' . implode('；', $errors));
            }
            return $this->inspect($transactionId);
        });
    }

    private function assertTrustedBundle(array $bundle): void
    {
        foreach (['plan', 'remoteContents', 'mergedTextContents', 'definitionHash', 'schemaHash', 'registryHash', 'templateVersion', 'migrationHash', 'resourcesHash', 'resources'] as $key) {
            if (!array_key_exists($key, $bundle)) {
                throw new RuntimeException('trusted generation bundle 缺少字段：' . $key);
            }
        }
        if (!is_array($bundle['plan']) || ($bundle['plan']['managed'] ?? false) !== true
            || !is_array($bundle['plan']['files'] ?? null) || !is_array($bundle['remoteContents'])
            || !is_array($bundle['mergedTextContents']) || !is_array($bundle['resources'])) {
            throw new RuntimeException('trusted generation bundle 结构无效');
        }
        foreach (['definitionHash', 'schemaHash', 'registryHash', 'migrationHash', 'resourcesHash'] as $hash) {
            $this->assertHash((string) $bundle[$hash], $hash);
        }
        if (!hash_equals((string) $bundle['definitionHash'], (string) ($bundle['plan']['definitionHash'] ?? ''))) {
            throw new RuntimeException('bundle Definition 与 managed plan 不一致');
        }
        $resourcesHash = hash('sha256', CrudDefinition::canonicalJson($bundle['resources']));
        if (!hash_equals((string) $bundle['resourcesHash'], $resourcesHash)) {
            throw new RuntimeException('bundle resources hash 不匹配');
        }
        foreach ($bundle['plan']['files'] as $file) {
            if (!is_array($file) || !is_string($file['path'] ?? null)) {
                throw new RuntimeException('managed plan 文件无效');
            }
            PathGuard::resolve($this->projectRoot, $file['path'], '项目目录');
            $status = (string) ($file['status'] ?? '');
            if (in_array($status, ['conflict', 'binary-conflict', 'conflict-no-base'], true)) {
                throw new RuntimeException('冲突 managed plan 拒绝执行');
            }
            if (!in_array($status, ['create', 'update', 'auto-merged', 'delete', 'keep-local'], true)) {
                throw new RuntimeException('managed plan 文件状态无效');
            }
            $remoteHash = $file['remoteHash'] ?? null;
            if ($remoteHash !== null) {
                $remote = $bundle['remoteContents'][$file['path']] ?? null;
                if (!is_string($remote) || !hash_equals((string) $remoteHash, hash('sha256', $remote))) {
                    throw new RuntimeException('trusted Remote 内容 hash 不匹配：' . $file['path']);
                }
            }
            if (in_array($status, ['create', 'update', 'auto-merged'], true)) {
                $content = ($file['contentKind'] ?? 'text') === 'binary'
                    ? ($bundle['remoteContents'][$file['path']] ?? null)
                    : ($bundle['mergedTextContents'][$file['path']] ?? null);
                if (!is_string($content) || !hash_equals((string) ($file['mergedHash'] ?? ''), hash('sha256', $content))) {
                    throw new RuntimeException('trusted Merged 内容 hash 不匹配：' . $file['path']);
                }
            }
        }
    }

    private function assertCurrentState(array $bundle): void
    {
        $actual = ($this->currentState)();
        foreach (['definitionHash', 'schemaHash', 'registryHash', 'templateVersion', 'migrationHash', 'resourcesHash'] as $key) {
            if (!is_string($actual[$key] ?? null) || !hash_equals((string) $bundle[$key], $actual[$key])) {
                throw new RuntimeException($key . ' 已漂移');
            }
        }
    }

    private function assertTargetsCurrent(array $files): void
    {
        foreach ($files as $file) {
            $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], '项目目录');
            $stat = @lstat($target);
            if ($stat !== false && (($stat['mode'] & 0170000) !== 0100000)) {
                throw new RuntimeException('目标路径被非普通文件或符号链接阻塞');
            }
            $actual = $stat === false ? null : hash_file('sha256', $target);
            if ($actual !== ($file['localHash'] ?? null)) {
                throw new RuntimeException('目标文件 hash 已变化：' . $file['path']);
            }
        }
    }

    private function newJournal(string $id, int $moduleId, int $generationId, array $bundle, string $digest): array
    {
        $files = [];
        foreach ($bundle['plan']['files'] as $file) {
            $artifactType = trim((string) ($file['artifactType'] ?? ''));
            if ($artifactType === '') {
                throw new RuntimeException('managed plan 文件缺少 artifactType：' . $file['path']);
            }
            $files[] = [
                'path' => $file['path'], 'status' => $file['status'], 'artifact_type' => $artifactType,
                'content_kind' => $file['contentKind'] ?? 'text',
                'local_hash' => $file['localHash'] ?? null, 'remote_hash' => $file['remoteHash'] ?? null,
                'target_hash' => $file['mergedHash'] ?? null, 'next_base_hash' => $file['nextBaseHash'] ?? null,
                'stage_path' => null, 'backup_path' => null, 'write_state' => 'planned',
            ];
        }
        return [
            'schema_version' => 1, 'transaction_id' => $id, 'module_id' => $moduleId,
            'generation_id' => $generationId, 'state' => 'prepared', 'history' => ['prepared'],
            'bundle_digest' => $digest, 'plan_digest' => $bundle['plan']['planDigest'],
            'files' => $files, 'applied' => [], 'prepared_blobs' => [], 'updated_at' => gmdate(DATE_ATOM),
        ];
    }

    private function stage(array $journal, array $bundle): array
    {
        $root = 'runtime/private/business-development/transactions/' . $journal['transaction_id'];
        foreach ($journal['files'] as $index => $file) {
            if (!in_array($file['status'], ['create', 'update', 'auto-merged'], true)) {
                continue;
            }
            $content = $file['content_kind'] === 'binary'
                ? $bundle['remoteContents'][$file['path']]
                : $bundle['mergedTextContents'][$file['path']];
            $relative = $root . '/staged/' . $file['path'];
            $stage = PathGuard::resolve($this->projectRoot, $relative, '项目目录');
            $this->secureDirectory(dirname($stage));
            if (file_put_contents($stage, $content, LOCK_EX) === false || !chmod($stage, 0600)
                || !hash_equals((string) $file['target_hash'], (string) hash_file('sha256', $stage))) {
                throw new RuntimeException('staging 内容 hash 验证失败：' . $file['path']);
            }
            $journal['files'][$index]['stage_path'] = $relative;
        }
        return $this->checkpoint($journal, 'staged');
    }

    private function writeFiles(array $journal): array
    {
        $journal = $this->checkpoint($journal, 'writing');
        $safeCommit = new SafeCommit($this->projectRoot);
        $root = 'runtime/private/business-development/transactions/' . $journal['transaction_id'];
        foreach ($journal['files'] as $index => $file) {
            if (in_array($file['status'], ['keep-local'], true)) {
                continue;
            }
            $target = PathGuard::resolve($this->projectRoot, $file['path'], '项目目录');
            $backupRelative = $root . '/backup/' . $file['path'];
            $backup = PathGuard::resolve($this->projectRoot, $backupRelative, '项目目录');
            $journal['files'][$index]['backup_path'] = $backupRelative;
            $journal['files'][$index]['write_state'] = 'committing';
            $this->writeJournal($journal);
            $this->ensureTargetDirectory(dirname($target));
            if ($file['status'] === 'delete') {
                $this->secureDirectory(dirname($backup));
                if (!is_file($target) || !rename($target, $backup)) {
                    throw new RuntimeException('删除目标移动到 backup 失败：' . $file['path']);
                }
            } else {
                $this->secureDirectory(dirname($backup));
                $result = $safeCommit->commit(
                    PathGuard::resolve($this->projectRoot, (string) $file['stage_path'], '项目目录'),
                    $target,
                    $backup,
                    $file['local_hash'],
                    (string) $file['target_hash']
                );
                if (($result['ok'] ?? false) !== true) {
                    throw new RuntimeException((string) ($result['message'] ?? '安全文件提交失败'));
                }
            }
            $this->fault('after_safe_commit', $index, $file['path']);
            $journal['files'][$index]['write_state'] = 'written';
            $journal['applied'][] = $index;
            $this->writeJournal($journal);
            $this->fault('after_file_rename', $index, $file['path']);
        }
        return $this->checkpoint($journal, 'files_written');
    }

    private function prepareBaselines(array $journal, array $bundle, int $moduleId, int $generationId): array
    {
        $baselineFiles = array_values(array_filter(
            $journal['files'],
            static fn (array $file): bool => $file['remote_hash'] !== null
        ));
        $journal['prepared_blobs'] = array_map(function (array $file): array {
            $expected = $this->baselines->inspectExpected((string) $file['remote_hash']);
            return [
                'path' => $expected['path'], 'hash' => $expected['hash'], 'created' => null,
                'preexisting' => null, 'materialization' => 'planned',
            ];
        }, $baselineFiles);
        $this->writeJournal($journal);

        $records = [];
        foreach ($baselineFiles as $index => $file) {
            $expected = $this->baselines->inspectExpected((string) $file['remote_hash']);
            $journal['prepared_blobs'][$index]['preexisting'] = $expected['existing'];
            $this->writeJournal($journal);

            $remote = (string) $bundle['remoteContents'][$file['path']];
            $blob = $this->baselines->prepare($remote);
            $this->fault('after_blob_materialized', $index, $file['path']);
            $this->fault('before_blob_checkpoint', $index, $file['path']);
            $journal['prepared_blobs'][$index]['created'] = $blob['created'];
            $journal['prepared_blobs'][$index]['materialization'] = 'ready';
            $this->writeJournal($journal);
            $records[] = [
                'business_module_id' => $moduleId, 'relative_path' => $file['path'],
                'artifact_type' => $file['artifact_type'],
                'base_hash' => $file['remote_hash'], 'base_storage_path' => $blob['path'],
                'target_hash' => $file['target_hash'], 'template_version' => $bundle['templateVersion'],
                'definition_hash' => $bundle['definitionHash'], 'generation_id' => $generationId,
                'content_kind' => $file['content_kind'], 'status' => 'active',
            ];
            $this->fault('after_blob_prepare', $index, $file['path']);
        }
        return [$journal, $records];
    }

    private function rollbackJournal(array $journal, bool $resourcesBegun): array
    {
        $errors = [];
        $rollbackResources = $resourcesBegun || in_array(
            $journal['state'] ?? '',
            ['resources_applying', 'resources_applied', 'baselines_committing'],
            true
        );
        try {
            $journal = $this->checkpoint($journal, 'rolling_back');
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
        if ($rollbackResources) {
            try {
                $this->resourceCall('rollback');
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        try {
            $latest = $this->inspect((string) $journal['transaction_id']);
            $this->baselines->rollback((array) ($latest['prepared_blobs'] ?? []));
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }
        foreach (array_reverse($this->rollbackIndexes($journal)) as $index) {
            try {
                $this->restoreFile($journal['files'][$index]);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if ($errors === []) {
            $this->cleanupTransaction($journal);
            $this->checkpoint($journal, 'rolled_back');
        } else {
            $this->checkpoint($journal, 'recovery_required');
        }
        return $errors;
    }

    /** 返回 WAL 已记录及可由 target/backup hash 证明已完成 rename 的文件索引。 */
    private function rollbackIndexes(array &$journal): array
    {
        $indexes = array_values(array_unique(array_map('intval', (array) ($journal['applied'] ?? []))));
        foreach ($journal['files'] as $index => $file) {
            if (($file['write_state'] ?? '') !== 'committing' || in_array($index, $indexes, true)) {
                continue;
            }
            $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], '项目目录');
            $targetHash = is_file($target) && !is_link($target) ? hash_file('sha256', $target) : null;
            $backup = PathGuard::resolve($this->projectRoot, (string) $file['backup_path'], '项目目录');
            $backupHash = is_file($backup) && !is_link($backup) ? hash_file('sha256', $backup) : null;
            $committed = $file['status'] === 'create'
                ? $targetHash !== null && hash_equals((string) $file['target_hash'], (string) $targetHash)
                : $backupHash !== null && hash_equals((string) $file['local_hash'], (string) $backupHash)
                    && ($file['status'] === 'delete'
                        ? $targetHash === null
                        : $targetHash !== null && hash_equals((string) $file['target_hash'], (string) $targetHash));
            if ($committed) {
                $indexes[] = $index;
                $journal['files'][$index]['write_state'] = 'written';
            }
        }
        $journal['applied'] = $indexes;
        $this->writeJournal($journal);
        return $indexes;
    }

    private function restoreFile(array $file): void
    {
        $target = PathGuard::resolve($this->projectRoot, $file['path'], '项目目录');
        $backup = PathGuard::resolve($this->projectRoot, (string) $file['backup_path'], '项目目录');
        clearstatcache(true, $target);
        $targetHash = is_file($target) && !is_link($target) ? hash_file('sha256', $target) : null;
        if ($file['status'] === 'create') {
            if ($targetHash !== null && hash_equals((string) $file['target_hash'], (string) $targetHash) && unlink($target)) {
                return;
            }
            if ($targetHash === null) {
                return;
            }
            throw new RuntimeException('新增文件状态不可证明：' . $file['path']);
        }
        if (!is_file($backup) || is_link($backup)
            || !hash_equals((string) $file['local_hash'], (string) hash_file('sha256', $backup))) {
            throw new RuntimeException('backup 缺失或 hash 不匹配：' . $file['path']);
        }
        if ($targetHash !== null && $file['target_hash'] !== null && !hash_equals((string) $file['target_hash'], (string) $targetHash)) {
            throw new RuntimeException('目标文件状态不可证明：' . $file['path']);
        }
        if ($targetHash !== null && !unlink($target)) {
            throw new RuntimeException('无法移除待恢复目标：' . $file['path']);
        }
        if (!rename($backup, $target) || !hash_equals((string) $file['local_hash'], (string) hash_file('sha256', $target))) {
            throw new RuntimeException('无法恢复 backup：' . $file['path']);
        }
    }

    /** 根据 target/backup hash 收敛 rename 后、checkpoint 前的 writing 窗口。 */
    private function resolveInterruptedCommits(array $journal): array
    {
        foreach ($journal['files'] as $index => $file) {
            if (($file['write_state'] ?? '') !== 'committing' || in_array($index, (array) $journal['applied'], true)) {
                continue;
            }
            $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], '项目目录');
            $targetHash = is_file($target) && !is_link($target) ? hash_file('sha256', $target) : null;
            $backup = PathGuard::resolve($this->projectRoot, (string) $file['backup_path'], '项目目录');
            $backupHash = is_file($backup) && !is_link($backup) ? hash_file('sha256', $backup) : null;
            $committed = $file['status'] === 'create'
                ? $targetHash !== null && hash_equals((string) $file['target_hash'], (string) $targetHash)
                : $backupHash !== null && hash_equals((string) $file['local_hash'], (string) $backupHash)
                    && ($file['status'] === 'delete'
                        ? $targetHash === null
                        : $targetHash !== null && hash_equals((string) $file['target_hash'], (string) $targetHash));
            if ($committed) {
                $journal['files'][$index]['write_state'] = 'written';
                $journal['applied'][] = $index;
                $this->writeJournal($journal);
                continue;
            }
            $notCommitted = $targetHash === ($file['local_hash'] ?? null) && $backupHash === null;
            if (!$notCommitted) {
                return $this->checkpoint($journal, 'recovery_required');
            }
            $journal['files'][$index]['write_state'] = 'planned';
            $this->writeJournal($journal);
        }
        return $journal;
    }

    private function isGenerationCommitted(array $journal): bool
    {
        if (!is_object($this->stateRepository) || !method_exists($this->stateRepository, 'isGenerationCommitted')) {
            throw new RuntimeException('generation state repository 不支持提交证据查询');
        }
        return $this->stateRepository->isGenerationCommitted(
            (int) $journal['module_id'],
            (int) $journal['generation_id'],
            (string) $journal['transaction_id'],
            (string) $journal['plan_digest']
        );
    }

    private function finalizeCommitted(array $journal): array
    {
        if (!$this->committedArtifactsMatch($journal)) {
            $this->checkpoint($journal, 'recovery_required');
            throw new RuntimeException('已提交事务的文件或 baseline blob 不一致，事务需要人工恢复');
        }
        $journal = $this->checkpoint($journal, 'completed');
        $this->cleanupTransaction($journal);
        return $journal;
    }

    private function committedArtifactsMatch(array $journal): bool
    {
        foreach ($journal['files'] as $file) {
            $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], '项目目录');
            $actual = is_file($target) && !is_link($target) ? hash_file('sha256', $target) : null;
            $expected = $file['status'] === 'delete' ? null : ($file['target_hash'] ?? $file['local_hash'] ?? null);
            if ($actual !== $expected) {
                return false;
            }
        }
        try {
            $this->baselines->commit((array) ($journal['prepared_blobs'] ?? []));
        } catch (Throwable) {
            return false;
        }
        return true;
    }

    private function canRollback(array $journal): bool
    {
        if (($journal['state'] ?? '') === 'recovery_required') {
            return false;
        }
        foreach ($this->rollbackIndexes($journal) as $index) {
            $file = $journal['files'][$index] ?? null;
            if (!is_array($file)) return false;
            $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], '项目目录');
            $actual = is_file($target) && !is_link($target) ? hash_file('sha256', $target) : null;
            if ($file['status'] === 'create') {
                if ($actual !== null && !hash_equals((string) $file['target_hash'], (string) $actual)) return false;
                continue;
            }
            $backup = PathGuard::resolve($this->projectRoot, (string) $file['backup_path'], '项目目录');
            if (!is_file($backup) || is_link($backup)
                || !hash_equals((string) $file['local_hash'], (string) hash_file('sha256', $backup))) return false;
            if ($actual !== null && $file['target_hash'] !== null && !hash_equals((string) $file['target_hash'], (string) $actual)) return false;
        }
        return true;
    }

    private function checkpoint(array $journal, string $state): array
    {
        if (!in_array($state, self::STATES, true)) {
            throw new RuntimeException('generation WAL 阶段无效');
        }
        $journal['state'] = $state;
        if (!in_array($state, $journal['history'], true)) {
            $journal['history'][] = $state;
        }
        $this->writeJournal($journal);
        return $journal;
    }

    private function writeJournal(array $journal): void
    {
        $id = (string) $journal['transaction_id'];
        $this->assertTransactionId($id);
        $file = $this->walFile($id);
        $this->secureDirectory(dirname($file));
        $journal['updated_at'] = gmdate(DATE_ATOM);
        $json = json_encode($journal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (str_contains($json, rtrim($this->projectRoot, DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('generation WAL 禁止包含项目绝对路径');
        }
        $temporary = dirname($file) . DIRECTORY_SEPARATOR . '.wal-' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !chmod($temporary, 0600)
            || !rename($temporary, $file) || !chmod($file, 0600)) {
            @unlink($temporary);
            throw new RuntimeException('generation WAL 原子写入失败');
        }
    }

    private function walFile(string $id): string
    {
        return $this->privateRoot() . DIRECTORY_SEPARATOR . 'wal' . DIRECTORY_SEPARATOR . $id . '.json';
    }

    private function cleanupTransaction(array $journal): void
    {
        $path = $this->privateRoot() . DIRECTORY_SEPARATOR . 'transactions' . DIRECTORY_SEPARATOR . $journal['transaction_id'];
        $this->removeTree($path);
    }

    private function privateRoot(): string
    {
        return $this->baselines->root();
    }

    private function locked(callable $operation): mixed
    {
        $directory = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache';
        $this->secureDirectory($directory);
        $handle = @fopen($directory . DIRECTORY_SEPARATOR . 'business-development-write.lock', 'c+');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) fclose($handle);
            throw new RuntimeException('无法获取 business development 排他锁');
        }
        try {
            return $operation();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function resourceCall(string $method, array $arguments = []): mixed
    {
        if (!is_object($this->resources) || !method_exists($this->resources, $method)) {
            throw new RuntimeException('结构化资源事务不支持：' . $method);
        }
        return $this->resources->{$method}(...$arguments);
    }

    private function fault(string $point, int $index, string $path): void
    {
        if ($this->faultHook !== null) {
            ($this->faultHook)($point, $index, $path);
        }
    }

    private static function defaultProjectRoot(): string
    {
        if (function_exists('root_path')) {
            return rtrim((string) root_path(), DIRECTORY_SEPARATOR);
        }
        return dirname(__DIR__, 3);
    }

    private function assertTransactionId(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            throw new RuntimeException('generation transaction id 无效');
        }
    }

    private function assertHash(string $hash, string $label): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
            throw new RuntimeException($label . ' 无效');
        }
    }

    private function ensureTargetDirectory(string $directory): void
    {
        if (is_link($directory)) throw new RuntimeException('目标目录禁止符号链接');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建目标目录');
        }
    }

    private function secureDirectory(string $directory): void
    {
        if (is_link($directory)) throw new RuntimeException('私有目录禁止符号链接');
        $oldMask = umask(0077);
        try {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('无法创建私有目录');
            }
        } finally {
            umask($oldMask);
        }
        if (!chmod($directory, 0700)) throw new RuntimeException('私有目录权限设置失败');
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) return;
        if (is_link($path)) throw new RuntimeException('拒绝清理符号链接事务目录');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) {
            if ($item->isLink()) throw new RuntimeException('事务目录包含符号链接');
            $ok = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (!$ok) throw new RuntimeException('无法清理事务文件');
        }
        if (!rmdir($path)) throw new RuntimeException('无法清理事务目录');
    }
}
