<?php

declare(strict_types=1);

namespace app\console\ai\service;

use app\console\ai\contract\DockerProcessRunner;
use app\console\ai\infrastructure\ProcessResult;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** 创建隔离工作副本并管理最小权限 Docker 容器。 */
final class AgentSandboxManager
{
    private const LABEL = 'com.funadmin.ai-agent=true';
    private const EXCLUDED_ROOTS = ['.git', '.env', '.env.local', '.env.production', 'runtime'];

    public function __construct(
        private readonly DockerProcessRunner $runner,
        private readonly string $projectRoot,
        private readonly string $privateRoot,
        private readonly array $config
    ) {
    }

    public function create(int $taskId, int $sessionId = 0): array
    {
        $image = trim((string) ($this->config['image'] ?? ''));
        if (preg_match('/^[^\s]+@sha256:[a-f0-9]{64}$/', $image) !== 1) {
            throw new RuntimeException('Docker sandbox image 必须固定为 digest');
        }
        $this->assertDockerAvailable();
        $workspace = rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/sandboxes/' . $taskId . '-' . bin2hex(random_bytes(6));
        $snapshot = $workspace . '/snapshot';
        $this->copyProject($snapshot);
        $this->writeManifest($snapshot, $workspace . '/baseline-manifest.json');
        $volume = 'funadmin-ai-' . $taskId . '-' . bin2hex(random_bytes(6));
        $argv = [
            'docker', 'create', '--label', self::LABEL, '--label', 'com.funadmin.ai-task=' . $taskId,
            '--label', 'com.funadmin.ai-session=' . $sessionId, '--label', 'com.funadmin.ai-volume=' . $volume,
            '--read-only', '--tmpfs', '/tmp:rw,noexec,nosuid,nodev,size=64m', '--tmpfs', '/run:rw,noexec,nosuid,nodev,size=16m',
            '--security-opt', 'no-new-privileges', '--cap-drop', 'ALL', '--cpus', (string) ($this->config['cpu'] ?? 0.5),
            '--memory', (int) ($this->config['memory_mb'] ?? 256) . 'm', '--pids-limit', (string) ($this->config['pids'] ?? 64),
            '--network', 'none', '--user', '10001:10001', '--workdir', '/workspace',
            '--mount', 'type=volume,src=' . $volume . ',dst=/workspace', $image, 'sleep', 'infinity',
        ];
        $result = $this->checked($argv, 30, '创建 Docker sandbox 失败');
        $containerId = trim($result->stdout);
        $this->checked(['docker', 'cp', $snapshot . '/.', $this->containerId($containerId) . ':/workspace'], 120, '复制可信快照失败');
        $this->checked(['docker', 'run', '--rm', '--network', 'none', '--volumes-from', $this->containerId($containerId), '--user', '0:0', $image, 'chown', '-R', '10001:10001', '/workspace'], 120, '修正 sandbox workspace 所有权失败');
        return ['containerId' => $containerId, 'volume' => $volume, 'workspace' => $workspace, 'status' => 'created', 'taskId' => $taskId, 'sessionId' => $sessionId];
    }

    public function start(string $containerId): void
    {
        $id = $this->containerId($containerId);
        $this->checked(['docker', 'start', $id], 30, '启动 Docker sandbox 失败');
        foreach ([
            ['git', 'init'], ['git', 'config', 'user.name', 'FunAdmin AI Sandbox'],
            ['git', 'config', 'user.email', 'sandbox@invalid.local'], ['git', 'add', '--all'],
            ['git', 'commit', '--no-gpg-sign', '-m', 'sandbox baseline'],
        ] as $argv) {
            $this->checked(array_merge(['docker', 'exec', $id], $argv), 60, '初始化 sandbox baseline 失败');
        }
    }

    public function exec(string $containerId, array $argv, int $timeoutSeconds): ProcessResult
    {
        if ($argv === [] || array_filter($argv, static fn (mixed $arg): bool => !is_string($arg)) !== []) {
            throw new RuntimeException('容器命令必须是非空字符串 argv');
        }
        return $this->runner->run(array_merge(['docker', 'exec', $this->containerId($containerId)], $argv), $timeoutSeconds);
    }

