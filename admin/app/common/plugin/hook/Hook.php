<?php

declare(strict_types=1);
namespace app\common\plugin\hook;

use app\common\plugin\sdk\PluginActivationReader;
use support\Container;

/** Global entry point available in services, CLI, workers and other plugins. */
final class Hook
{
    public static function action(string $point, mixed $payload = null, array $context = []): void
    {
        self::runtime()->action($point, $payload, $context);
    }

    public static function filter(string $point, mixed $value, array $context = []): mixed
    {
        return self::runtime()->filter($point, $value, $context);
    }

    private static function runtime(): HookRuntime
    {
        $container = Container::instance();
        if (!$container->has(HookRuntime::class)) {
            $container->addDefinitions([HookRuntime::class => static fn() => new HookRuntime(
                new PluginActivationReader(base_path('runtime/plugins/activation'), true),
                base_path('plugins')
            )]);
        }
        return $container->get(HookRuntime::class);
    }
}
