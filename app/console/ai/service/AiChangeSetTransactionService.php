<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\common\crud\PathGuard;
use app\console\ai\exception\AiChangeSetInterruptionException;
use Closure;
use RuntimeException;
use Throwable;

/** 项目级锁与私有 WAL 协调 ChangeSet 的逐文件安全提交。 */
final class AiChangeSetTransactionService
{
    private readonly ?Closure $faultHook;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $privateRoot,
        ?callable $faultHook = null
    ) {
        $this->faultHook = $faultHook === null ? null : Closure::fromCallable($faultHook);
    }

    public function execute(int $changeSetId, int $adminId, int $conversationId, int $taskId, array $plan, array $approval): array
    {
        if (($approval['status'] ?? '') !== 'approved'
            || ($approval['operation'] ?? '') !== 'apply_workspace'
            || (int) ($approval['requested_by'] ?? 0) !== $adminId
            || (int) ($approval['decided_by'] ?? 0) !== $adminId
            || (int) ($approval['conversation_id'] ?? 0) !== $conversationId
            || (int) ($approval['task_id'] ?? 0) !== $taskId) {
            throw new RuntimeException('最终审批无效或不可绕过', 403);
        }
        $lockPath = rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/change-set.lock';
        $walRoot = rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/wal';
        if (!is_dir($walRoot) && !mkdir($walRoot, 0700, true) && !is_dir($walRoot)) throw new RuntimeException('无法创建 ChangeSet WAL');
        $handle = fopen($lockPath, 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) throw new RuntimeException('ChangeSet 项目锁已被占用', 409);
        $transactionId = bin2hex(random_bytes(16));
        $journal = ['schema_version'=>1,'version'=>0,'transaction_id'=>$transactionId,'change_set_id'=>$changeSetId,'admin_id'=>$adminId,'conversation_id'=>$conversationId,'task_id'=>$taskId,
            'state'=>'prepared','history'=>['prepared'],'files'=>[],'applied'=>[],'updated_at'=>date(DATE_ATOM)];
        $walPath = $walRoot . '/' . $transactionId . '.json';
        $this->write($walPath, $journal);
        try {
            $this->fault('after_prepared');
            foreach ((array) ($plan['files'] ?? []) as $file) {
                $path = (string) ($file['path'] ?? '');
                $target = PathGuard::resolve($this->projectRoot, $path, 'ChangeSet');
                $before = is_file($target) ? hash_file('sha256', $target) : null;
                if ($before !== ($file['localHash'] ?? null)) throw new RuntimeException('Local hash 已变化，必须重新 preview', 409);
                $entry = ['path'=>$path,'base_hash'=>$file['baseHash'] ?? null,'local_hash'=>$before,'remote_hash'=>$file['remoteHash'] ?? null,'status'=>$file['status'] ?? '', 'target_hash'=>$file['mergedHash'] ?? null, 'backup_path'=>null, 'write_hash'=>null, 'write_state'=>'planned'];
                if ($before !== null) {
                    $backupPath = $walRoot . '/' . $transactionId . '-' . hash('sha256', $path) . '.bak';
                    if (!copy($target, $backupPath)) throw new RuntimeException('备份文件失败：' . $path);
                    $entry['backup_path'] = $backupPath;
                }
                $journal['files'][] = $entry;
            }
            $this->checkpoint($walPath, $journal, 'staged');
            $this->fault('after_staged');
            foreach ((array) ($plan['files'] ?? []) as $index => $file) {
                $path = (string) $file['path'];
                $target = PathGuard::resolve($this->projectRoot, $path, 'ChangeSet');
                $content = $file['content'] ?? null;
                $journal['files'][$index]['write_state'] = 'committing';
                $this->write($walPath, $journal);
                if (($file['status'] ?? '') === 'delete') { if (is_file($target) && !unlink($target)) throw new RuntimeException('删除文件失败：' . $path); }
                elseif (!is_string($content)) throw new RuntimeException('ChangeSet 文件内容不可用：' . $path);
                else {
                    $directory = dirname($target);
                    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) throw new RuntimeException('无法创建目标目录');
                    $temporary = $target . '.ai-' . $transactionId . '.tmp';
                    if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $target)) throw new RuntimeException('原子写入失败：' . $path);
                }
                $this->fault('after_file_rename');
                $journal['files'][$index]['write_hash'] = is_file($target) ? hash_file('sha256', $target) : null;
                $journal['files'][$index]['write_state'] = 'written';
                $journal['applied'][] = $path;
                $this->checkpoint($walPath, $journal, 'writing');
            }
            foreach ((array) ($plan['files'] ?? []) as $file) {
                $target = PathGuard::resolve($this->projectRoot, (string)$file['path'], 'ChangeSet');
                $actual = is_file($target) ? hash_file('sha256', $target) : null;
                $expected = ($file['status'] ?? '') === 'delete' ? null : ($file['mergedHash'] ?? null);
                if ($actual !== $expected) throw new RuntimeException('写入后 hash 验证失败');
            }
            $this->checkpoint($walPath, $journal, 'verified');
            $this->fault('after_verified');
            $journal['state'] = 'completed'; $journal['history'][] = 'completed'; $this->cleanupBackups($journal); $this->write($walPath, $journal);
            return ['state'=>'completed','transactionId'=>$transactionId,'written'=>count($journal['applied'])];
        } catch (AiChangeSetInterruptionException $exception) {
            $journal['state'] = 'recovery_required'; $journal['history'][] = 'recovery_required'; $this->write($walPath, $journal);
            throw $exception;
        } catch (Throwable $exception) {
            $this->rollback($walPath, $journal);
            throw $exception;
        } finally { flock($handle, LOCK_UN); fclose($handle); }
    }

    public function recover(string $transactionId): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $transactionId)) throw new RuntimeException('transaction id 不合法');
        $lockPath = rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/change-set.lock';
        $handle = fopen($lockPath, 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) throw new RuntimeException('ChangeSet 项目锁已被占用', 409);
        try {
            return $this->recoverLocked($transactionId);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function recoverLocked(string $transactionId): array
    {
        $walPath = rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/wal/' . $transactionId . '.json';
        if (!is_file($walPath) || is_link($walPath)) throw new RuntimeException('ChangeSet WAL 不存在');
        $journal = json_decode((string) file_get_contents($walPath), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($journal) || ($journal['transaction_id'] ?? '') !== $transactionId) throw new RuntimeException('ChangeSet WAL 内容无效');
        if (($journal['state'] ?? '') === 'rolled_back') return $journal;
        if (($journal['state'] ?? '') === 'completed') return $journal;
        $allTargetsMatch = true;
        foreach ((array) ($journal['files'] ?? []) as $file) {
            $target = PathGuard::resolve($this->projectRoot, (string) ($file['path'] ?? ''), 'ChangeSet');
            $actual = is_file($target) ? hash_file('sha256', $target) : null;
            $writeHash = $file['write_hash'] ?? null;
            $targetHash = $file['target_hash'] ?? null;
            $localHash = $file['local_hash'] ?? null;
            if ($actual !== $targetHash) $allTargetsMatch = false;
            if ($actual !== $localHash && $actual !== $writeHash && $actual !== $targetHash) {
                $journal['state'] = 'recovery_required'; $journal['history'][] = 'recovery_required'; $this->write($walPath, $journal);
                throw new RuntimeException('无法证明文件状态，事务需要人工恢复');
            }
        }
        $verified = in_array('verified', (array) ($journal['history'] ?? []), true);
        if ($verified && $allTargetsMatch) {
            $journal['state'] = 'completed'; $journal['history'][] = 'completed'; $this->cleanupBackups($journal); $this->write($walPath, $journal);
            return $journal;
        }
        $journal['state'] = 'rolling_back'; $journal['history'][] = 'rolling_back'; $this->write($walPath, $journal);
        foreach (array_reverse((array) ($journal['files'] ?? [])) as $file) {
            $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], 'ChangeSet');
            $backup = (string) ($file['backup_path'] ?? '');
            if ($backup !== '' && is_file($backup)) {
                if (!rename($backup, $target)) throw new RuntimeException('恢复备份失败：' . $file['path']);
            } elseif (is_file($target) && !unlink($target)) {
                throw new RuntimeException('删除新文件失败：' . $file['path']);
            }
        }
        $journal['state'] = 'rolled_back'; $journal['history'][] = 'rolled_back'; $this->write($walPath, $journal);
        return $journal;
    }

    private function cleanupBackups(array $journal): void
    {
        foreach ((array) ($journal['files'] ?? []) as $file) {
            $backup = (string) ($file['backup_path'] ?? '');
            if ($backup !== '' && is_file($backup) && !unlink($backup)) throw new RuntimeException('清理事务备份失败');
        }
    }

    private function rollback(string $walPath, array &$journal): void
    {
        $journal['state'] = 'rolling_back'; $journal['history'][] = 'rolling_back'; $this->write($walPath, $journal);
        try {
            foreach (array_reverse((array) ($journal['files'] ?? [])) as $file) {
                $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], 'ChangeSet');
                $backup = (string) ($file['backup_path'] ?? '');
                if ($backup !== '' && is_file($backup)) {
                    if (!rename($backup, $target)) throw new RuntimeException('恢复备份失败：' . $file['path']);
                } elseif (($file['local_hash'] ?? null) === null && is_file($target) && !unlink($target)) {
                    throw new RuntimeException('删除新文件失败：' . $file['path']);
                }
            }
            $journal['state'] = 'rolled_back'; $journal['history'][] = 'rolled_back'; $this->write($walPath, $journal);
        } catch (Throwable $exception) {
            $journal['state'] = 'recovery_required'; $journal['history'][] = 'recovery_required'; $this->write($walPath, $journal);
            throw new RuntimeException('ChangeSet 回滚失败：' . $exception->getMessage(), 0, $exception);
        }
    }

    private function fault(string $stage): void
    {
        if ($this->faultHook !== null) ($this->faultHook)($stage);
    }

    private function checkpoint(string $path, array &$journal, string $state): void
    {
        $journal['state'] = $state; $journal['history'][] = $state; $this->write($path, $journal);
    }

    private function write(string $path, array &$journal): void
    {
        $journal['version'] = (int) ($journal['version'] ?? 0) + 1;
        $journal['updated_at'] = date(DATE_ATOM);
        $temporary = $path . '.tmp';
        $encoded = json_encode($journal, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($temporary, $encoded, LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('无法持久化 ChangeSet WAL');
        }
    }
}
