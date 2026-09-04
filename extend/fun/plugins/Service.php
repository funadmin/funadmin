<?php
declare(strict_types=1);

namespace fun\plugins;

use fun\helper\FileHelper;
use think\Route;
use think\facade\Cache;
use fun\plugins\middleware\Plugins;

/**
 * 插件服务
 * Class Service
 * @package fun\plugins
 */
class Service extends \think\Service
{
    protected $plugins_path;
    //存放[插件名称]列表数据
    protected $plugins_data=[];
    //存放[插件ini所有信息]列表数据
    protected $plugins_data_list=[];
    //模块所有[config.php]里的信息存放
    protected $plugins_data_list_config=[];
    public function register()
    {
        $this->app->bind('plugins', Service::class);

        // 无则创建 plugins 目录
        $this->plugins_path = $this->getPluginsPath();

        $this->autoload();

        plugins_vendor_autoload($this->plugins_data_list?$this->plugins_data_list:Cache::get('plugins_data_list',[]));

        // 加载系统语言包
        $this->loadLang();
        // 2.注册插件事件hook
        $this->loadEvent();

        $this->loadService();
        // 4.自动加载全局的插件内部第三方类库
    }
    public function boot()
    {
        //注册HttpRun事件监听,触发后注册全局中间件到开始位置
        $this->registerRoutes(function (Route $route) {
            // 路由脚本
            $execute = '\\fun\\plugins\\Route::execute';

            // 注册插件公共中间件
            if (is_file($this->app->plugins->getPluginsPath() . 'middleware.php')) {
                $this->app->middleware->import(include $this->app->plugins->getPluginsPath() . 'middleware.php', 'route');
            }

            // 注册插件控制器路由。
            $route->rule(PLUGIN_DIR . "/:plugin/[:controller]/[:action]", $execute)->middleware(Plugins::class);
            // 自定义路由只允许由 enabled 插件在 plugin.json 中显式声明。
            $loader = new RuntimeLoader();
            foreach ($this->registry()->enabled() as $manifest) {
                $loader->loadRoutes($route, $manifest);
            }
        });

    }

    private function loadLang()
    {
        // 加载应用默认语言包
        $this->app->loadLangPack($this->app->lang->defaultLangSet());
    }

    /**
     * 挂载插件服务
     */
    private function loadService()
    {
        $loader = new RuntimeLoader();
        foreach ($this->registry()->enabled() as $manifest) {
            $loader->loadServices($this->app, $manifest);
        }
    }

    private function registry(): Registry
    {
        return new Registry($this->plugins_path, function (): array {
            $records = [];
            $query = \app\common\model\Plugin::where(function ($query): void {
                $query->whereNull('delete_time')->whereOr('delete_time', 0);
            });
            foreach ($query->select() as $record) {
                $records[(string) $record->name] = [
                    'version' => (string) $record->version,
                    'lifecycle_state' => (string) $record->lifecycle_state,
                ];
            }
            return $records;
        });
    }
    /**
     * 插件事件
     */
    private function loadEvent()
    {
        $loader = new RuntimeLoader();
        foreach ($this->registry()->enabled() as $manifest) {
            $loader->loadEvents($this->app, $manifest);
        }
    }


    /**
     * 自动载入钩子插件
     * @return bool
     */
    private function autoload()
    {
        if (!config('plugins.autoload', true)) {
            return true;
        }

        $config = config('plugins');
        foreach ($this->registry()->enabled() as $name => $manifest) {
            $pluginConfig = $manifest->toArray();
            $pluginConfig['status'] = 1;
            $pluginConfig['install'] = 1;
            $this->plugins_data[] = $name;
            $this->plugins_data_list[$name] = $pluginConfig;
            $configFile = $this->getPluginsPath() . $name . DS . 'config.php';
            $this->plugins_data_list_config[$name] = is_file($configFile) ? (array) include $configFile : [];
        }
        Cache::set('plugins_config', $config);
        Cache::set('plugins_data', $this->plugins_data);
        Cache::set('plugins_data_list', $this->plugins_data_list);
        Cache::set('plugins_data_list_config', $this->plugins_data_list_config);
        config($config, 'plugins');
        return true;
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

    /**
     * 获取插件源资源文件夹
     * @param string $name 插件名称
     * @return  string
     */
    public static function getAssetsDir($name)
    {
        $assetsDir = [
            Service::getPluginsNamePath($name) . 'public' . DS =>app()->getRootPath() . str_replace("/", DS, "public/static/{$name}"),
            Service::getPluginsNamePath($name) . 'storage' . DS=> app()->getRootPath() . str_replace("/", DS, "public/storage/{$name}")];
        return $assetsDir;
    }


    //获取插件目录
    public static function getPluginsNamePath($name)
    {
        return app()->getRootPath() . PLUGIN_DIR . DS . $name . DS;
    }

    /**
     * 获取忽略的目录
     * @return string[]
     */
    public static function getAppDir(){
        return [
            "app"
        ];
    }
    /**
     * @param $name
     * @return void
     */
    public static function copyApp($name,$delete = false){
        foreach (Service::getAppDir() as $k => $dir) {
            $sourcedir =  Service::getPluginsNamePath($name) .$dir. DS . $name;
            if (is_dir($sourcedir)) {
                FileHelper::copyDir($sourcedir, app()->getBasePath().DS.$name,$delete);
                if($delete) FileHelper::delDir(Service::getPluginsNamePath($name).$dir);
            }else{
                @copy($sourcedir, app()->getBasePath() .DS.$dir);
                if($delete) @unlink($sourcedir);
            }
        }
        $assetsDir = Service::getAssetsDir($name);
        foreach ($assetsDir as $key=>$item){
            if (is_dir($key)) {
                FileHelper::copyDir($key, $item,$delete);
                if($delete) FileHelper::delDir($key);
            }
        }

    }
    /**
     * @param $name
     * @return void
     */
    public static function removeApp($name,$delete =false){
        $appDir = app()->getBasePath().$name;
        $pluginPath =  Service::getPluginsNamePath($name);
        if(is_dir($appDir)){
            foreach (scandir($appDir) as $dir){
                $sourcedir = $appDir.DS.$dir;
                if(in_array($dir,['.','..'])) continue;
                if (is_dir($sourcedir)) {
                    FileHelper::copyDir($sourcedir, $pluginPath .'app'. DS. $name . DS .$dir. DS,$delete);
                    if($delete) FileHelper::delDir($sourcedir);
                }else{
                    if(!is_dir(dirname($pluginPath .'app'. DS. $name))) @mkdir($pluginPath .'app'. DS. $name,0755,true);
                    @copy($sourcedir,$pluginPath .'app'.DS .$name . DS .$dir);
                    if($delete) @unlink($sourcedir);
                }
            }
            @rmdir($appDir);
        }
        // 移除插件基础静态资源目录
        $assetsDir = Service::getAssetsDir($name);
        foreach ($assetsDir as $key=>$item) {
            if (is_dir($item)) {
                FileHelper::copyDir($item,$key,$delete);
                if($delete) FileHelper::delDir($item);
            }
        }
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

    //更新插件状态
    public static function updatePluginsInfo($name, $state = 1, $install = 1)
    {
        $pluginslist = get_plugins_list();
        $pluginslist[$name]['status'] = $state;
        $pluginslist[$name]['install'] = $install;
        Cache::set('pluginslist', $pluginslist);
    }

}
