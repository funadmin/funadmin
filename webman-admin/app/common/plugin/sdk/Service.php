<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

/** 仅注册插件基础服务，不访问控制面或加载插件源码。 */
class Service implements \Webman\Bootstrap
{
    protected string $plugins_path;

    public static function start(?\Workerman\Worker $worker): void
    {
        (new self())->register();
    }

    public function register(): void
    {
        $container = \support\Container::instance();
        $container->addDefinitions(['plugins' => static fn () => new self()]);
        $this->plugins_path = $this->getPluginsPath();
    }

    public function getPluginsPath(): string
    {
        return rtrim(base_path('plugins'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    public static function getPluginCodePath(string $code): string
    {
        PluginScaffolder::assertValidName($code);
        return base_path('plugins/' . $code) . DIRECTORY_SEPARATOR;
    }
}
