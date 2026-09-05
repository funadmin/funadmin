<?php

declare(strict_types=1);

namespace app\common\crud;

use RuntimeException;

/**
 * 调用固定的本地 POSIX helper，以目录 fd 完成最终安全提交。
 */
final class SafeCommit
{
    private const SOURCE = __DIR__ . '/support/crud_safe_commit.c';

    public function __construct(private readonly string $projectRoot)
    {
    }

    public function commit(
        string $stagePath,
        string $targetPath,
        string $backupPath,
        ?string $previousHash,
        string $newHash
    ): array {
        return $this->execute('commit', [
            $this->relative($stagePath),
            $this->relative($targetPath),
            $previousHash === null ? '-' : $this->relative($backupPath),
            $previousHash ?? '-',
            $newHash,
        ]);
    }

    public function restore(
        string $backupPath,
        string $targetPath,
        string $currentHash,
        string $restoreHash,
        string $targetParent
    ): array {
        return $this->execute('restore', [
            $this->relative($backupPath),
            $this->relative($targetPath),
            $currentHash,
            $restoreHash,
            $targetParent,
        ]);
    }

    public function remove(string $targetPath, string $currentHash, string $targetParent): array
    {
        return $this->execute('remove', [
            $this->relative($targetPath),
            $currentHash,
            $targetParent,
        ]);
    }

    private function relative(string $path): string
    {
        return PathGuard::relative($this->projectRoot, $path);
    }

    private function execute(string $operation, array $arguments): array
    {
        $root = realpath($this->projectRoot);
        if ($root === false || !is_dir($root)) {
            throw new RuntimeException('安全文件操作无法解析项目根');
        }
        $command = [$this->helperBinary(), $operation, $root, ...$arguments];
        $pipes = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $root, $this->helperEnvironment(), ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('无法启动固定安全文件操作 helper');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        $result = is_string($stdout) ? json_decode(trim($stdout), true) : null;
        if (!is_array($result)
            || !isset($result['ok'], $result['code'], $result['message'], $result['phase'], $result['renamed'])) {
            throw new RuntimeException('安全文件操作 helper 返回无效：' . trim((string) $stderr));
        }
        $result['exit_code'] = $exitCode;
        if (is_string($stderr) && trim($stderr) !== '') {
            $result['stderr'] = trim($stderr);
        }
        return $result;
    }

    private function helperBinary(): string
    {
        if (PHP_OS_FAMILY === 'Windows' || !is_file(self::SOURCE)) {
            throw new RuntimeException('当前环境不支持 POSIX 安全提交 helper');
        }
        $sourceHash = hash_file('sha256', self::SOURCE);
        if (!is_string($sourceHash)) {
            throw new RuntimeException('无法校验安全提交 helper 源码');
        }
        $directory = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . '/runtime/cache/crud-safe-commit';
        $this->secureDirectory($directory);
        $testing = defined('FUNADMIN_CRUD_HELPER_TESTING') && FUNADMIN_CRUD_HELPER_TESTING === true;
        $binary = $directory . '/crud-safe-commit-' . $sourceHash . ($testing ? '-test' : '');
        if (!is_file($binary)) {
            $this->compile($binary, $testing);
        }
        clearstatcache(true, $binary);
        $stat = @lstat($binary);
        if ($stat === false || (($stat['mode'] & 0170000) !== 0100000) || !is_executable($binary)) {
            throw new RuntimeException('安全提交 helper 不是可信可执行普通文件');
        }
        if (($stat['mode'] & 0777) !== 0700 || !hash_equals($sourceHash, hash_file('sha256', self::SOURCE))) {
            throw new RuntimeException('安全提交 helper 权限或源码校验失败');
        }
        return $binary;
    }

    private function compile(string $binary, bool $testing): void
    {
        $compiler = $this->compiler();
        $temporary = $binary . '.tmp-' . bin2hex(random_bytes(6));
        $command = [$compiler, '-D_DARWIN_C_SOURCE', '-std=c11', '-O2', '-Wall', '-Wextra', '-Werror'];
        if ($testing) {
            $command[] = '-DFUNADMIN_CRUD_HELPER_TEST_BUILD=1';
        }
        array_push($command, self::SOURCE, '-o', $temporary);
        $pipes = [];
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) {
            throw new RuntimeException('安全提交 helper 编译器不可用');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        if ($exitCode !== 0 || !is_file($temporary) || !chmod($temporary, 0700)) {
            @unlink($temporary);
            throw new RuntimeException('无法构建安全提交 helper：' . trim((string) $stderr . (string) $stdout));
        }
        if (!link($temporary, $binary)) {
            @unlink($temporary);
            throw new RuntimeException('无法原子安装安全提交 helper');
        }
        @unlink($temporary);
    }

    private function compiler(): string
    {
        foreach (['/usr/bin/cc', '/usr/bin/clang'] as $compiler) {
            if (is_file($compiler) && is_executable($compiler)) {
                return $compiler;
            }
        }
        throw new RuntimeException('缺少固定路径 C 编译器，安全提交已拒绝');
    }

    private function secureDirectory(string $directory): void
    {
        $oldMask = umask(0077);
        try {
            if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                throw new RuntimeException('无法创建安全提交 helper 目录');
            }
        } finally {
            umask($oldMask);
        }
        if (is_link($directory) || !chmod($directory, 0700)) {
            throw new RuntimeException('安全提交 helper 目录权限不安全');
        }
        clearstatcache(true, $directory);
        if ((fileperms($directory) & 0777) !== 0700) {
            throw new RuntimeException('安全提交 helper 目录权限验证失败');
        }
    }

    private function helperEnvironment(): array
    {
        $environment = [];
        if (defined('FUNADMIN_CRUD_HELPER_TESTING') && FUNADMIN_CRUD_HELPER_TESTING === true) {
            $swap = getenv('FUNADMIN_CRUD_HELPER_TEST_SWAP_PARENT');
            if (is_string($swap) && preg_match('/^[A-Za-z0-9_.\/-]+:[A-Za-z0-9_.\/-]+$/', $swap) === 1) {
                $environment['FUNADMIN_CRUD_HELPER_TEST_SWAP_PARENT'] = $swap;
            }
            $failure = getenv('FUNADMIN_CRUD_HELPER_TEST_FAIL_AFTER_RENAME');
            if (is_string($failure) && in_array($failure, ['fsync', 'verify'], true)) {
                $environment['FUNADMIN_CRUD_HELPER_TEST_FAIL_AFTER_RENAME'] = $failure;
            }
        }
        return $environment;
    }
}
