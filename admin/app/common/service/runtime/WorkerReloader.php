<?php

declare(strict_types=1);

namespace app\common\service\runtime;

/**
 * 请求 webman 主进程平滑重载 worker（等价于 php start.php reload）。
 * 注解路由只在 worker 启动时注册，生成新的核心控制器后需要重载才能访问；文件监控默认关闭。
 * 信号由主进程逐个重启 worker：空闲 worker 立即重启；发起重载的请求先完成响应；
 * 其他 worker 上正在执行的请求最多等待 config/server.php 的 stop_timeout（默认 30 秒，WEBMAN_STOP_TIMEOUT 可调）后才会被强制结束。
 * 任何不可用情况（Windows/无 posix、pid 文件缺失、主进程不存在）都只返回原因，绝不抛异常。
 */
final class WorkerReloader
{
    /** @var (\Closure(int, int): bool)|null */
    private readonly ?\Closure $sender;

    /**
     * @param callable(int $pid, int $signal): bool|null $signalSender 测试注入；默认 posix_kill。信号 0 用于探测主进程存活。
     */
    public function __construct(private readonly string $pidFile, ?callable $signalSender = null, private readonly ?bool $posixAvailable = null)
    {
        $this->sender = $signalSender !== null ? \Closure::fromCallable($signalSender) : null;
    }

    public static function production(): self
    {
        $pidFile = function_exists('config') ? config('server.pid_file') : null;
        return new self(is_string($pidFile) && $pidFile !== '' ? $pidFile : runtime_path() . '/webman.pid');
    }

    public function pidFile(): string
    {
        return $this->pidFile;
    }

    /** SIGUSR1：webman/Workerman 的 reload 信号（无 pcntl 常量时按平台取值）。 */
    public static function reloadSignal(): int
    {
        return \defined('SIGUSR1') ? \SIGUSR1 : (PHP_OS_FAMILY === 'Linux' ? 10 : 30);
    }

    /** @return array{requested: bool, reason: string|null} */
    public function reload(): array
    {
        $sender = $this->sender;
        if ($sender === null) {
            if (!($this->posixAvailable ?? function_exists('posix_kill'))) return self::declined('posix_unavailable');
            $sender = static fn (int $pid, int $signal): bool => posix_kill($pid, $signal);
        }
        if (!is_file($this->pidFile) || !is_readable($this->pidFile)) return self::declined('pid_file_missing');
        $content = trim((string) @file_get_contents($this->pidFile));
        if (preg_match('/^[1-9][0-9]{0,9}$/D', $content) !== 1) return self::declined('pid_invalid');
        $pid = (int) $content;
        try {
            if (!$sender($pid, 0)) return self::declined('master_not_running');
            if (!$sender($pid, self::reloadSignal())) return self::declined('signal_failed');
        } catch (\Throwable) {
            return self::declined('signal_failed');
        }
        return ['requested' => true, 'reason' => null];
    }

    /** @return array{requested: false, reason: string} */
    private static function declined(string $reason): array
    {
        return ['requested' => false, 'reason' => $reason];
    }
}
