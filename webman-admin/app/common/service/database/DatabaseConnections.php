<?php

declare(strict_types=1);

namespace app\common\service\database;

use Illuminate\Container\Container;

/** 临时安装连接与具名连接共享实际配置，不把连接名称当成 driver。 */
final class DatabaseConnections
{
    public static function settings(?string $name = null): array
    {
        $container = Container::getInstance();
        if ($container->bound('config')) {
            $config = $container['config'];
            $name ??= $config['database.default'];
            $connections = $config['database.connections'] ?? [];
            if (isset($connections[$name])) return $connections[$name];
        }
        $name ??= (string) config('database.default', 'mysql');
        return (array) config('database.connections.' . $name, []);
    }

    public static function allowed(): array
    {
        if (config('crud.connections_explicit', false)) return (array) config('crud.connections', []);
        $container = Container::getInstance();
        $name = $container->bound('config') ? ($container['config']['database.default'] ?? null) : null;
        if (is_string($name) && (str_starts_with($name, 'installed_') || str_starts_with($name, 'installer_'))) {
            return [(string) (self::settings($name)['driver'] ?? '')];
        }
        return (array) config('crud.connections', []);
    }
}
