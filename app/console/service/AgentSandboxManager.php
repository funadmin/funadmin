<?php

declare(strict_types=1);

namespace app\console\service;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** 创建隔离工作副本并管理最小权限 Docker 容器。 */
final class AgentSandboxManager
{
    private const LABEL = 'com.funadmin.ai-agent=true';
    private const EXCLUDED_ROOTS = ['.git', '.env', '.env.local', '.env.production'];

    public function __construct(
        private readonly DockerProcessRunner $runner,
        private readonly string $projectRoot,
        private readonly string $privateRoot,
        private readonly array $config
    ) {
    }

    public function create(int $taskId): array
    {
        $image = trim((string) ($this->config['image'] ?? ''));
        if ($image === '') {
            throw new RuntimeException('Docker sandbox image 未配置');
        }
        $this->assertDockerAvailable();
        $workspace = rtrim($this->privateRoot, DIRECTORY_SEPARATOR) . '/sandboxes/' . $taskId . '-' . bin2hex(random_bytes(6));
        $this->copyProject($workspace);
        $argv = [
            'docker', 'create', '--label', self::LABEL, '--label', 'com.funadmin.ai-task=' . $taskId,
            '--read-only', '--tmpfs', '/tmp:rw,noexec,nosuid,nodev,size=64m', '--tmpfs', '/run:rw,noexec,nosuid,nodev,size=16m',
            '--security-opt', 'no-new-privileges', '--cap-drop', 'ALL', '--cpus', (string) ($this->config['cpu'] ?? 0.5),
            '--memory', (int) ($this->config['memory_mb'] ?? 256) . 'm', '--pids-limit', (string) ($this->config['pids'] ?? 64),
            '--network', ($this->config['network_enabled'] ?? false) ? 'bridge' : 'none', '--user', '10001:10001',
            '--workdir', '/workspace', '--mount', 'type=bind,src=' . $workspace . ',dst=/workspace,rw', $image, 'sleep', 'infinity',
        ];
        $result = $this->checked($argv, 30, '创建 Docker sandbox 失败');
        return ['containerId' => trim($result->stdout), 'workspace' => $workspace, 'status' => 'created'];
    }

    public function start(string $containerId): void
    {
        $this->checked(['docker', 'start', $this->containerId($containerId)], 30, '启动 Docker sandbox 失败');
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

    public function cleanup(string $containerId, string $workspace): void
    {
        try {
            $this->runner->run(['docker', 'rm', '-f', $this->containerId($containerId)], 30);
        } finally {
            $this->deleteTree($workspace);
        }
    }

    public function cleanupOrphans(): int
    {
        $result = $this->checked(['docker', 'ps', '-aq', '--filter', 'label=' . self::LABEL], 30, '查询孤儿 sandbox 失败');
        $ids = preg_split('/\s+/', trim($result->stdout), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($ids as $id) {
            $this->runner->run(['docker', 'rm', '-f', $this->containerId($id)], 30);
        }
        return count($ids);
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
            if ($this->excluded($relative) || $item->isLink()) {
                continue;
            }
            $target = $destination . DIRECTORY_SEPARATOR . $relative;
            if ($item->isDir()) {
                if (!is_dir($target)) mkdir($target, 0700, true);
            } else {
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0700, true);
                copy($item->getPathname(), $target);
            }
        }
    }

    private function excluded(string $relative): bool
    {
        $normalized = str_replace('\\', '/', $relative);
        $root = explode('/', $normalized)[0];
        return in_array($root, self::EXCLUDED_ROOTS, true) || $root === 'runtime'
            || preg_match('#(^|/)\.env(?:\.|$)#', $normalized) === 1
            || preg_match('#(^|/)(?:id_rsa|id_ed25519|credentials|known_hosts)(?:$|/)#i', $normalized) === 1;
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) return;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
