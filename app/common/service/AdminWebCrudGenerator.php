<?php

declare(strict_types=1);

namespace app\common\service;

use InvalidArgumentException;
use RuntimeException;

/**
 * 后台 CRUD 生成器统一执行入口。
 *
 * 仅允许读取项目目录内的 JSON 配置，并通过参数数组启动 Node，避免 shell 拼接。
 */
class AdminWebCrudGenerator
{
    public function run(string $configPath, bool $dryRun = false, bool $force = false): array
    {
        $rootPath = rtrim(root_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $resolvedConfig = $this->resolveConfigPath($rootPath, $configPath);
        $script = $rootPath . 'admin-web' . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'crud-gen.mjs';
        if (!is_file($script)) {
            throw new RuntimeException('后台 CRUD 生成脚本不存在');
        }

        $command = [$this->nodeBinary(), $script];
        if ($dryRun) {
            $command[] = '--dry';
        }
        if ($force) {
            $command[] = '--force';
        }
        $command[] = $resolvedConfig;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $rootPath . 'admin-web',
            null,
            ['bypass_shell' => true]
        );
        if (!is_resource($process)) {
            throw new RuntimeException('无法启动后台 CRUD 生成器');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $result = [
            'success' => $exitCode === 0,
            'exitCode' => $exitCode,
            'config' => $this->relativePath($rootPath, $resolvedConfig),
            'dryRun' => $dryRun,
            'output' => trim((string) $stdout),
            'error' => trim((string) $stderr),
        ];
        if ($exitCode !== 0) {
            throw new RuntimeException($result['error'] ?: $result['output'] ?: '后台 CRUD 生成失败');
        }
        return $result;
    }

    private function resolveConfigPath(string $rootPath, string $configPath): string
    {
        $configPath = trim($configPath);
        if ($configPath === '' || strtolower(pathinfo($configPath, PATHINFO_EXTENSION)) !== 'json') {
            throw new InvalidArgumentException('必须指定项目目录内的 JSON 配置文件');
        }
        $candidate = $this->isAbsolutePath($configPath) ? $configPath : $rootPath . ltrim($configPath, '/\\');
        $resolved = realpath($candidate);
        $resolvedRoot = realpath($rootPath);
        if ($resolved === false || !is_file($resolved)) {
            throw new InvalidArgumentException('CRUD 配置文件不存在');
        }
        if ($resolvedRoot === false || !str_starts_with($resolved . DIRECTORY_SEPARATOR, $resolvedRoot . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('CRUD 配置文件必须位于项目目录内');
        }
        return $resolved;
    }

    private function nodeBinary(): string
    {
        $configured = trim((string) getenv('NODE_BINARY'));
        if ($configured !== '') {
            if ($this->isAbsolutePath($configured) && !is_executable($configured)) {
                throw new RuntimeException('NODE_BINARY 指向的 Node 不可执行');
            }
            return $configured;
        }

        foreach (['/opt/homebrew/bin/node', '/usr/local/bin/node', '/usr/bin/node'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }
        return 'node';
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function relativePath(string $rootPath, string $path): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($rootPath)));
    }
}
