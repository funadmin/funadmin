<?php

declare(strict_types=1);

namespace Tests;

use app\admin\development\service\BusinessDevelopmentService;
use app\common\service\runtime\WorkerReloader;
use PHPUnit\Framework\TestCase;

/** 正式生成核心模块后平滑重载 webman worker，使新注解路由生效；不可用时只返回原因，绝不抛异常。 */
final class WorkerReloaderTest extends TestCase
{
    private string $pidFile = '';

    protected function setUp(): void
    {
        $this->pidFile = sys_get_temp_dir() . '/funadmin-reload-' . bin2hex(random_bytes(6)) . '.pid';
    }

    protected function tearDown(): void
    {
        if (is_file($this->pidFile)) unlink($this->pidFile);
    }

    public function test_sends_sigusr1_to_the_live_master_pid(): void
    {
        file_put_contents($this->pidFile, "4321\n");
        $calls = [];
        $reloader = new WorkerReloader($this->pidFile, static function (int $pid, int $signal) use (&$calls): bool {
            $calls[] = [$pid, $signal];
            return true;
        });
        self::assertSame(['requested' => true, 'reason' => null], $reloader->reload());
        self::assertContains([4321, WorkerReloader::reloadSignal()], $calls);
        self::assertSame(\defined('SIGUSR1') ? \SIGUSR1 : WorkerReloader::reloadSignal(), WorkerReloader::reloadSignal());
        self::assertSame([4321, 0], $calls[0], '先探测主进程存活再发送重载信号');
    }

    public function test_missing_pid_file_dead_master_or_failed_signal_never_throw(): void
    {
        $calls = [];
        $sender = static function (int $pid, int $signal) use (&$calls): bool {
            $calls[] = [$pid, $signal];
            return false;
        };
        self::assertSame(['requested' => false, 'reason' => 'pid_file_missing'], (new WorkerReloader($this->pidFile, $sender))->reload());
        file_put_contents($this->pidFile, 'garbage');
        self::assertSame(['requested' => false, 'reason' => 'pid_invalid'], (new WorkerReloader($this->pidFile, $sender))->reload());
        file_put_contents($this->pidFile, '4321');
        self::assertSame(['requested' => false, 'reason' => 'master_not_running'], (new WorkerReloader($this->pidFile, $sender))->reload());
        self::assertSame([[4321, 0]], $calls, '主进程不存活时不发送重载信号');
        $throwing = static fn (int $pid, int $signal): bool => throw new \RuntimeException('boom');
        self::assertSame(['requested' => false, 'reason' => 'signal_failed'], (new WorkerReloader($this->pidFile, $throwing))->reload());
    }

    public function test_posix_unavailable_returns_reason_without_throwing(): void
    {
        file_put_contents($this->pidFile, '4321');
        self::assertSame(['requested' => false, 'reason' => 'posix_unavailable'], (new WorkerReloader($this->pidFile, posixAvailable: false))->reload());
    }

    public function test_production_reads_the_configured_pid_file(): void
    {
        self::assertSame(config('server.pid_file'), WorkerReloader::production()->pidFile());
    }

    public function test_only_core_formal_generation_requests_a_reload(): void
    {
        file_put_contents($this->pidFile, '4321');
        $calls = [];
        $reloader = new WorkerReloader($this->pidFile, static function (int $pid, int $signal) use (&$calls): bool {
            $calls[] = [$pid, $signal];
            return true;
        });
        $result = ['generationId' => 7, 'idempotentReplay' => false];

        $core = BusinessDevelopmentService::withWorkerReload($result, 'core', $reloader);
        self::assertTrue($core['reloadRequested']);
        self::assertNull($core['reloadReason']);
        self::assertSame(7, $core['generationId']);
        self::assertContains([4321, WorkerReloader::reloadSignal()], $calls);

        $calls = [];
        $plugin = BusinessDevelopmentService::withWorkerReload($result, 'plugin', $reloader);
        self::assertFalse($plugin['reloadRequested']);
        self::assertSame('plugin_target', $plugin['reloadReason']);
        self::assertSame([], $calls, '插件目标由插件生命周期激活，不重载 worker');

        $unavailable = BusinessDevelopmentService::withWorkerReload($result, 'core', new WorkerReloader($this->pidFile . '.missing', static fn (): bool => true));
        self::assertFalse($unavailable['reloadRequested']);
        self::assertSame('pid_file_missing', $unavailable['reloadReason']);
    }
}
