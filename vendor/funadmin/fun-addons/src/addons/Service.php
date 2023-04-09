<?php
declare(strict_types=1);

namespace fun\addons;

use fun\helper\FileHelper;
use think\addons\Url;
use think\App;
use think\Console;
use think\Exception;
use think\facade\View;
use think\facade\Request;
use think\Route;
use think\helper\Str;
use think\facade\Config;
use think\facade\Lang;
use think\facade\Cache;
use think\facade\Event;
use fun\addons\middleware\Addons;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * 插件服务
 * Class Service
 * @package fun\addons
 */
class Service extends \think\Service
{
    protected $addons_path;
    protected $appName;
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

        // 无则创建addons目录
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
            $route->rule("addons/:addon/[:controller]/[:action]", $execute)->middleware(Addons::class);
            // 自定义路由
            $routes = (array) Config::get('addons.route', []);
            foreach ($routes as $key => $val) {
                if (!$val) {
                    continue;
                }
                if (is_array($val)) {
                    $domain = $val['domain'];
                    $rules = [];
                    foreach ($val['rule'] as $k => $rule) {
                        [$addon, $controller, $action] = explode('/', $rule);
                        $rules[$k] = [
                            'addons'        => $addon,
                            'controller'    => $controller,
                            'action'        => $action,
                            'indomain'      => 1,
                        ];
                    }
                    $route->domain($domain, function () use ($rules, $route, $execute) {
                        // 动态注册域名的路由规则
                        foreach ($rules as $k => $rule) {
                            $route->rule($k, $execute)
                                ->name($k)
                                ->completeMatch(true)
                                ->append($rule);
                        }
                    });
                } else {
                    list($addon, $controller, $action) = explode('/', $val);
                    $route->rule($key, $execute)
                        ->name($key)
                        ->completeMatch(true)
                        ->append([
                            'addons' => $addon,
                            'controller' => $controller,
                            'action' => $action
                        ]);
                }
            }
        });

    }

    private function loadLang()
    {
        // 加载系统语言包
        Lang::load([
            $this->app->getRootPath() . '/vendor/fun/fun-addons/src/lang/zh-cn.php'
        ]);
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

            if (!is_file($addonDir .   'Plugin.php')) {
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
            $bind = array_merge($bind, $services);
        }
        $this->app->bind($bind);
    }
    /**
     * 插件事件
     */
    private function loadEvent()
    {
        $hooks = $this->app->isDebug() ? [] : Cache::get('hooks', []);
        if (empty($hooks)) {
            $hooks = (array)Config::get('addons.hooks', []);
            // 初始化钩子
            foreach ($hooks as $key => $values) {
                if (is_string($values)) {
                    $values = explode(',', $values);
                } else {
                    $values = (array)$values;
                }
                $hooks[$key] = array_filter(array_map(function ($v) use ($key) {
                    return [get_addons_class($v),$key];
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
        // 是否处理自动载入
        if (!Config::get('addons.autoload', true)) {
            return true;
        }
        $config = Config::get('addons');
        // 读取插件目录及钩子列表
        $base = get_class_methods("\\fun\\Addons");
        $base = array_merge($base, ['init','initialize','install', 'uninstall', 'enabled', 'disabled']);
        // 读取插件目录中的php文件
        foreach (glob($this->getAddonsPath() . '*/*.php') as $addons_file) {
            // 格式化路径信息
            $info = pathinfo($addons_file);
            // 获取插件目录名
            $name = pathinfo($info['dirname'], PATHINFO_FILENAME);
            // 找到插件入口文件
            if (strtolower($info['filename']) === 'plugin') {
                // 读取出所有公共方法
                if(!class_exists("\\addons\\" . $name . "\\" . $info['filename'])) continue;
                $methods = (array)get_class_methods("\\addons\\" . $name . "\\" . $info['filename']);
                $ini= $info['dirname'] .DS. 'plugin.ini';
                if (!is_file($ini)) {
                    continue;
                }
                $addon_config = parse_ini_file($ini, true, INI_SCANNER_TYPED) ?: [];

                if(!$addon_config['status']) continue;
                if(!$addon_config['install']) continue;

                $this->addons_data[] = $addon_config['name'];
                $this->addons_data_list[$addon_config['name']] = $addon_config;
                $this->addons_data_list_config[$addon_config['name']] = include ($this->getAddonsPath().$addon_config['name'].'/config.php');
                // 跟插件基类方法做比对，得到差异结果
                $hooks = array_diff($methods, $base);
                // 循环将钩子方法写入配置中
                foreach ($hooks as $hook) {
                    if (!isset($config['hooks'][$hook])) {
                        $config['hooks'][$hook] = [];
                    }
                    // 兼容手动配置项
                    if (is_string($config['hooks'][$hook])) {
                        $config['hooks'][$hook] = explode(',', $config['hooks'][$hook]);
                    }
                    if (!in_array($name, $config['hooks'][$hook])) {
                        $config['hooks'][$hook][] = $name;
                    }
                }
            }
        }
        //插件配置信息保存到缓存
        Cache::set('addons_config',$config);
        //插件列表
        Cache::set('addons_data', $this->addons_data);
        //插件ini列表
        Cache::set('addons_data_list', $this->addons_data_list);
        //插件config列表
        Cache::set('addons_data_list_config', $this->addons_data_list_config);
        Config::set($config, 'addons');
    }

    /**
     * 获取 addons 路径
     * @return string
     */
    public function getAddonsPath()
    {
        // 初始化插件目录
        $addons_path = $this->app->getRootPath() . 'addons' . DS;
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
    public static function getSourceAssetsDir($name)
    {
        return Service::getAddonsNamePath($name) . 'public' . DS;
    }

    /**
     * 获取插件目标资源文件夹
     * @param string $name 插件名称
     * @return  string
     */
    public static function getDestAssetsDir($name)
    {
        $assetsDir = app()->getRootPath() . str_replace("/", DS, "public/static/{$name}");
        if (!is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }
        return $assetsDir;
    }

    //获取插件目录
    public static function getAddonsNamePath($name)
    {
        return app()->getRootPath() . 'addons' . DS . $name . DS;
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
        foreach (Service::getAppDir($name) as $k => $dir) {
            $sourcedir =  Service::getAddonsNamePath($name) .$dir. DS . $name;
            if (is_dir($sourcedir)) {
                FileHelper::copyDir($sourcedir, app()->getBasePath().DS.$name,$delete);
                if($delete) FileHelper::delDir(Service::getAddonsNamePath($name).$dir);
            }else{
                @copy($sourcedir, app()->getBasePath() .DS.$dir);
                if($delete) unlink($sourcedir);
            }
        }
        $sourceAssetsDir = Service::getSourceAssetsDir($name);
        $destAssetsDir = Service::getDestAssetsDir($name);
        if (is_dir($sourceAssetsDir)) {
            FileHelper::copyDir($sourceAssetsDir, $destAssetsDir,$delete);
            if($delete) FileHelper::delDir($sourceAssetsDir);
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
                    if($delete) unlink($sourcedir);
                }
            }
            @rmdir($appDir);
        }
        // 移除插件基础静态资源目录
        $destAssetsDir = Service::getDestAssetsDir($name);
        if (is_dir($destAssetsDir)) {
            FileHelper::copyDir($destAssetsDir,$addonPath.'public'.DS,$delete);
            if($delete) FileHelper::delDir($destAssetsDir);
        }
        //删除文件
        $list = Service::getGlobalAddonsFiles($name);
        foreach ($list as $k => $v) {
            @unlink(app()->getRootPath() . $v);
        }
    }
    /**
     * 获取检测的全局文件夹目录
     * @return  array
     */
    public static function getCheckDirs()
    {
        return [
            'public'
        ];
    }

    /**
     * 获取插件在全局的文件
     * @param int $onlyconflict 冲突
     * @param string $name 插件名称
     * @return  array
     */
    public static function getGlobalAddonsFiles($name, $onlyconflict = false)
    {
        $list = [];
        $addonDir = self::getAddonsNamePath($name);
        // 扫描插件目录是否有覆盖的文件
        foreach (self::getCheckDirs() as $k => $name) {
            $checkDir = app()->getRootPath() . DS . $name . DS;
            if (!is_dir($checkDir))
                continue;
            //检测到存在插件外目录
            if (is_dir($addonDir . $name)) {
                //匹配出所有的文件
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($addonDir . $name, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($files as $fileinfo) {
                    if ($fileinfo->isFile()) {
                        $filePath = $fileinfo->getPathName();
                        $path = str_replace($addonDir, '', $filePath);
                        if ($onlyconflict) {
                            $destPath = app()->getRootPath() . $path;
                            if (is_file($destPath)) {
                                if (filesize($filePath) != filesize($destPath) || md5_file($filePath) != md5_file($destPath)) {
                                    $list[] = $path;
                                }
                            }
                        } else {
                            $list[] = $path;
                        }
                    }
                }
            }
        }
        return $list;
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
