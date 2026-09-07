<?php

declare(strict_types=1);

namespace app\console\service;

use FilesystemIterator;
use fun\plugins\Manifest;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** 以目录为独占单元原子发布 Manifest v2 原生应用。 */
final class PluginAppPublicationService
{
    private const RESOURCE_TYPE = 'native_app';
    private const CONSOLE_LAYERS = ['controller', 'model', 'service', 'validate', 'middleware'];

    public function __construct(
        private readonly string $appRoot,
        private readonly string $runtimeRoot,
        private readonly PluginAppPublicationRepository $repository,
        private readonly mixed $faultHook = null
    ) {
        if ($this->faultHook !== null && !is_callable($this->faultHook)) {
            throw new RuntimeException('publication fault hook 必须可调用');
        }
    }

    public function publish(
        Manifest $manifest,
        string $token,
        bool $lock = true,
        bool $rollbackOnFailure = true
    ): array {
        $this->prepareOperation($token, $manifest->code(), 'publish', $rollbackOnFailure);
        try {
            return $this->applyPrepared($manifest, $token, $lock, $rollbackOnFailure);
        } catch (Throwable $exception) {
            if ($rollbackOnFailure && ($this->inspect($token)['state'] ?? '') === 'operation_prepared') {
                $this->rollback($token, $lock);
            }
            throw $exception;
        }
    }

    public function prepareOperation(
        string $token,
        string $pluginCode,
        string $operation,
        bool $rollbackOnFailure = true,
        array $recoveryContext = []
    ): array {
        $this->assertToken($token);
        $this->assertPluginCode($pluginCode);
        $this->assertNewToken($token);
        if (!in_array($operation, ['publish', 'remove'], true)) {
            throw new RuntimeException('publication operation 无效');
        }
        $journal = [
            'schema_version' => 1,
            'token' => $token,
            'plugin' => $pluginCode,
            'operation' => $operation,
            'state' => 'operation_prepared',
            'deployment_rollback_allowed' => $rollbackOnFailure,
            'registry_before' => [],
            'units' => [],
            'recovery_context' => $recoveryContext,
            'recovery_steps' => $this->emptyRecoverySteps(),
        ];
        $this->writeJournal($token, $journal);
        return $journal;
    }

    public function applyPrepared(
        Manifest $manifest,
        string $token,
        bool $lock = true,
        bool $rollbackOnFailure = true
    ): array {
        $this->assertToken($token);
        $journal = $this->inspect($token);
        if (($journal['plugin'] ?? '') !== $manifest->code() || ($journal['operation'] ?? '') !== 'publish'
            || ($journal['state'] ?? '') !== 'operation_prepared') {
            throw new RuntimeException('publication operation journal 与发布请求不匹配');
        }
        $operation = function () use ($manifest, $token, $rollbackOnFailure, $journal): array {
            return $this->execute(
                $manifest->code(),
                $manifest->version(),
                $token,
                $this->sourceUnits($manifest),
                false,
                $rollbackOnFailure,
                $journal
            );
        };
        return $lock ? $this->locked($operation) : $operation();
    }

    public function remove(
        string $pluginCode,
        string $token,
        bool $lock = true,
        bool $rollbackOnFailure = true
    ): array
    {
        $this->prepareOperation($token, $pluginCode, 'remove', $rollbackOnFailure);
        try {
            return $this->applyPreparedRemove($pluginCode, $token, $lock, $rollbackOnFailure);
        } catch (Throwable $exception) {
            if ($rollbackOnFailure && ($this->inspect($token)['state'] ?? '') === 'operation_prepared') {
                $this->rollback($token, $lock);
            }
            throw $exception;
        }
    }

    public function applyPreparedRemove(
        string $pluginCode,
        string $token,
        bool $lock = true,
        bool $rollbackOnFailure = true
    ): array {
        $this->assertPluginCode($pluginCode);
        $this->assertToken($token);
        $journal = $this->inspect($token);
        if (($journal['plugin'] ?? '') !== $pluginCode || ($journal['operation'] ?? '') !== 'remove'
            || ($journal['state'] ?? '') !== 'operation_prepared') {
            throw new RuntimeException('publication operation journal 与删除请求不匹配');
        }
        $operation = function () use ($pluginCode, $token, $rollbackOnFailure, $journal): array {
            $owned = $this->ownedRecords($pluginCode);
            $this->assertCurrentUnmodified($pluginCode, $owned);
            $units = [];
            foreach ($this->recordsByUnit($owned) as $unit => $records) {
                $target = $this->unitTarget($unit, $pluginCode);
                $units[$unit] = ['source' => null, 'target' => $target, 'records' => $records];
            }
            return $this->execute($pluginCode, '', $token, $units, true, $rollbackOnFailure, $journal);
        };
        return $lock ? $this->locked($operation) : $operation();
    }

    public function inspect(string $token): array
    {
        $this->assertToken($token);
        $file = $this->journalFile($token);
        if (!is_file($file)) {
            throw new RuntimeException('publication journal 不存在：' . $token);
        }
        try {
            $journal = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('publication journal 损坏：' . $token, 0, $exception);
        }
        if (!is_array($journal) || ($journal['token'] ?? '') !== $token) {
            throw new RuntimeException('publication journal 内容无效：' . $token);
        }
        return $journal;
    }

