<?php

declare(strict_types=1);

namespace app\common\form;

use Throwable;

/** 在支持 pcntl 的同步 PHP 运行时中以信号中断超时任务，否则执行完成后校验 deadline。 */
final class DeadlineExecutor
{
    public static function run(callable $operation, int $timeoutMs, callable $timeoutException): mixed
    {
        $timeoutMs = max(1, min(30000, $timeoutMs));
        $startedAt = hrtime(true);
        $canInterrupt = function_exists('pcntl_async_signals')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_setitimer')
            && defined('ITIMER_REAL')
            && defined('SIGALRM');
        if (!$canInterrupt) {
            $result = $operation();
            if ((hrtime(true) - $startedAt) / 1_000_000 > $timeoutMs) throw $timeoutException();
            return $result;
        }

        $previousAsync = pcntl_async_signals(true);
        $previousHandler = function_exists('pcntl_signal_get_handler') ? pcntl_signal_get_handler(SIGALRM) : SIG_DFL;
        pcntl_signal(SIGALRM, static function () use ($timeoutException): never {
            throw $timeoutException();
        });
        pcntl_setitimer(ITIMER_REAL, $timeoutMs / 1000);
        try {
            $result = $operation();
            if ((hrtime(true) - $startedAt) / 1_000_000 > $timeoutMs) throw $timeoutException();
            return $result;
        } finally {
            pcntl_setitimer(ITIMER_REAL, 0);
            pcntl_signal(SIGALRM, $previousHandler);
            pcntl_async_signals($previousAsync);
        }
    }
}
