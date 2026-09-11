<?php

declare(strict_types=1);

namespace app\console\service;

use RuntimeException;

/** 使用 proc_open 的数组命令模式，完全绕过 shell 解析。 */
final class NativeDockerProcessRunner implements DockerProcessRunner
{
    public function run(array $argv, int $timeoutSeconds): ProcessResult
    {
        if ($argv === [] || array_filter($argv, static fn (mixed $value): bool => !is_string($value)) !== []) {
            throw new RuntimeException('进程参数必须是字符串数组');
        }
        $pipes = [];
        $process = proc_open($argv, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('Docker CLI 不可用');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + max(1, $timeoutSeconds);
        do {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process, 9);
                throw new RuntimeException('Docker CLI 执行超时');
            }
            usleep(10000);
        } while (true);
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = (int) $status['exitcode'];
        proc_close($process);
        if ($exitCode === 127 || str_contains(strtolower($stderr), 'not found')) {
            throw new RuntimeException('Docker CLI 不可用');
        }
        return new ProcessResult($exitCode, $stdout, $stderr);
    }
}
