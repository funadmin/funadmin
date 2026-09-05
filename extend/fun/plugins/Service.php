<?php
declare(strict_types=1);

namespace fun\plugins;

use think\Route;

/**
 * 插件服务
 * Class Service
 * @package fun\plugins
 */
class Service extends \think\Service
{
    protected $plugins_path;
    public function register()
    {
        $this->app->bind('plugins', Service::class);

        // 无则创建 plugins 目录
        $this->plugins_path = $this->getPluginsPath();

        $this->loadLang();
        $this->loadRuntimeBoundaries();
    }
    public function boot()
    {
        $this->registerRoutes(function (Route $route): void {
            $loader = new RuntimeLoader();
            foreach ($this->registry()->enabled() as $manifest) {
                $this->isolate($manifest, 'routes', static fn () => $loader->loadRoutes($route, $manifest));
            }
        });

    }

    private function loadLang()
    {
        // 加载应用默认语言包
        $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }

    private function loadRuntimeBoundaries(): void
    {
        $loader = new RuntimeLoader();
        foreach ($this->registry()->enabled() as $manifest) {
            $this->isolate($manifest, 'entry', static fn () => $loader->loadEntry($manifest));
            $this->isolate($manifest, 'services', fn () => $loader->loadServices($this->app, $manifest));
            $this->isolate($manifest, 'events', fn () => $loader->loadEvents($this->app, $manifest));
        }
    }

    private function isolate(Manifest $manifest, string $boundary, callable $load): void
    {
        try {
            $load();
        } catch (\Throwable $exception) {
            error_log(sprintf(
                '插件 %s 的 %s 边界加载失败：%s',
                $manifest->name(),
                $boundary,
                $exception->getMessage()
            ));
        }
    }

    private function registry(): Registry
    {
        return new Registry($this->plugins_path, function (): array {
            $records = [];
            try {
                $query = \app\common\model\Plugin::whereNull('deleted_at');
                foreach ($query->select() as $record) {
                    $records[(string) $record->name] = [
                        'version' => (string) $record->version,
                        'lifecycle_state' => (string) $record->lifecycle_state,
                    ];
                }
            } catch (\Throwable) {
                // 安装期或数据库不可用时插件表可能尚未创建：降级为空记录，保证引导与安装流程可用。
                return [];
            }
            return $records;
        });
    }
    /**
     * 获取 plugins 路径
     * @return string
     */
    public function getPluginsPath()
    {
        // 初始化插件目录
        $plugins_path = $this->app->getRootPath() . PLUGIN_DIR . DS;
        // 如果插件目录不存在则创建
        if (!is_dir($plugins_path)) {
            @mkdir($plugins_path, 0755, true);
        }
        return $plugins_path;
    }

    /**
     * 获取插件的配置信息
     * @param string $name
     * @return array
     */
    public function getPluginsConfig()
    {
        $name = $this->app->request->plugin;
        $plugin = get_plugins_instance($name);
        if (!$plugin) {
            return [];
        }
        return $plugin->getConfig();
    }

    //获取插件目录
    public static function getPluginsNamePath($name)
    {
        return app()->getRootPath() . PLUGIN_DIR . DS . $name . DS;
    }

    /**
     * 获取检测的全局文件夹目录
     * @return  array
     */
    public static function getCheckDirs()
    {
        return [
            'global'
        ];
    }

}