    public function stale(?string $pluginCode = null): array
    {
        if ($pluginCode !== null) {
            $this->assertPluginCode($pluginCode);
        }
        $root = $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-journals';
        if (!is_dir($root)) {
            return [];
        }
        $journals = [];
        foreach ($this->directoryEntries($root) as $directory) {
            if (!is_dir($directory) || !is_file($directory . DIRECTORY_SEPARATOR . 'journal.json')) {
                continue;
            }
            $token = basename($directory);
            try {
                $journal = $this->inspect($token);
            } catch (Throwable) {
                continue;
            }
            if (($journal['state'] ?? '') !== 'completed'
                && ($pluginCode === null || ($journal['plugin'] ?? '') === $pluginCode)) {
                $journals[] = $journal;
            }
        }
        usort($journals, static fn (array $left, array $right): int => strcmp((string) ($left['updated_at'] ?? ''), (string) ($right['updated_at'] ?? '')));
        return $journals;
    }

    public function recover(string $token, bool $manual = false, ?callable $restoreContext = null): void
    {
        $this->assertToken($token);
        $this->locked(function () use ($token, $manual, $restoreContext): void {
            $journal = $this->inspect($token);
            if (($journal['state'] ?? '') === 'completed') {
                return;
            }
            if (($journal['state'] ?? '') === 'finalizing' || ($journal['commit_decided'] ?? false) === true) {
                $steps = array_replace($this->emptyFinalizationSteps(), (array) ($journal['finalization_steps'] ?? []));
                if (!$steps['files_cleaned']) {
                    $this->markFinalizationStep($token, 'files_cleaned');
                }
                $this->finishFinalization($token);
                return;
            }
            if ((($journal['manual_recovery'] ?? false) === true
                || ($journal['deployment_rollback_allowed'] ?? true) === false) && !$manual) {
                throw new RuntimeException('publication 需要显式人工恢复：' . $token);
            }
            $this->writeJournal($token, array_replace($journal, ['state' => 'rollback_required']));
            if (($journal['state'] ?? '') !== 'operation_prepared') {
                $this->restore($journal);
            }
            if ($restoreContext !== null) {
                $restoreContext((array) ($journal['recovery_context'] ?? []));
            }
            $journal = $this->inspect($token);
            $this->cleanupArtifacts($journal);
            $this->writeJournal($token, array_replace($journal, ['state' => 'completed', 'rolled_back' => true]));
        });
    }

    public function attachRecoveryContext(string $token, array $context): void
    {
        $journal = $this->inspect($token);
        if (($journal['state'] ?? '') === 'completed') {
            throw new RuntimeException('completed publication 不允许追加恢复上下文：' . $token);
        }
        $journal['recovery_context'] = array_replace_recursive(
            (array) ($journal['recovery_context'] ?? []),
            $context
        );
        $this->writeJournal($token, $journal);
    }

