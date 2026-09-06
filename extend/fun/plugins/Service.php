<?php
declare(strict_types=1);

namespace fun\plugins;

use app\common\model\Plugin;
use app\common\model\PluginOperation;
use think\Route;

/**
 * 插件服务
 * Class Service
 * @package fun\plugins
 */
class Service extends \think\Service
{
    protected string $plugins_path;
    private ?array $commonManifests = null;
    private array $applicationManifests = [];

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
            $boundaries = [
                'routes' => static fn (Manifest $manifest) => $loader->loadRoutes($route, $manifest),
            ];
            // 普通路由与当前应用通道路由必须在同一插件边界链中执行，保留依赖失败传播与插件内短路。
            $appName = (string) $this->app->http->getName();
            $channel = $appName === 'index' ? 'frontend' : $appName;
            if (in_array($channel, ['api', 'frontend'], true)) {
                $boundaries['channels.' . $channel] = static fn (Manifest $manifest) => $loader->loadChannelRoutes($route, $manifest, $channel);
            }
            $this->booter()->boot($this->applicationManifests($appName), $boundaries);
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
        $this->booter()->boot($this->commonManifests(), [
            // vendor 必须先于 entry：入口类的父类/接口可能来自插件自带依赖
            'composer' => static fn (Manifest $manifest) => $loader->loadComposerAutoload($manifest),
            'entry' => static fn (Manifest $manifest) => $loader->loadEntry($manifest),
            'services' => fn (Manifest $manifest) => $loader->loadServices($this->app, $manifest),
            'events' => fn (Manifest $manifest) => $loader->loadEvents($this->app, $manifest),
        ]);
    }

    private function booter(): PluginRuntimeBooter
    {
        $recorder = new RuntimeLoadFailureRecorder(function (array $failure): void {
            $errorStage = (string) ($failure['error_stage'] ?? 'runtime');
            unset($failure['error_stage']);
            if (self::sameRuntimeFailure($failure, $errorStage)) {
                return;
            }
            PluginOperation::create($failure + [
                'from_version' => '',
                'to_version' => '',
                'recovery_path' => null,
            ]);
            Plugin::where('name', $failure['plugin_name'])->update([
                'status' => 0,
                'lifecycle_state' => 'failed',
                'last_error' => $failure['error_message'],
                'error_stage' => $errorStage,
            ]);
            // 失败插件立即从后续请求的编译清单移除，避免高并发下反复启动和写库。
            $this->runtimeCache()->rebuildOrInvalidate($this->registry()->enabled());
        });
        return new PluginRuntimeBooter([$recorder, 'record']);
    }

    private static function sameRuntimeFailure(array $failure, string $stage): bool
    {
        $plugin = Plugin::where('name', (string) ($failure['plugin_name'] ?? ''))->find();
        return $plugin
            && (string) $plugin->lifecycle_state === 'failed'
            && (string) $plugin->error_stage === $stage
            && hash_equals(
                hash('sha256', (string) $plugin->last_error),
                hash('sha256', (string) ($failure['error_message'] ?? ''))
            );
    }

    private function commonManifests(): array
    {
        if ($this->commonManifests !== null) {
            return $this->commonManifests;
        }
        $cache = $this->runtimeCache();
        if (!$cache->exists('console')) {
            // 首次部署尚无编译清单时仅发现一次，后续生命周期或命令会生成清单。
            return $this->commonManifests = $this->registry()->enabled();
        }
        return $this->commonManifests = $cache->load('console');
    }

    private function applicationManifests(string $application): array
    {
        $application = $application === 'index' ? 'frontend' : $application;
        $application = in_array($application, ['api', 'frontend'], true) ? $application : 'console';
        if (isset($this->applicationManifests[$application])) {
            return $this->applicationManifests[$application];
        }
        $cache = $this->runtimeCache();
        return $this->applicationManifests[$application] = $cache->exists($application)
            ? $cache->load($application)
            : $this->commonManifests();
    }

    private function runtimeCache(): PluginRuntimeCache
    {
        return new PluginRuntimeCache($this->plugins_path, root_path('runtime/plugins/compiled'));
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
                        'needs_reinstall' => (int) ($record->needs_reinstall ?? 0),
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
    public function getPluginsPath(): string
    {
        // 初始化插件目录
        $plugins_path = $this->app->getRootPath() . PLUGIN_DIR . DS;
        // 如果插件目录不存在则创建
        if (!is_dir($plugins_path)) {
            @mkdir($plugins_path, 0755, true);
        }
        return $plugins_path;
    }

    //获取插件目录
    public static function getPluginsNamePath(string $name): string
    {
        return app()->getRootPath() . PLUGIN_DIR . DS . $name . DS;
    }

    /**
     * 获取检测的全局文件夹目录
     * @return  array
     */
    public static function getCheckDirs(): array
    {
        return [
            'global'
        ];
    }

}
