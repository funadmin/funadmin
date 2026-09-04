<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;

/**
 * 插件生命周期显式状态机。
 */
final class LifecycleState
{
    private const TRANSITIONS = [
        'discovered' => ['installing'],
        'installing' => ['disabled', 'failed'],
        'disabled' => ['enabling', 'uninstalling', 'installing'],
        'enabling' => ['enabled', 'failed'],
        'enabled' => ['disabling'],
        'disabling' => ['disabled', 'failed'],
        'uninstalling' => ['discovered', 'failed'],
        'failed' => ['disabled', 'installing', 'uninstalling'],
    ];

    public static function all(): array
    {
        return array_keys(self::TRANSITIONS);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function assertTransition(string $from, string $to): void
    {
        if (!self::canTransition($from, $to)) {
            throw new RuntimeException("非法插件状态迁移：{$from} -> {$to}");
        }
    }
}