    public function requireManualRecovery(string $token, array $context = []): string
    {
        $journal = $this->inspect($token);
        $recoveryRoot = $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-recovery' . DIRECTORY_SEPARATOR . $token;
        foreach ((array) ($journal['units'] ?? []) as $index => $unit) {
            $backup = (string) ($unit['backup'] ?? '');
            $previous = (string) ($unit['previous_backup'] ?? '');
            $durable = $recoveryRoot . DIRECTORY_SEPARATOR . $this->unitSlug((string) $unit['unit']);
            if (($unit['manual_materialized'] ?? false) !== true && $backup !== '' && is_dir($backup)) {
                $this->copyTree($backup, $durable);
                if (!hash_equals($this->treeHash($backup), $this->treeHash($durable))) {
                    throw new RuntimeException('publication durable backup tree hash 校验失败：' . $unit['unit']);
                }
                $this->fault('before_manual_journal', $index, $backup);
                $journal['units'][$index]['previous_backup'] = $backup;
                $journal['units'][$index]['backup'] = $durable;
                $journal['units'][$index]['recovery_backup'] = $durable;
                $journal['units'][$index]['manual_materialized'] = true;
                $journal['units'][$index]['manual_backup_cleaned'] = false;
                $this->writeJournal($token, $journal);
                $previous = $backup;
                $this->fault('after_manual_journal', $index, $backup);
            }
            if (($journal['units'][$index]['manual_backup_cleaned'] ?? false) !== true) {
                $previous = (string) ($journal['units'][$index]['previous_backup'] ?? $previous);
                if ($previous !== '' && $previous !== $durable) {
                    $this->removeTree($previous);
                }
                $journal['units'][$index]['manual_backup_cleaned'] = true;
                $this->writeJournal($token, $journal);
                $this->fault('after_manual_unlink', $index, $previous);
            }
        }
        $journal = $this->inspect($token);
        $journal['state'] = 'rollback_required';
        $journal['manual_recovery'] = true;
        $journal['recovery_path'] = $recoveryRoot;
        $journal['recovery_context'] = array_replace_recursive(
            (array) ($journal['recovery_context'] ?? []),
            $context
        );
        $this->ensureDirectory($recoveryRoot);
        $contextFile = $recoveryRoot . DIRECTORY_SEPARATOR . 'recovery-context.json';
        $encodedContext = json_encode(
            $journal['recovery_context'],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        if (file_put_contents($contextFile, $encodedContext . "\n", LOCK_EX) === false) {
            throw new RuntimeException('无法持久化 publication 恢复上下文');
        }
        $journal['recovery_context_path'] = $contextFile;
        $this->writeJournal($token, $journal);
        return $recoveryRoot;
    }

    public function markRollbackRequired(string $token): void
    {
        $journal = $this->inspect($token);
        if (($journal['state'] ?? '') === 'completed') {
            return;
        }
        $journal['recovery_steps'] = array_replace(
            $this->emptyRecoverySteps(),
            (array) ($journal['recovery_steps'] ?? [])
        );
        $this->writeJournal($token, array_replace($journal, ['state' => 'rollback_required']));
    }

    public function markRecoveryStep(string $token, string $step): void
    {
        if (!array_key_exists($step, $this->emptyRecoverySteps())) {
            throw new RuntimeException('publication recovery step 无效：' . $step);
        }
        $journal = $this->inspect($token);
        $steps = array_replace($this->emptyRecoverySteps(), (array) ($journal['recovery_steps'] ?? []));
        $steps[$step] = true;
        $journal['recovery_steps'] = $steps;
        $this->writeJournal($token, $journal);
    }

    public function cleanupRecoveryArtifacts(string $token): void
    {
        $journal = $this->inspect($token);
        $this->cleanupArtifacts($journal, false);
    }

    public function completeRecovery(string $token): void
    {
        $journal = $this->inspect($token);
        $steps = array_replace($this->emptyRecoverySteps(), (array) ($journal['recovery_steps'] ?? []));
        if (in_array(false, $steps, true)) {
            throw new RuntimeException('publication recovery steps 尚未全部完成：' . $token);
        }
        $journal['recovery_steps'] = $steps;
        $this->writeJournal($token, array_replace($journal, ['state' => 'completed', 'rolled_back' => true]));
    }

    public function rollback(
        string $token,
        bool $lock = true,
        bool $cleanupArtifactsOnRollback = true
    ): void {
        $this->assertToken($token);
        $operation = function () use ($token, $cleanupArtifactsOnRollback): void {
            $journal = $this->inspect($token);
            if (($journal['manual_recovery'] ?? false) === true) {
                throw new RuntimeException('publication 需要显式人工恢复：' . $token);
            }
            $this->writeJournal($token, array_replace($journal, ['state' => 'rollback_required']));
            if (($journal['state'] ?? '') !== 'operation_prepared') {
                $this->restore($journal);
            }
            if ($cleanupArtifactsOnRollback) {
                $this->cleanupArtifactsOnRollback($token, false);
            }
        };
        if ($lock) {
            $this->locked($operation);
            return;
        }
        $operation();
    }

    public function cleanupArtifactsOnRollback(
        string $token,
        bool $lock = true,
        bool $cleanupSharedTokenRoot = true
    ): void {
        $this->assertToken($token);
        $operation = function () use ($token, $cleanupSharedTokenRoot): void {
            $journal = $this->inspect($token);
            if (($journal['state'] ?? '') !== 'rollback_required') {
                throw new RuntimeException('publication 尚未完成回滚，不能清理恢复材料：' . $token);
            }
            $this->cleanupArtifacts($journal, $cleanupSharedTokenRoot);
            $this->writeJournal($token, array_replace($journal, ['state' => 'completed', 'rolled_back' => true]));
        };
        if ($lock) {
            $this->locked($operation);
            return;
        }
        $operation();
    }

    public function beginFinalization(string $token): void
    {
        $journal = $this->inspect($token);
        if (($journal['state'] ?? '') === 'completed' || ($journal['state'] ?? '') === 'finalizing') {
            return;
        }
        if (($journal['state'] ?? '') !== 'registry_committed') {
            throw new RuntimeException('publication 尚未提交 registry，不能完成：' . $token);
        }
        $journal['state'] = 'finalizing';
        $journal['commit_decided'] = true;
        $journal['finalization_steps'] = array_replace(
            $this->emptyFinalizationSteps(),
            (array) ($journal['finalization_steps'] ?? [])
        );
        $this->writeJournal($token, $journal);
    }

    public function markFinalizationStep(string $token, string $step): void
    {
        if (!array_key_exists($step, $this->emptyFinalizationSteps())) {
            throw new RuntimeException('publication finalization step 无效：' . $step);
        }
        $journal = $this->inspect($token);
        if (($journal['state'] ?? '') !== 'finalizing' || ($journal['commit_decided'] ?? false) !== true) {
            throw new RuntimeException('publication 尚未写入提交决定：' . $token);
        }
        $steps = array_replace($this->emptyFinalizationSteps(), (array) ($journal['finalization_steps'] ?? []));
        $steps[$step] = true;
        $journal['finalization_steps'] = $steps;
        $this->writeJournal($token, $journal);
    }

    public function finishFinalization(string $token): void
    {
        $journal = $this->inspect($token);
        $steps = array_replace($this->emptyFinalizationSteps(), (array) ($journal['finalization_steps'] ?? []));
        if (($steps['files_cleaned'] ?? false) !== true) {
            throw new RuntimeException('publication public 清理尚未完成：' . $token);
        }
        foreach ((array) ($journal['units'] ?? []) as $index => $unit) {
            $cleanup = array_replace(['temp' => false, 'backup' => false], (array) ($unit['cleanup'] ?? []));
            if (!$cleanup['temp']) {
                $this->fault('before_finalization_temp_cleanup', $index, (string) ($unit['temp'] ?? ''));
                $this->removeTree((string) ($unit['temp'] ?? ''));
                $cleanup['temp'] = true;
                $journal['units'][$index]['cleanup'] = $cleanup;
                $this->writeJournal($token, $journal);
            }
            if (!$cleanup['backup']) {
                $this->fault('before_finalization_backup_cleanup', $index, (string) ($unit['backup'] ?? ''));
                $this->removeTree((string) ($unit['backup'] ?? ''));
                $cleanup['backup'] = true;
                $journal['units'][$index]['cleanup'] = $cleanup;
                $this->writeJournal($token, $journal);
            }
        }
        if (!$steps['native_cleaned']) {
            $steps['native_cleaned'] = true;
            $journal['finalization_steps'] = $steps;
            $this->writeJournal($token, $journal);
        }
        if (!$steps['shared_recovery_cleaned']) {
            $this->fault('before_finalization_shared_cleanup', 0, $token);
            $recoveryRoot = $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-recovery';
            if (is_link($recoveryRoot)) {
                throw new RuntimeException('publication recovery 根目录禁止符号链接');
            }
            $this->removeTree($recoveryRoot . DIRECTORY_SEPARATOR . $token);
            $steps['shared_recovery_cleaned'] = true;
            $journal['finalization_steps'] = $steps;
            $this->writeJournal($token, $journal);
        }
        $this->writeJournal($token, array_replace($journal, ['state' => 'completed']));
    }

    public function complete(string $token, bool $lock = true): void
    {
        $this->assertToken($token);
        $operation = function () use ($token): void {
            $this->beginFinalization($token);
            $this->markFinalizationStep($token, 'files_cleaned');
            $this->finishFinalization($token);
        };
        if ($lock) {
            $this->locked($operation);
            return;
        }
        $operation();
    }

    private function execute(
        string $pluginCode,
        string $version,
        string $token,
        array $sourceUnits,
        bool $removing = false,
        bool $localRollbackOnFailure = true,
        ?array $preparedJournal = null
    ): array {
        $existing = $this->repository->all();
        $owned = $this->ownedRecords($pluginCode, $existing);
        $this->assertCurrentUnmodified($pluginCode, $owned);
        $oldByUnit = $this->recordsByUnit($owned);
        if (!$removing) {
            foreach ($oldByUnit as $unit => $records) {
                if (!isset($sourceUnits[$unit])) {
                    $sourceUnits[$unit] = [
                        'source' => null,
                        'target' => $this->unitTarget($unit, $pluginCode),
                        'records' => $records,
                    ];
                }
            }
        }
        $this->assertExclusive($pluginCode, $sourceUnits, $existing, $oldByUnit);
        $journal = [
            'schema_version' => 1,
            'token' => $token,
            'plugin' => $pluginCode,
            'operation' => $removing ? 'remove' : 'publish',
            'state' => 'prepared',
            'deployment_rollback_allowed' => $preparedJournal['deployment_rollback_allowed'] ?? $localRollbackOnFailure,
            'registry_before' => $owned,
            'units' => [],
            'recovery_steps' => array_replace(
                $this->emptyRecoverySteps(),
                (array) ($preparedJournal['recovery_steps'] ?? [])
            ),
            'updated_at' => date(DATE_ATOM),
        ];
        $next = [];
        try {
            foreach ($sourceUnits as $unit => $definition) {
                if ($definition['source'] !== null && $this->files($definition['source']) === []) {
                    if (!isset($oldByUnit[$unit])) {
                        continue;
                    }
                    $definition['source'] = null;
                }
                $target = $definition['target'];
                $temp = dirname($target) . DIRECTORY_SEPARATOR . '.publication-' . $token . '-' . $this->unitSlug($unit);
                $backup = $this->backupPath($token, $target);
                $oldHash = is_dir($target) ? $this->treeHash($target) : null;
                $newHash = $definition['source'] !== null ? $this->treeHash($definition['source']) : null;
                if ($definition['source'] !== null) {
                    $next = array_merge($next, $this->recordsForUnit(
                        $pluginCode,
                        $version,
                        $token,
                        $unit,
                        $definition['source'],
                        $target,
                        (string) $newHash
                    ));
                }
                $journal['units'][] = [
                    'token' => $token,
                    'plugin' => $pluginCode,
                    'unit' => $unit,
                    'source' => $definition['source'],
                    'target' => $target,
                    'temp' => $temp,
                    'backup' => $backup,
                    'old_tree_hash' => $oldHash,
                    'new_tree_hash' => $newHash,
                    'materialization_state' => $definition['source'] === null ? 'not_required' : 'planned',
                    'state' => 'prepared',
                ];
            }
            if ($preparedJournal !== null) {
                $journal['recovery_context'] = (array) ($preparedJournal['recovery_context'] ?? []);
            }
            $this->writeJournal($token, $journal);
            foreach ($journal['units'] as $index => $unit) {
                if (($unit['materialization_state'] ?? '') === 'planned') {
                    $this->copyTree((string) $unit['source'], (string) $unit['temp']);
                    $actualHash = $this->treeHash((string) $unit['temp']);
                    if (!hash_equals((string) $unit['new_tree_hash'], $actualHash)) {
                        throw new RuntimeException('publication tree hash 校验失败：' . $unit['unit']);
                    }
                    $journal['units'][$index]['materialization_state'] = 'materialized';
                    $this->writeJournal($token, $journal);
                    $this->fault('after_native_materialize', $index, (string) $unit['temp']);
                    $unit = $journal['units'][$index];
                }
                $this->swap($unit);
                $journal['units'][$index]['state'] = 'swapped';
                $journal['state'] = 'swapped';
                $this->writeJournal($token, $journal);
            }
            $this->repository->replaceAppForPlugin($pluginCode, $next);
            $journal['state'] = 'registry_committed';
            foreach ($journal['units'] as &$unit) {
                $unit['state'] = 'registry_committed';
            }
            unset($unit);
            $this->writeJournal($token, $journal);
            return ['token' => $token, 'plugin' => $pluginCode, 'state' => 'registry_committed'];
        } catch (Throwable $exception) {
            if (is_file($this->journalFile($token))) {
                $current = $this->inspect($token);
                $this->writeJournal($token, array_replace($current, ['state' => 'rollback_required']));
                if ($localRollbackOnFailure) {
                    try {
                        $this->restore($current);
                        $current = $this->inspect($token);
                        $this->cleanupArtifacts($current);
                        $this->writeJournal($token, array_replace($current, ['state' => 'completed', 'rolled_back' => true]));
                    } catch (Throwable $rollbackException) {
                        throw new RuntimeException(
                            $exception->getMessage() . '；原生 App 自动回滚失败：' . $rollbackException->getMessage(),
                            0,
                            $exception
                        );
                    }
                }
            } else {
                foreach ($sourceUnits as $unit => $definition) {
                    $temp = dirname($definition['target']) . DIRECTORY_SEPARATOR . '.publication-' . $token . '-' . $this->unitSlug($unit);
                    $this->removeTree($temp);
                }
            }
            throw $exception;
        }
    }

    private function sourceUnits(Manifest $manifest): array
    {
        if (($manifest->toArray()['schema_version'] ?? null) !== 2) {
            throw new RuntimeException('原生 App 发布仅支持 Manifest v2');
        }
        $pluginRoot = realpath($manifest->directory());
        $appSource = realpath($manifest->directory() . DIRECTORY_SEPARATOR . 'app');
        if ($pluginRoot === false || $appSource === false || !str_starts_with($appSource, $pluginRoot . DIRECTORY_SEPARATOR)) {
            return [];
        }
        $allowedRoot = [$manifest->code() => true, 'console' => true];
        foreach ($this->directoryEntries($appSource) as $entry) {
            if (is_link($entry)) {
                throw new RuntimeException('原生 App 源目录禁止符号链接：' . $entry);
            }
            if (!is_dir($entry) || !isset($allowedRoot[basename($entry)])) {
                throw new RuntimeException('插件 app 根目录存在未允许目录或文件：' . basename($entry));
            }
        }
        $units = [];
        $application = $appSource . DIRECTORY_SEPARATOR . $manifest->code();
        if (is_dir($application)) {
            $this->assertSafeSourceTree($application, $appSource);
            $units['app:' . $manifest->code()] = [
                'source' => $application,
                'target' => $this->appDirectory() . DIRECTORY_SEPARATOR . $manifest->code(),
            ];
        }
        $console = $appSource . DIRECTORY_SEPARATOR . 'console';
        if (is_dir($console)) {
            foreach ($this->directoryEntries($console) as $entry) {
                if (is_link($entry)) {
                    throw new RuntimeException('原生 App 源目录禁止符号链接：' . $entry);
                }
                $layer = basename($entry);
                if (!is_dir($entry)) {
                    throw new RuntimeException('Console 根目录只允许固定 layer 目录：' . $layer);
                }
                if (!in_array($layer, self::CONSOLE_LAYERS, true)) {
                    throw new RuntimeException('未知 Console layer：' . $layer);
                }
                $this->assertSafeSourceTree($entry, $appSource);
                $units['console:' . $layer . ':' . $manifest->code()] = [
                    'source' => $entry,
                    'target' => $this->appDirectory() . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR
                        . $layer . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . $manifest->code(),
                ];
            }
        }
        ksort($units, SORT_STRING);
        return $units;
    }

    private function assertCurrentUnmodified(string $pluginCode, array $owned): void
    {
        if ($owned === []) {
            return;
        }
        $conflicts = [];
        $byUnit = $this->recordsByUnit($owned);
        foreach ($byUnit as $unit => $records) {
            $targetRoot = $this->unitTarget($unit, $pluginCode);
            $expectedTreeHash = (string) ($records[0]['tree_hash'] ?? '');
            if (is_dir($targetRoot) && $expectedTreeHash !== ''
                && !hash_equals($expectedTreeHash, $this->treeHash($targetRoot))) {
                $conflicts[] = 'modified-unit:' . $unit;
            }
            $registered = [];
            foreach ($records as $record) {
                $target = $this->registryTarget((string) $record['target_path']);
                $relative = $this->relativePath($targetRoot, $target);
                $registered[$relative] = true;
                if (!is_file($target)) {
                    $conflicts[] = 'deleted:' . $record['target_path'];
                    continue;
                }
                $hash = hash_file('sha256', $target);
                if ($hash === false || !hash_equals((string) $record['sha256'], $hash)) {
                    $conflicts[] = 'modified:' . $record['target_path'];
                }
            }
            if (is_dir($targetRoot)) {
                foreach ($this->files($targetRoot) as $file) {
                    $relative = $this->relativePath($targetRoot, $file);
                    if (!isset($registered[$relative])) {
                        $conflicts[] = 'untracked:' . $this->targetRegistryPath($file);
                    }
                }
            }
        }
        if ($conflicts !== []) {
            sort($conflicts, SORT_STRING);
            throw new RuntimeException('原生 App 存在二次开发冲突：' . implode(', ', $conflicts));
        }
    }

    private function assertExclusive(
        string $pluginCode,
        array $units,
        array $registry,
        array $oldByUnit
    ): void {
        foreach ($units as $unit => $definition) {
            foreach ($registry as $record) {
                if (($record['resource_type'] ?? '') === self::RESOURCE_TYPE
                    && ($record['publication_unit'] ?? '') === $unit
                    && ($record['plugin_code'] ?? '') !== $pluginCode) {
                    throw new RuntimeException('publication unit 已属于其他插件：' . $unit);
                }
            }
            if (file_exists($definition['target']) && !isset($oldByUnit[$unit])) {
                throw new RuntimeException('拒绝覆盖未登记的核心目标：' . $this->targetRegistryPath($definition['target']));
            }
            $this->assertNoTargetSymlink($definition['target']);
        }
    }

    private function swap(array $unit): void
    {
        $target = (string) $unit['target'];
        $backup = (string) $unit['backup'];
        $temp = (string) $unit['temp'];
        if (file_exists($target)) {
            $this->ensureDirectory(dirname($backup));
            if (!rename($target, $backup)) {
                throw new RuntimeException('无法备份原生 App publication unit：' . $target);
            }
        }
        if ($unit['source'] !== null && !rename($temp, $target)) {
            if (is_dir($backup)) {
                @rename($backup, $target);
            }
            throw new RuntimeException('无法原子切换原生 App publication unit：' . $target);
        }
    }

    private function restore(array $journal): void
    {
        $this->restoreNativeFilesFromJournal($journal);
        $this->restoreNativeRegistryFromJournal($journal);
    }

    public function restoreNativeFiles(string $token): void
    {
        $this->restoreNativeFilesFromJournal($this->inspect($token));
    }

    public function restoreNativeRegistry(string $token): void
    {
        $this->restoreNativeRegistryFromJournal($this->inspect($token));
    }

    private function restoreNativeFilesFromJournal(array $journal): void
    {
        $units = array_reverse((array) ($journal['units'] ?? []));
        foreach ($units as $unit) {
            $target = (string) ($unit['target'] ?? '');
            $backup = (string) ($unit['backup'] ?? '');
            $temp = (string) ($unit['temp'] ?? '');
            $swapped = in_array(($unit['state'] ?? ''), ['swapped', 'registry_committed'], true);
            if ($backup !== '' && is_dir($backup)) {
                $this->removeTree($target);
                $this->ensureDirectory(dirname($target));
                if (dirname($backup) === dirname($target)) {
                    if (!rename($backup, $target)) {
                        throw new RuntimeException('无法恢复原生 App publication unit：' . $target);
                    }
                } else {
                    $restoreTemp = dirname($target) . DIRECTORY_SEPARATOR . '.publication-restore-'
                        . (string) ($journal['token'] ?? '') . '-' . basename($target);
                    $this->copyTree($backup, $restoreTemp);
                    if (!rename($restoreTemp, $target)) {
                        throw new RuntimeException('无法原子恢复原生 App publication unit：' . $target);
                    }
                }
            } elseif (($swapped || $this->isInterruptedNewSwap($unit))
                && ($unit['old_tree_hash'] ?? null) === null) {
                $this->removeTree($target);
            }
            $this->removeTree($temp);
        }
    }

    private function restoreNativeRegistryFromJournal(array $journal): void
    {
        $this->repository->replaceAppForPlugin(
            (string) $journal['plugin'],
            (array) ($journal['registry_before'] ?? [])
        );
    }

    private function recordsForUnit(
        string $pluginCode,
        string $version,
        string $token,
        string $unit,
        string $source,
        string $target,
        string $treeHash
    ): array {
        $records = [];
        $now = date('Y-m-d H:i:s');
        foreach ($this->files($source) as $file) {
            $relative = $this->relativePath($source, $file);
            $sha256 = hash_file('sha256', $file);
            if ($sha256 === false) {
                throw new RuntimeException('无法计算原生 App SHA-256：' . $file);
            }
            $records[] = [
                'plugin_code' => $pluginCode,
                'version' => $version,
                'resource_type' => self::RESOURCE_TYPE,
                'publication_unit' => $unit,
                'operation_token' => $token,
                'source_path' => $this->unitSourcePath($unit, $relative),
                'target_path' => $this->targetRegistryPath($target . DIRECTORY_SEPARATOR . $relative),
                'sha256' => $sha256,
                'tree_hash' => $treeHash,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        return $records;
    }

    private function copyTree(string $source, string $target): void
    {
        $this->removeTree($target);
        $this->ensureDirectory($target);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('原生 App 目录禁止符号链接：' . $item->getPathname());
            }
            $relative = $this->relativePath($source, $item->getPathname());
            $destination = $target . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                $this->ensureDirectory($destination);
            } elseif (!copy($item->getPathname(), $destination)) {
                throw new RuntimeException('原生 App 文件复制失败：' . $relative);
            }
        }
    }

