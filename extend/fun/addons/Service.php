<?php
declare(strict_types=1);

namespace fun\addons;

use fun\helper\FileHelper;
use think\Route;
use think\facade\Cache;
use think\facade\Event;
use fun\addons\middleware\Addons;

/**
 * 插件服务
 * Class Service
 * @package fun\addons
 */
class Service extends \think\Service
{
    protected $addons_path;
    //存放[插件名称]列表数据
    protected $addons_data=[];
    //存放[插件ini所有信息]列表数据
    protected $addons_data_list=[];
    //模块所有[config.php]里的信息存放
    protected $addons_data_list_config=[];
    public function register()
    {
        error_reporting(0);

        $this->app->bind('addons', Service::class);

        // 无则创建 plugins 目录
        $this->addons_path = $this->getAddonsPath();

        $this->autoload();

        addons_vendor_autoload($this->addons_data_list?$this->addons_data_list:Cache::get('addons_data_list',[]));

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
            $execute = '\\fun\\addons\\Route::execute';

            // 注册插件公共中间件
            if (is_file($this->app->addons->getAddonsPath() . 'middleware.php')) {
                $this->app->middleware->import(include $this->app->addons->getAddonsPath() . 'middleware.php', 'route');
            }

            // 注册控制器路由
            $route->rule(PLUGIN_DIR . "/:addon/[:controller]/[:action]", $execute)->middleware(Addons::class);
            // 旧 URL 只读兼容，插件文件不再写入 addons 目录。
            $route->rule("addons/:addon/[:controller]/[:action]", $execute)->middleware(Addons::class);
            // 自定义路由
            $routes = (array) config('addons.route', []);
            foreach ($routes as $key => $val) {
                if (!$val) {
                    continue;
                }
                $rules = [];
                if (is_array($val)) {
                    if(!empty($val['app_domain'])){
                        if(!empty($val['app_rule'])){
                            \think\facade\Route::domain(((string)$val['app_domain']), function () use ($val){
                                // 动态注册域名的路由规则
                                foreach ($val['app_rule'] as $k => $rule) {
                                    \think\facade\Route::rule($k,$rule);
                                }
                            });
                        }
                    }
                    $domain = !empty($val['domain'])?$val['domain']:'';
                    if($domain){
                        foreach ($val['rule'] as $k => $rule) {
                            [$addon, $controller, $action] = explode('/', $rule);
                            $rules[$k] = [
                                'addons'        => $addon,
                                'controller'    => $controller,
                                'action'        => $action,
                                'indomain'      => 1,
                            ];
                        }
                        $route->domain((string)$domain, function () use ($rules, $route, $execute) {
                            // 动态注册域名的路由规则
                            foreach ($rules as $k => $rule) {
                                $route->rule($k, $execute)
                                    ->name($k)
                                    ->completeMatch(true)
                                    ->append($rule);
                            }
                        });
                    }else{
                        foreach ($val['rule'] as $k => $rule) {
                            [$addon, $controller, $action] = explode('/', $rule);
                            $rules[$k] = [
                                'addons'        => $addon,
                                'controller'    => $controller,
                                'action'        => $action,
                            ];
                        }
                        foreach ($rules as $k => $rule) {
                            $route->rule($k, $execute)
                                ->name($k)
                                ->completeMatch(true)
                                ->append($rule);
                        }
                    }

                }
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
        $results = scandir($this->addons_path);
        $bind = [];
        foreach ($results as $name) {
            if ($name === '.' or $name === '..') {
                continue;
            }
            if (is_file($this->addons_path . $name)) {
                continue;
            }
            $addonDir = $this->addons_path . $name . DIRECTORY_SEPARATOR;
            if (!is_dir($addonDir)) {
                continue;
            }

            if (!is_file($addonDir .   'Plugin.php') && !is_file($addonDir . 'Addon.php')) {
                continue;
            }
            $service_file = $addonDir . 'service.ini';
            if (!is_file($service_file)) {
                continue;
            }
            $services = parse_ini_file($service_file, true, INI_SCANNER_TYPED) ?: [];
            if($services){
                foreach ($services as $service) {
                    if (class_exists($service)) {
                        $this->app->register($service,$force=true);
                    }
                }
            }
            $bind[] = $services; // 收集服务
        }
        if(!empty($bind)){
            $this->app->bind($bind);
        }

        $routes = (array) config('addons.route', []);
        foreach ($routes as $key => $val) {
            if (!$val) {
                continue;
            }
            if (is_array($val)) {
                if (!empty($val['app_domain'])) {
                    \config(['domain_bind' =>[$val['app_domain']=>$val['addons']]],'app');
                }
            }
        }
    }
    /**
     * 插件事件
     */
    private function loadEvent()
    {
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);
        if (empty($hooks)) {
            $hooks = (array)config('addons.hooks', []);
            // 初始化钩子
            foreach ($hooks as $key => $values) {
                if (is_string($values)) {
                    $values = explode(',', $values);
                } else {
                    $values = (array)$values;
                }
                $hooks[$key] = array_filter(array_map(function ($v) use ($key) {
                    $addon = get_addons_class($v);
                    return $addon?[$addon,$key]:[];
                }, $values));
            }
            Cache::set('hooks', $hooks);
        }
        Event::listenEvents($hooks);
        //如果在插件中有定义 AddonsInit，则直接执行
        if (isset($hooks['AddonsInit'])) {
            foreach ($hooks['AddonsInit'] as $k => $v) {
                Event::trigger( 'AddonsInit',$v);
            }
        }
    }


