<?php

declare(strict_types=1);

namespace app\common\plugin\sdk;

/** 注册基础服务，并仅为可信快照中已启用的插件加载依赖。 */
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
        $container->addDefinitions([
            'plugins' => static fn () => new self(),
            \app\common\plugin\hook\HookRuntime::class => static fn () => new \app\common\plugin\hook\HookRuntime(
                new PluginActivationReader(base_path('runtime/plugins/activation'), true),
                base_path('plugins')
            ),
        ]);
        $this->plugins_path = $this->getPluginsPath();
        PluginVendorAutoloader::loadEnabled(new PluginActivationReader(base_path('runtime/plugins/activation')), $this->plugins_path);
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
