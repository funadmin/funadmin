<?php

declare(strict_types=1);

namespace app\common\crud;

use Closure;
use RuntimeException;
use Throwable;

/**
 * 在项目级排他锁内执行计划重校验、原子替换和失败回滚。
 */
final class AtomicWriter
{
    /** @var null|Closure(array): void */
    private readonly ?Closure $afterWrite;

    /** @var null|Closure(array): void */
    private readonly ?Closure $beforeReplace;

    private readonly ConfirmationToken $tokens;

    public function __construct(
        private readonly string $projectRoot,
        ?callable $afterWrite = null,
        ?ConfirmationToken $tokens = null,
        ?callable $beforeReplace = null
    ) {
        $this->afterWrite = $afterWrite === null ? null : Closure::fromCallable($afterWrite);
        $this->tokens = $tokens ?? new ConfirmationToken($projectRoot);
        $this->beforeReplace = $beforeReplace === null ? null : Closure::fromCallable($beforeReplace);
    }

    public function write(array $plan, string $confirmToken, array $allowOverwrite = []): array
    {
        $lock = $this->acquireLock();
        try {
            return $this->writeLocked($plan, $confirmToken, $allowOverwrite);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function writeLocked(array $plan, string $confirmToken, array $allowOverwrite): array
    {
        $planDigest = ConfirmationToken::planDigest($plan);
        if (!isset($plan['planDigest']) || !hash_equals((string) $plan['planDigest'], $planDigest)) {
            throw new RuntimeException('生成计划 planDigest 不匹配');
        }
        $claims = $this->tokens->verify($confirmToken, $planDigest);
        $allowed = array_fill_keys(array_map(
            static fn (string $path): string => str_replace('\\', '/', $path),
            $allowOverwrite
        ), true);
        $files = $plan['files'] ?? [];
        if (!is_array($files)) {
            throw new RuntimeException('生成计划文件列表不合法');
        }
        $plannedPaths = array_column($files, 'path');
        $unknownAllowed = array_diff(array_keys($allowed), $plannedPaths);
        if ($unknownAllowed !== []) {
            throw new RuntimeException('allowOverwrite 包含计划外路径：' . implode(', ', $unknownAllowed));
        }
        $this->preflight($files, $allowed);
        $this->tokens->consume((string) $claims['nonce'], (int) $claims['expiresAt']);

        $temporary = rtrim($this->projectRoot, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . '.crud-write-' . bin2hex(random_bytes(8));
        $backup = $temporary . DIRECTORY_SEPARATOR . 'backup';
        $staged = $temporary . DIRECTORY_SEPARATOR . 'staged';
        $this->createDirectory($backup, 0700);
        $this->createDirectory($staged, 0700);
        $applied = [];
        $createdDirectories = [];
        $safeCommit = new SafeCommit($this->projectRoot);
        $commitRecoveryError = null;
        try {
            foreach ($files as $file) {
                if (($file['status'] ?? '') === 'unchanged') {
                    continue;
                }
                $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], '项目目录');
                $stage = PathGuard::resolve($staged, (string) $file['path'], '临时目录');
                $backupFile = PathGuard::resolve($backup, (string) $file['path'], '临时目录');
                $this->ensureDirectory(dirname($stage));
                if (file_put_contents($stage, (string) $file['content'], LOCK_EX) === false
                    || !hash_equals((string) $file['hash'], (string) hash_file('sha256', $stage))) {
                    throw new RuntimeException('临时文件 hash 校验失败：' . $file['path']);
                }
                if ($this->beforeReplace !== null) {
                    ($this->beforeReplace)($file);
                }
                $target = PathGuard::resolve($this->projectRoot, (string) $file['path'], '项目目录');
                $this->assertCurrentTarget($target, $file);
                $previousHash = isset($file['previousHash']) && is_string($file['previousHash'])
                    ? $file['previousHash']
                    : null;
                $existed = $previousHash !== null;
                if ($existed) {
                    $this->ensureDirectory(dirname($backupFile));
                }
                $this->ensureTargetDirectory(dirname($target), $createdDirectories);
                $this->assertCurrentTarget($target, $file);
                $commit = $safeCommit->commit($stage, $target, $backupFile, $previousHash, (string) $file['hash']);
                if (($commit['renamed'] ?? false) === true) {
                    $applied[] = [
                        'target' => $target,
                        'backup' => $backupFile,
                        'existed' => $existed,
                        'previousHash' => $previousHash,
                        'newHash' => (string) $file['hash'],
                        'targetParent' => (string) ($commit['target_parent'] ?? ''),
                    ];
                }
                if (($commit['ok'] ?? false) !== true) {
                    if (in_array((string) ($commit['code'] ?? ''), [
                        'COMMIT_AND_RESTORE_FAILED',
                        'BACKUP_AND_RESTORE_FAILED',
                    ], true)) {
                        $commitRecoveryError = (string) ($commit['message'] ?? '提交失败且旧文件恢复失败');
                    }
                    throw new RuntimeException((string) ($commit['message'] ?? '安全提交失败'));
                }
                if (!isset($commit['hash']) || !hash_equals((string) $file['hash'], (string) $commit['hash'])) {
                    throw new RuntimeException('安全提交 helper 返回 hash 不匹配');
                }
                $verifiedTarget = PathGuard::resolve($this->projectRoot, (string) $file['path'], '项目目录');
                if ($verifiedTarget !== $target
                    || !is_file($verifiedTarget)
                    || !hash_equals((string) $file['hash'], (string) hash_file('sha256', $verifiedTarget))) {
                    throw new RuntimeException('替换后路径或内容 hash 校验失败：' . $file['path']);
                }
                if ($this->afterWrite !== null) {
                    ($this->afterWrite)($file);
                }
            }
        } catch (Throwable $exception) {
            $rollbackErrors = $this->rollback($safeCommit, $applied, $createdDirectories);
            if ($commitRecoveryError !== null) {
                $rollbackErrors[] = $commitRecoveryError;
            }
            if ($rollbackErrors !== []) {
                throw new RuntimeException(
                    $exception->getMessage() . '；回滚失败：' . implode('；', $rollbackErrors)
                    . '；备份保留于：' . $temporary,
                    0,
                    $exception
                );
            }
            $cleanupErrors = $this->removeTree($temporary);
            if ($cleanupErrors !== []) {
                throw new RuntimeException(
                    $exception->getMessage() . '；临时目录清理失败：' . implode('；', $cleanupErrors),
                    0,
                    $exception
                );
            }
            throw $exception;
        }
        $cleanupErrors = $this->removeTree($temporary);
        if ($cleanupErrors !== []) {
            throw new RuntimeException('写入成功但临时目录清理失败：' . implode('；', $cleanupErrors));
        }
        return ['status' => 'written', 'written' => count($applied)];
    }

    private function preflight(array $files, array $allowed): void
    {
        foreach ($files as $file) {
            $target = PathGuard::resolve($this->projectRoot, (string) ($file['path'] ?? ''), '项目目录');
            $this->assertCurrentTarget($target, $file);
            if (($file['status'] ?? '') === 'blocked') {
                throw new RuntimeException('目标路径被非文件对象阻塞：' . $file['path']);
            }
            if (($file['status'] ?? '') === 'conflict' && !isset($allowed[$file['path']])) {
                throw new RuntimeException('冲突文件不在精确 allowOverwrite：' . $file['path']);
            }
            if (isset($allowed[$file['path']]) && ($file['status'] ?? '') !== 'conflict') {
                throw new RuntimeException('allowOverwrite 只能授权冲突文件：' . $file['path']);
            }
            if (!hash_equals((string) ($file['hash'] ?? ''), hash('sha256', (string) ($file['content'] ?? '')))) {
                throw new RuntimeException('计划内容 hash 不匹配：' . $file['path']);
            }
        }
    }

    private function assertCurrentTarget(string $target, array $file): void
    {
        clearstatcache(true, $target);
        $stat = @lstat($target);
        if ($stat !== false && (($stat['mode'] & 0170000) !== 0100000)) {
            throw new RuntimeException('目标路径被非文件对象或符号链接阻塞：' . $file['path']);
        }
        $actualHash = $stat === false ? null : hash_file('sha256', $target);
        if ($actualHash !== ($file['previousHash'] ?? null)) {
            throw new RuntimeException('目标文件 hash 已变化：' . $file['path']);
        }
    }

    private function rollback(SafeCommit $safeCommit, array $applied, array $createdDirectories): array
    {
        $errors = [];
        foreach (array_reverse($applied) as $item) {
            try {
                $result = $item['existed']
                    ? $safeCommit->restore(
                        $item['backup'],
                        $item['target'],
                        $item['newHash'],
                        $item['previousHash'],
                        $item['targetParent']
                    )
                    : $safeCommit->remove($item['target'], $item['newHash'], $item['targetParent']);
                if (($result['ok'] ?? false) !== true) {
                    $errors[] = (string) ($result['message'] ?? '安全回滚失败') . ' ' . $item['target'];
                }
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage() . ' ' . $item['target'];
            }
        }
        if ($errors !== []) {
            return $errors;
        }
        foreach (array_reverse($createdDirectories) as $directory) {
            if (is_dir($directory) && !rmdir($directory)) {
                $errors[] = '无法删除新增目录 ' . $directory;
            }
        }
        return $errors;
    }

    private function acquireLock()
    {
        $directory = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'cache';
        $this->ensureDirectory($directory);
        $handle = @fopen($directory . DIRECTORY_SEPARATOR . 'crud-write.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('无法获取 CRUD 项目级排他锁');
        }
        return $handle;
    }

    private function ensureTargetDirectory(string $directory, array &$createdDirectories): void
    {
        if (is_dir($directory)) {
            return;
        }
        $missing = [];
        $cursor = $directory;
        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR);
        while ($cursor !== $root && !file_exists($cursor) && !is_link($cursor)) {
            $missing[] = $cursor;
            $cursor = dirname($cursor);
        }
        PathGuard::relative($this->projectRoot, $directory . DIRECTORY_SEPARATOR . '.guard');
        $this->ensureDirectory($directory);
        foreach (array_reverse($missing) as $created) {
            $createdDirectories[] = $created;
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建目录：' . $directory);
        }
    }

    private function createDirectory(string $directory, int $permissions): void
    {
        if (!mkdir($directory, $permissions, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建临时目录：' . $directory);
        }
    }

    private function removeTree(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $errors = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $removed = $item->isDir() && !$item->isLink()
                ? rmdir($item->getPathname())
                : unlink($item->getPathname());
            if (!$removed) {
                $errors[] = '无法删除 ' . $item->getPathname();
            }
        }
        if (!rmdir($path)) {
            $errors[] = '无法删除 ' . $path;
        }
        return $errors;
    }
}
