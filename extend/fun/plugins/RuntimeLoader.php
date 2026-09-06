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
    /** 已挂载的插件 vendor autoload，防同一请求内重复注册 */
    private static array $loadedAutoloads = [];

    /**
     * 挂载插件自带 composer vendor（约定检测 vendor/autoload.php），
     * 必须在 entry 之前调用：入口类的父类/接口可能来自插件 vendor。
     */
    public function loadComposerAutoload(Manifest $manifest): void
    {
        $file = $manifest->directory() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
        if (!is_file($file) || isset(self::$loadedAutoloads[$file])) {
            return;
        }
        require_once $file;
        self::$loadedAutoloads[$file] = true;
    }

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
        $this->loadComposerAutoload($manifest);
        $entry = $manifest->toArray()['entry'];
        $file = $manifest->directory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $entry['file']);
        require_once $file;
        $class = (string) ($entry['class'] ?? '');
        if ($class === '' || !class_exists($class, false)) {
            throw new RuntimeException('插件入口类加载失败：' . $class);
        }
    }

    /** 按 vendor autoload、entry、容器实例化的固定顺序创建生命周期对象。 */
    public function instantiateEntry(Manifest $manifest, callable $make): object
    {
        $this->loadComposerAutoload($manifest);
        $this->loadEntry($manifest);
        $class = (string) ($manifest->toArray()['entry']['class'] ?? '');
        return $make($class);
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
        if (!$registrar instanceof \Closure) {
            throw new RuntimeException('插件 routes 加载文件必须返回 Closure');
        }
        $registrar($route);
    }

    /**
     * 通道（channels.api / channels.index）路由文件路径，未声明返回 null。
     */
    public function channelRoutesPath(Manifest $manifest, string $channel): ?string
    {
        if (!in_array($channel, ['api', 'index'], true)) {
            throw new RuntimeException('不支持的插件 channel：' . $channel);
        }
        $channels = (array) ($manifest->toArray()['channels'] ?? []);
        $relative = (string) ($channels[$channel]['routes'] ?? '');
        if ($relative === '') {
            return null;
        }
        return $manifest->directory() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * 加载通道路由：仅由 Service 在匹配的应用请求内调用，实现应用级隔离。
     */
    public function loadChannelRoutes(Route $route, Manifest $manifest, string $channel): void
    {
        $file = $this->channelRoutesPath($manifest, $channel);
        if ($file === null) {
            return;
        }
        $registrar = require $file;
        if (!$registrar instanceof \Closure) {
            throw new RuntimeException('插件 channel ' . $channel . ' 路由加载文件必须返回 Closure');
        }
        $registrar($route);
    }
}