    private function treeHash(string $directory): string
    {
        $hashes = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('原生 App 目录禁止符号链接：' . $item->getPathname());
            }
            $relative = $this->relativePath($directory, $item->getPathname());
            if ($item->isDir()) {
                $hashes[] = 'd' . "\0" . $relative;
                continue;
            }
            $hash = hash_file('sha256', $item->getPathname());
            if ($hash === false) {
                throw new RuntimeException('无法计算 publication tree hash：' . $item->getPathname());
            }
            $hashes[] = 'f' . "\0" . $relative . "\0" . $hash;
        }
        sort($hashes, SORT_STRING);
        return hash('sha256', implode("\n", $hashes));
    }

    private function files(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('原生 App 目录禁止符号链接：' . $item->getPathname());
            }
            if ($item->isFile()) {
                $files[] = $item->getPathname();
            }
        }
        sort($files, SORT_STRING);
        return $files;
    }

    private function assertSafeSourceTree(string $directory, string $root): void
    {
        $realRoot = realpath($root);
        $realDirectory = realpath($directory);
        if ($realRoot === false || $realDirectory === false || !str_starts_with($realDirectory, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('原生 App 源路径越界：' . $directory);
        }
        $this->files($realDirectory);
    }

    private function unitTarget(string $unit, string $pluginCode): string
    {
        if ($unit === 'app:' . $pluginCode) {
            return $this->appDirectory() . DIRECTORY_SEPARATOR . $pluginCode;
        }
        if (preg_match('/^console:([a-z]+):' . preg_quote($pluginCode, '/') . '$/', $unit, $match) === 1
            && in_array($match[1], self::CONSOLE_LAYERS, true)) {
            return $this->appDirectory() . DIRECTORY_SEPARATOR . 'console' . DIRECTORY_SEPARATOR . $match[1]
                . DIRECTORY_SEPARATOR . 'plugin' . DIRECTORY_SEPARATOR . $pluginCode;
        }
        throw new RuntimeException('registry publication unit 非法：' . $unit);
    }

    private function unitSourcePath(string $unit, string $relative): string
    {
        $parts = explode(':', $unit);
        if ($parts[0] === 'app' && count($parts) === 2) {
            return 'app/' . $parts[1] . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }
        if ($parts[0] === 'console' && count($parts) === 3 && in_array($parts[1], self::CONSOLE_LAYERS, true)) {
            return 'app/console/' . $parts[1] . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }
        throw new RuntimeException('registry publication unit 非法：' . $unit);
    }

    private function ownedRecords(string $pluginCode, ?array $records = null): array
    {
        return array_values(array_filter(
            $records ?? $this->repository->all(),
            static fn (array $row): bool => ($row['plugin_code'] ?? '') === $pluginCode
                && ($row['resource_type'] ?? '') === self::RESOURCE_TYPE
        ));
    }

    private function recordsByUnit(array $records): array
    {
        $grouped = [];
        foreach ($records as $record) {
            $unit = (string) ($record['publication_unit'] ?? '');
            if ($unit === '') {
                throw new RuntimeException('原生 App registry 缺少 publication_unit');
            }
            $grouped[$unit][] = $record;
        }
        ksort($grouped, SORT_STRING);
        return $grouped;
    }

    private function registryTarget(string $targetPath): string
    {
        if (!str_starts_with($targetPath, 'app/')) {
            throw new RuntimeException('原生 App registry 目标越界：' . $targetPath);
        }
        $target = $this->rootDirectory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetPath);
        $this->assertNoTargetSymlink($target);
        return $target;
    }

    private function targetRegistryPath(string $target): string
    {
        $root = $this->rootDirectory();
        if ($target !== $root && !str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('原生 App 目标路径越界：' . $target);
        }
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($target, strlen($root) + 1));
    }

    private function relativePath(string $root, string $path): string
    {
        if (!str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('publication 文件路径越界：' . $path);
        }
        return substr($path, strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
    }

    private function assertNoTargetSymlink(string $target): void
    {
        $root = $this->rootDirectory();
        if ($target !== $root && !str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('原生 App 目标路径越界');
        }
        $current = $root;
        $relative = substr($target, strlen($root) + 1);
        foreach (array_filter(explode(DIRECTORY_SEPARATOR, $relative), 'strlen') as $segment) {
            $current .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($current)) {
                throw new RuntimeException('原生 App 目标路径禁止符号链接：' . $this->targetRegistryPath($current));
            }
        }
    }

    private function backupPath(string $token, string $target): string
    {
        return dirname($target) . DIRECTORY_SEPARATOR . '.publication-backup-' . $token . '-' . basename($target);
    }

    public function hasJournal(string $token): bool
    {
        $this->assertToken($token);
        return is_file($this->journalFile($token));
    }

    private function journalFile(string $token): string
    {
        return $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-journals' . DIRECTORY_SEPARATOR
            . $token . DIRECTORY_SEPARATOR . 'journal.json';
    }

    private function writeJournal(string $token, array $journal): void
    {
        $journal['updated_at'] = date(DATE_ATOM);
        $file = $this->journalFile($token);
        $this->ensureDirectory(dirname($file));
        $temporary = tempnam(dirname($file), 'journal.tmp.');
        if ($temporary === false) {
            throw new RuntimeException('无法创建 publication journal 临时文件');
        }
        try {
            $json = json_encode($journal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary, $json . "\n", LOCK_EX) === false || !rename($temporary, $file)) {
                throw new RuntimeException('publication journal 原子写入失败');
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function cleanupArtifacts(array $journal, bool $cleanupSharedTokenRoot = true): void
    {
        foreach ((array) ($journal['units'] ?? []) as $unit) {
            $this->removeTree((string) ($unit['temp'] ?? ''));
            $this->removeTree((string) ($unit['backup'] ?? ''));
        }
        $token = (string) ($journal['token'] ?? '');
        $this->assertToken($token);
        if ($cleanupSharedTokenRoot) {
            $recoveryRoot = $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication-recovery';
            if (is_link($recoveryRoot)) {
                throw new RuntimeException('publication recovery 根目录禁止符号链接');
            }
            $this->removeTree($recoveryRoot . DIRECTORY_SEPARATOR . $token);
        }
    }

    private function locked(callable $operation): mixed
    {
        $lockFile = $this->runtimeDirectory() . DIRECTORY_SEPARATOR . 'publication.lock';
        $lock = fopen($lockFile, 'c+');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('无法获取插件全局发布锁');
        }
        try {
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function fault(string $point, int $index, string $path): void
    {
        if ($this->faultHook !== null) {
            ($this->faultHook)($point, $index, $path);
        }
    }

    private function directoryEntries(string $directory): array
    {
        $names = array_values(array_diff(scandir($directory) ?: [], ['.', '..']));
        sort($names, SORT_STRING);
        return array_map(
            static fn (string $name): string => $directory . DIRECTORY_SEPARATOR . $name,
            $names
        );
    }

    private function isInterruptedNewSwap(array $unit): bool
    {
        $target = (string) ($unit['target'] ?? '');
        $temp = (string) ($unit['temp'] ?? '');
        $newHash = (string) ($unit['new_tree_hash'] ?? '');
        return $newHash !== '' && !is_dir($temp) && is_dir($target)
            && hash_equals($newHash, $this->treeHash($target));
    }

    private function removeTree(string $directory): void
    {
        if ($directory === '' || (!is_dir($directory) && !is_link($directory))) {
            return;
        }
        if (is_link($directory)) {
            throw new RuntimeException('拒绝删除符号链接 publication 路径：' . $directory);
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) {
                throw new RuntimeException('拒绝删除包含符号链接的 publication unit：' . $item->getPathname());
            }
            $ok = $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            if (!$ok) {
                throw new RuntimeException('无法清理 publication 路径：' . $item->getPathname());
            }
        }
        if (!rmdir($directory)) {
            throw new RuntimeException('无法清理 publication 目录：' . $directory);
        }
    }

    private function removeEmptyParents(string $directory, string $stop): void
    {
        while ($directory !== $stop && is_dir($directory) && (scandir($directory) ?: []) === ['.', '..']) {
            if (!rmdir($directory)) {
                return;
            }
            $directory = dirname($directory);
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建 publication 目录：' . $directory);
        }
    }

    private function rootDirectory(): string
    {
        $root = dirname($this->appDirectory());
        $real = realpath($root);
        if ($real === false) {
            throw new RuntimeException('无法解析项目根目录');
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function appDirectory(): string
    {
        $this->ensureDirectory($this->appRoot);
        $real = realpath($this->appRoot);
        if ($real === false) {
            throw new RuntimeException('无法解析 App 根目录');
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function runtimeDirectory(): string
    {
        $this->ensureDirectory($this->runtimeRoot);
        $real = realpath($this->runtimeRoot);
        if ($real === false) {
            throw new RuntimeException('无法解析 publication runtime 目录');
        }
        return rtrim($real, DIRECTORY_SEPARATOR);
    }

    private function unitSlug(string $unit): string
    {
        return str_replace(':', '-', $unit);
    }

    private function emptyRecoverySteps(): array
    {
        return [
            'files_restored' => false,
            'files_cleaned' => false,
            'native_restored' => false,
            'registry_restored' => false,
            'artifacts_cleaned' => false,
        ];
    }

    private function emptyFinalizationSteps(): array
    {
        return [
            'files_cleaned' => false,
            'native_cleaned' => false,
            'shared_recovery_cleaned' => false,
        ];
    }

    private function assertPluginCode(string $code): void
    {
        if (preg_match('/^[a-z][a-z0-9]*$/', $code) !== 1) {
            throw new RuntimeException('插件标识不合法');
        }
    }

    private function assertToken(string $token): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $token) !== 1) {
            throw new RuntimeException('publication operation token 不合法');
        }
    }

    private function assertNewToken(string $token): void
    {
        if (is_file($this->journalFile($token))) {
            throw new RuntimeException('publication operation token 已存在：' . $token);
        }
    }
}