    /**
     * 自动载入钩子插件
     * @return bool
     */
    private function autoload()
    {
        if (!config('addons.autoload', true)) {
            return true;
        }

        $config = config('addons');
        $base = array_merge(get_class_methods("\\fun\\Addons"), [
            'init', 'initialize', 'install', 'uninstall', 'enabled', 'disabled',
            'config', 'beforeUpdate', 'afterUpdate', 'configChanged', 'purgeData',
        ]);
        foreach (glob($this->getAddonsPath() . '*/*.php') as $pluginFile) {
            $info = pathinfo($pluginFile);
            if (!in_array(strtolower($info['filename']), ['plugin', 'addon'], true)) {
                continue;
            }
            $name = pathinfo($info['dirname'], PATHINFO_FILENAME);
            $manifest = $info['dirname'] . DS . 'plugin.ini';
            if (!is_file($manifest)) {
                $manifest = $info['dirname'] . DS . 'addon.ini';
            }
            if (!is_file($manifest)) {
                continue;
            }
            $pluginConfig = parse_ini_file($manifest, true, INI_SCANNER_TYPED) ?: [];
            if (empty($pluginConfig['status']) || empty($pluginConfig['install']) || empty($pluginConfig['name'])) {
                continue;
            }

            $class = "\\" . ADDON_NAMESPACE . "\\" . $name . "\\" . $info['filename'];
            if (!class_exists($class)) {
                continue;
            }
            $methods = (array) get_class_methods($class);
            $this->addons_data[] = $pluginConfig['name'];
            $this->addons_data_list[$pluginConfig['name']] = $pluginConfig;
            $configFile = $this->getAddonsPath() . $pluginConfig['name'] . DS . 'config.php';
            $this->addons_data_list_config[$pluginConfig['name']] = is_file($configFile) ? (array) include $configFile : [];
            foreach (array_diff($methods, $base) as $hook) {
                if (!isset($config['hooks'][$hook])) {
                    $config['hooks'][$hook] = [];
                }
                if (is_string($config['hooks'][$hook])) {
                    $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
                }
                if (!in_array($name, $config['hooks'][$hook], true)) {
                    $config['hooks'][$hook][] = $name;
                }
            }
        }
        Cache::set('addons_config', $config);
        Cache::set('addons_data', $this->addons_data);
        Cache::set('addons_data_list', $this->addons_data_list);
        Cache::set('addons_data_list_config', $this->addons_data_list_config);
        config($config, 'addons');
        return true;
    }

    /**
     * 获取 addons 路径
     * @return string
     */
    public function getAddonsPath()
    {
        // 初始化插件目录
        $addons_path = $this->app->getRootPath() . PLUGIN_DIR . DS;
        // 如果插件目录不存在则创建
        if (!is_dir($addons_path)) {
            @mkdir($addons_path, 0755, true);
        }
        return $addons_path;
    }

    /**
     * 获取插件的配置信息
     * @param string $name
     * @return array
     */
    public function getAddonsConfig()
    {
        $name = $this->app->request->addon;
        $addon = get_addons_instance($name);
        if (!$addon) {
            return [];
        }
        return $addon->getConfig();
    }

    /**
     * 获取插件源资源文件夹
     * @param string $name 插件名称
     * @return  string
     */
    public static function getAssetsDir($name)
    {
        $assetsDir = [
            Service::getAddonsNamePath($name) . 'public' . DS =>app()->getRootPath() . str_replace("/", DS, "public/static/{$name}"),
            Service::getAddonsNamePath($name) . 'storage' . DS=> app()->getRootPath() . str_replace("/", DS, "public/storage/{$name}")];
        return $assetsDir;
    }


    //获取插件目录
    public static function getAddonsNamePath($name)
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
            $sourcedir =  Service::getAddonsNamePath($name) .$dir. DS . $name;
            if (is_dir($sourcedir)) {
                FileHelper::copyDir($sourcedir, app()->getBasePath().DS.$name,$delete);
                if($delete) FileHelper::delDir(Service::getAddonsNamePath($name).$dir);
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
        $addonPath =  Service::getAddonsNamePath($name);
        if(is_dir($appDir)){
            foreach (scandir($appDir) as $dir){
                $sourcedir = $appDir.DS.$dir;
                if(in_array($dir,['.','..'])) continue;
                if (is_dir($sourcedir)) {
                    FileHelper::copyDir($sourcedir, $addonPath .'app'. DS. $name . DS .$dir. DS,$delete);
                    if($delete) FileHelper::delDir($sourcedir);
                }else{
                    if(!is_dir(dirname($addonPath .'app'. DS. $name))) @mkdir($addonPath .'app'. DS. $name,0755,true);
                    @copy($sourcedir,$addonPath .'app'.DS .$name . DS .$dir);
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
    public static function updateAddonsInfo($name, $state = 1, $install = 1)
    {
        $addonslist = get_addons_list();
        $addonslist[$name]['status'] = $state;
        $addonslist[$name]['install'] = $install;
        Cache::set('addonslist', $addonslist);
        set_addons_info($name, ['status' => $state, 'install' => $install]);
    }

}
