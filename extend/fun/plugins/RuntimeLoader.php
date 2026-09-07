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

    /** 已挂载的原生应用 namespace，防同一请求内重复注册。 */
    private static array $loadedNativeNamespaces = [];

    /**
     * 为发布后的 application 与 Console layer 注册精确插件 namespace。
     */
    public function loadNativeAutoload(Manifest $manifest): void
    {
        $code = $manifest->code();
        $prefix = 'plugin\\' . $code . '\\';
        $projectRoot = dirname($manifest->directory(), 2);
        $key = $prefix . "\0" . $projectRoot;
        if (isset(self::$loadedNativeNamespaces[$key])) {
            return;
        }
        spl_autoload_register(static function (string $class) use ($code, $prefix, $projectRoot): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $suffix = substr($class, strlen($prefix));
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $suffix) !== 1) {
                return;
            }
            $segments = explode('\\', $suffix);
            if ($segments[0] === 'console' && count($segments) >= 3) {
                $layer = $segments[1];
                if (!in_array($layer, ['controller', 'model', 'service', 'validate', 'middleware'], true)) {
                    return;
                }
                $relative = implode(DIRECTORY_SEPARATOR, array_slice($segments, 2)) . '.php';
                $file = $projectRoot . '/app/console/' . $layer . '/plugin/' . $code . '/' . $relative;
            } else {
                $file = $projectRoot . '/app/' . $code . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $suffix) . '.php';
            }
            if (is_file($file) && !is_link($file)) {
                require_once $file;
            }
        }, true, true);
        self::$loadedNativeNamespaces[$key] = true;
    }

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

    /** Manifest v2 使用原生应用目录，不再读取 channels。 */
    public function channelRoutesPath(Manifest $manifest, string $channel): ?string
    {
        if (!in_array($channel, ['api', 'frontend'], true)) {
            throw new RuntimeException('不支持的插件 channel：' . $channel);
        }
        return null;
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