    public function export(string $containerId, string $destination): void
    {
        if (!is_dir($destination) && !mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new RuntimeException('无法创建 sandbox 导出目录');
        }
        $this->checked(['docker', 'cp', $this->containerId($containerId) . ':/workspace/.', $destination], 60, '导出 Docker sandbox 失败');
    }

    public function exportChanges(string $containerId, string $workspace): array
    {
        $id = $this->containerId($containerId);
        $exportRoot = rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/exports/' . basename($workspace);
        $tree = $exportRoot . '/tree';
        if (!is_dir($tree) && !mkdir($tree, 0700, true) && !is_dir($tree)) throw new RuntimeException('无法创建变更导出目录');
        $this->checked(['docker', 'exec', $id, 'git', 'add', '-N', '--all'], 60, '准备 sandbox patch 失败');
        $patch = $this->checked(['docker', 'exec', $id, 'git', 'diff', '--binary', '--no-ext-diff', 'HEAD'], 60, '导出 sandbox patch 失败')->stdout;
        file_put_contents($exportRoot . '/changes.patch', $patch, LOCK_EX);
        $this->checked(['docker', 'cp', $id . ':/workspace/.', $tree], 120, '导出 sandbox bundle 失败');
        $this->deleteExportTree($tree . '/.git');
        $remoteManifest = $this->manifest($tree);
        file_put_contents($exportRoot . '/manifest.json', json_encode($remoteManifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
        $baselinePath = rtrim($workspace, DIRECTORY_SEPARATOR) . '/baseline-manifest.json';
        $baselineManifest = is_file($baselinePath)
            ? json_decode((string) file_get_contents($baselinePath), true, 512, JSON_THROW_ON_ERROR)
            : ['algorithm' => 'sha256', 'files' => [], 'digest' => hash('sha256', '{}')];
        $baselineBundlePath = $exportRoot . '/baseline-bundle.json';
        $baselineBundle = $this->bundle(rtrim($workspace, DIRECTORY_SEPARATOR) . '/snapshot', $baselineManifest['files'] ?? []);
        if (file_put_contents($baselineBundlePath, json_encode(['encoding'=>'base64','files'=>$baselineBundle], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('保存 baseline bundle 失败');
        }
        chmod($baselineBundlePath, 0600);
        $exportedBaselinePath = $exportRoot . '/baseline-manifest.json';
        if (file_put_contents($exportedBaselinePath, json_encode($baselineManifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('保存 baseline manifest 失败');
        }
        chmod($exportedBaselinePath, 0600);
        $bundle = [];
        foreach (array_keys($remoteManifest['files']) as $relative) {
            $content = file_get_contents($tree . '/' . $relative);
            if ($content !== false) $bundle[$relative] = base64_encode($content);
        }
        $bundlePath = $exportRoot . '/bundle.json';
        file_put_contents($bundlePath, json_encode(['encoding'=>'base64','files'=>$bundle], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
        $remoteManifestSha256 = hash_file('sha256', $exportRoot . '/manifest.json');
        return ['root'=>$exportRoot, 'patchPath'=>$exportRoot.'/changes.patch', 'patchSha256'=>hash_file('sha256', $exportRoot.'/changes.patch'),
            'bundlePath'=>$bundlePath, 'bundleSha256'=>hash_file('sha256', $bundlePath), 'manifestPath'=>$exportRoot.'/manifest.json',
            'manifestSha256'=>$remoteManifestSha256, 'manifest'=>$remoteManifest, 'remoteManifest'=>$remoteManifest,
            'remoteManifestSha256'=>$remoteManifestSha256, 'baselineManifest'=>$baselineManifest, 'baselineDigest'=>(string)($baselineManifest['digest'] ?? ''),
            'baselineManifestPath'=>$exportedBaselinePath, 'baselineManifestSha256'=>hash_file('sha256', $exportedBaselinePath),
            'baselineBundlePath'=>$baselineBundlePath, 'baselineBundleSha256'=>hash_file('sha256', $baselineBundlePath)];
    }

    public function cleanup(string $containerId, string $workspace, int $taskId, int $sessionId, string $checkpointVolume = ''): void
    {
        $id = $this->containerId($containerId);
        $labelsResult = $this->runner->run(['docker', 'inspect', '--format', '{{json .Config.Labels}}', $id], 15);
        $containerMissing = $labelsResult->exitCode !== 0 && $this->isNotFound($labelsResult);
        if ($labelsResult->exitCode !== 0 && !$containerMissing) {
            throw new RuntimeException('无法校验 sandbox 标签: ' . trim($labelsResult->stderr));
        }
        $volume = $checkpointVolume;
        if (!$containerMissing) {
            $labels = json_decode(trim($labelsResult->stdout), true);
            if (!is_array($labels) || ($labels['com.funadmin.ai-agent'] ?? '') !== 'true'
                || ($labels['com.funadmin.ai-task'] ?? '') !== (string)$taskId || ($labels['com.funadmin.ai-session'] ?? '') !== (string)$sessionId) {
                throw new RuntimeException('sandbox 标签与任务或会话不匹配');
            }
            $volume = (string)($labels['com.funadmin.ai-volume'] ?? $volume);
            $this->checkedUnlessMissing(['docker', 'rm', '-f', $id], 30, '删除 Docker sandbox 失败');
        }
        if ($volume !== '') $this->checkedUnlessMissing(['docker', 'volume', 'rm', $volume], 30, '删除 sandbox volume 失败');
        $this->deleteTree($workspace);
    }

    public function cleanupOrphans(
        array $tasks = [],
        int $retentionSeconds = 86400,
        int $heartbeatTimeoutSeconds = 300,
        ?callable $updateTask = null,
        ?callable $audit = null,
        ?int $now = null,
        ?callable $claim = null,
        ?string $leaseOwner = null,
        int $leaseSeconds = 300
    ): int {
        $now ??= time();
        $leaseOwner ??= bin2hex(random_bytes(16));
        $cleaned = 0;
        foreach ($tasks as $task) {
            $status = (string) ($task['status'] ?? '');
            if (!in_array($status, ['failed', 'cancelled', 'completed', 'succeeded'], true)) continue;
            $completedAt = strtotime((string) ($task['completed_at'] ?? '')) ?: 0;
            $heartbeatAt = strtotime((string) ($task['heartbeat_at'] ?? '')) ?: 0;
            $expired = $completedAt > 0 && $completedAt <= $now - $retentionSeconds;
            $staleHeartbeat = $heartbeatAt > 0 && $heartbeatAt <= $now - $heartbeatTimeoutSeconds;
            if (!$expired || (!(bool) ($task['sandbox_retained'] ?? false) && !$staleHeartbeat)) continue;
            $containerId = (string) ($task['container_task_id'] ?? '');
            $workspace = (string) ($task['workspace_path'] ?? '');
            if ($containerId === '' || $workspace === '') continue;
            if ($claim === null || !$claim($task, $leaseOwner, $now + $leaseSeconds)) continue;
            try {
                $this->cleanup($containerId, $workspace, (int) $task['id'], (int) ($task['conversation_id'] ?? 0), (string)($task['sandbox_volume'] ?? ''));
                $data = ['sandbox_status' => 'cleaned', 'sandbox_retained' => 0, 'cleanup_at' => date('Y-m-d H:i:s', $now)];
                $updated = $updateTask === null || $updateTask((int) $task['id'], $data, $leaseOwner) !== false;
                if (!$updated) continue;
                if ($audit !== null) $audit('ai.sandbox.cleanup', ['task_id' => (int) $task['id'], 'status' => $status]);
                $cleaned++;
            } catch (\Throwable $exception) {
                $data = ['sandbox_status' => 'failed', 'sandbox_retained' => 1, 'recovery_status' => 'recovery_required'];
                $updated = $updateTask === null || $updateTask((int) $task['id'], $data, $leaseOwner) !== false;
                if ($updated && $audit !== null) $audit('ai.sandbox.cleanup_failed', ['task_id' => (int) $task['id'], 'status' => $status, 'error' => $exception->getMessage()]);
            }
        }
        return $cleaned;
    }

    public function status(?string $containerId = null): array
    {
        $argv = $containerId === null ? ['docker', 'info', '--format', '{{json .ServerVersion}}'] : ['docker', 'inspect', '--format', '{{json .State}}', $this->containerId($containerId)];
        $result = $this->checked($argv, 15, 'Docker sandbox 状态不可用');
        return ['available' => true, 'containerId' => $containerId, 'details' => trim($result->stdout)];
    }

    private function assertDockerAvailable(): void
    {
        try {
            $result = $this->runner->run(['docker', 'version', '--format', '{{.Server.Version}}'], 10);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Docker 不可用，sandbox fail-closed', 0, $exception);
        }
        if ($result->exitCode !== 0) {
            throw new RuntimeException('Docker 不可用，sandbox fail-closed');
        }
    }

    private function checked(array $argv, int $timeout, string $message): ProcessResult
    {
        $result = $this->runner->run($argv, $timeout);
        if ($result->exitCode !== 0) {
            throw new RuntimeException($message . ': ' . trim($result->stderr));
        }
        return $result;
    }

    private function checkedUnlessMissing(array $argv, int $timeout, string $message): void
    {
        $result = $this->runner->run($argv, $timeout);
        if ($result->exitCode !== 0 && !$this->isNotFound($result)) {
            throw new RuntimeException($message . ': ' . trim($result->stderr));
        }
    }

    private function isNotFound(ProcessResult $result): bool
    {
        return preg_match('/(?:no such|not found)/i', $result->stderr . ' ' . $result->stdout) === 1;
    }

    private function containerId(string $id): string
    {
        if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$/', $id) !== 1) {
            throw new RuntimeException('非法容器 ID');
        }
        return $id;
    }

    private function copyProject(string $destination): void
    {
        if (!mkdir($destination, 0700, true) && !is_dir($destination)) {
            throw new RuntimeException('无法创建 sandbox 工作副本');
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->projectRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen(rtrim($this->projectRoot, DIRECTORY_SEPARATOR)) + 1);
            if ($this->excluded($relative) || $item->isLink()) continue;
            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0700, true);
            } else {
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
                if (!copy($item->getPathname(), $target)) throw new RuntimeException('复制可信快照失败');
            }
        }
    }

    private function writeManifest(string $root, string $path): void
    {
        $json = json_encode($this->manifest($root), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (file_put_contents($path, $json, LOCK_EX) === false) throw new RuntimeException('保存 baseline manifest 失败');
        chmod($path, 0600);
    }

    private function bundle(string $root, array $files): array
    {
        $bundle = [];
        foreach (array_keys($files) as $relative) {
            if (!is_string($relative) || $this->excluded($relative)) continue;
            $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_file($path) || is_link($path)) throw new RuntimeException('baseline bundle 文件缺失：' . $relative);
            $content = file_get_contents($path);
            if (!is_string($content)) throw new RuntimeException('无法读取 baseline bundle：' . $relative);
            $bundle[$relative] = base64_encode($content);
        }
        ksort($bundle, SORT_STRING);
        return $bundle;
    }

    private function manifest(string $root): array
    {
        $files = [];
        if (is_dir($root)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->isLink()) continue;
                $relative = str_replace('\\', '/', substr($item->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1));
                $files[$relative] = ['sha256'=>hash_file('sha256', $item->getPathname()), 'size'=>$item->getSize()];
            }
        }
        ksort($files);
        return ['algorithm'=>'sha256', 'files'=>$files, 'digest'=>hash('sha256', json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
    }

    private function excluded(string $relative): bool
    {
        $normalized = str_replace('\\', '/', $relative);
        $root = explode('/', $normalized)[0];
        return in_array($root, self::EXCLUDED_ROOTS, true)
            || preg_match('#(^|/)\.env(?:\.|$)#', $normalized) === 1
            || preg_match('#(^|/)(?:id_rsa|id_ed25519|credentials|known_hosts)(?:$|/)#i', $normalized) === 1;
    }

    private function deleteTree(string $path): void
    {
        $this->deleteWithin($path, rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/sandboxes', 'sandbox');
    }

    private function deleteExportTree(string $path): void
    {
        $this->deleteWithin($path, rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/exports', 'export');
    }

    private function deleteWithin(string $path, string $allowedRoot, string $kind): void
    {
        if (!is_dir($path)) return;
        $root = realpath($allowedRoot);
        $real = realpath($path);
        if ($root === false || $real === false || !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('拒绝删除 ' . $kind . ' root 外路径');
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($real, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($real);
    }
}
