<?php

declare(strict_types=1);

namespace fun\plugins;

/** 仅注册插件基础服务，不访问控制面或加载插件源码。 */
class Service extends \think\Service
{
    protected string $plugins_path;

    public function register(): void
    {
        $this->app->bind('plugins', Service::class);
        $this->plugins_path = $this->getPluginsPath();
        $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }

    public function getPluginsPath(): string
    {
        $pluginsPath = $this->app->getRootPath() . PLUGIN_DIR . DS;
        if (!is_dir($pluginsPath)) {
            @mkdir($pluginsPath, 0755, true);
        }
        return $pluginsPath;
    }

    public static function getPluginCodePath(string $code): string
    {
        return app()->getRootPath() . PLUGIN_DIR . DS . $code . DS;
    }
}
