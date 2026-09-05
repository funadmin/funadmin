<?php

declare(strict_types=1);

namespace fun\plugins;

use RuntimeException;
use think\App;
use think\Route;

/**
 * 只加载 plugin.json 显式声明的运行时边界。
 */
final class RuntimeLoader
{
    public function boundaries(Manifest $manifest): array
    {
        $boundaries = [];
        foreach (['services', 'events', 'routes'] as $type) {
            $path = $manifest->loadPath($type);
            if ($path !== null) {
                $boundaries[$type] = $path;
            }
        }
        return $boundaries;
    }

    public function loadEntry(Manifest $manifest): void
    {
        $entry = $manifest->toArray()['entry'];
        $file = $manifest->directory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $entry['file']);
        require_once $file;
        $class = (string) ($manifest->toArray()['entry']['class'] ?? '');
        if ($class === '' || !class_exists($class, false)) {
            throw new RuntimeException('插件入口类加载失败：' . $class);
        }
    }

    public function loadServices(App $app, Manifest $manifest): void
    {
        $file = $manifest->loadPath('services');
        if ($file === null) {
            return;
        }
        $services = require $file;
        if (!is_array($services)) {
            throw new RuntimeException('插件 services 加载文件必须返回数组');
        }
        foreach ($services as $service) {
            if (!is_string($service) || !class_exists($service)) {
                throw new RuntimeException('插件 service 类不存在');
            }
            $app->register($service, true);
        }
    }

    public function loadEvents(App $app, Manifest $manifest): void
    {
        $file = $manifest->loadPath('events');
        if ($file === null) {
            return;
        }
        $events = require $file;
        if (!is_array($events)) {
            throw new RuntimeException('插件 events 加载文件必须返回数组');
        }
        $app->loadEvent($events);
    }

    public function loadRoutes(Route $route, Manifest $manifest): void
    {
        $file = $manifest->loadPath('routes');
        if ($file === null) {
            return;
        }
        $registrar = require $file;
        if (!is_callable($registrar)) {
            throw new RuntimeException('插件 routes 加载文件必须返回 callable');
        }
        $registrar($route);
    }
}
