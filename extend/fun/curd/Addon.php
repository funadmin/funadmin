<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: https://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2020/9/21
 */

namespace fun\curd;

use app\backend\model\AuthRule;
use app\backend\service\AddonService;
use Exception;
use fun\helper\CtrHelper;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use fun\helper\FileHelper;
use fun\helper\ZipHelper;
use think\facade\Console;

/**
 * Class Curd
 * @package app\backend\command
 * 功能待完善
 */
class Addon extends Command
{
    protected $config = [];
    protected $controllerList = [];
    protected function configure()
    {
        $this->setName('addon')
            ->addOption('app', '', Option::VALUE_REQUIRED, '插件名', '')
            ->addOption('title', '', Option::VALUE_REQUIRED, '插件标题', '')
            ->addOption('description', '', Option::VALUE_OPTIONAL, '插件名', '')
            ->addOption('author', '', Option::VALUE_OPTIONAL, '插件作者', '')
            ->addOption('ver', '', Option::VALUE_OPTIONAL, '插件版本', '')
            ->addOption('requires', '', Option::VALUE_OPTIONAL, '插件需求版本', '')
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->addOption('min', '', Option::VALUE_OPTIONAL, '打包', 0)
            ->addOption('install', '', Option::VALUE_OPTIONAL, '安装', 0)
            ->addOption('uninstall', '', Option::VALUE_OPTIONAL, '卸载', 0)
            ->addOption('enable', '', Option::VALUE_OPTIONAL, '启用', 0)
            ->addOption('disable', '', Option::VALUE_OPTIONAL, '禁用', 0)
            ->setDescription('Addon Command');
    }

    protected function execute(Input $input, Output $output)
    {
        $param = [];
        $param['app'] = $input->getOption('app');
        $param['title'] = $input->getOption('title')?$input->getOption('title'):$param['app'];
        $param['description'] = $input->getOption('description')?$input->getOption('description'):$param['app'];
        $param['author'] = $input->getOption('author')?$input->getOption('author'):'FunAdmin';
        $param['version'] = $input->getOption('ver')?$input->getOption('ver'):'1.0.0';
        $param['requires'] = $input->getOption('requires')?$input->getOption('requires'):'1.0.0';
        $param['force'] = $input->getOption('force');//强制覆盖或删除
        $param['delete'] = $input->getOption('delete');
        $param['min'] = $input->getOption('min');
        $param['install'] = $input->getOption('install');
        $param['uninstall'] = $input->getOption('uninstall');
        $param['enable'] = $input->getOption('enable');
        $param['disable'] = $input->getOption('disable');
        $this->config = $param;
        if (empty($param['app'])) {
            $output->error("插件名不能为空");
            return false;
        }
        if($param['app']=='backend' || $param['app']=='common' || $param['app']=='frontend' || $param['app']=='api' || $param['app']=='install'){
            $output->error("插件名不能为backend或common或frontend或api或install");
            return false;
        }
        if($param['uninstall'] && !is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
            $output->error("插件目录不存在");
            return false;
        }
        if($param['enable'] && !is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
            $output->error("插件目录不存在");
            return false;
        }
        if($param['disable'] && !is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
            $output->error("插件目录不存在");
            return false;
        }
        try {
            if($param['enable'] && is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
                app(AddonService::class)->enableAddon($param['app']);
                $output->info("启用成功");
                return true;
            }
            if($param['disable'] && is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
                app(AddonService::class)->modifyAddon($param['app']);
                $output->info("禁用成功");
                return true;
            }
            if($param['uninstall'] && is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
                app(AddonService::class)->uninstallAddon($param['app']);
                $output->info("卸载成功");
                return true;
            }   
            if($param['install'] && !is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
                $output->error("插件目录不存在");
                return false;
            }
            if($param['install'] && is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
                app(AddonService::class)->installAddon($param['app'],'install');
                $output->info("安装成功");
                return true;
            }
            $tplPath = root_path('extend/fun/curd/tpl/addon');
            $addonPath = root_path(ADDON_DIR .'/'.$param['app']) ;
            $fileList = [
                [
                    'name'=>'Plugin.php',
                    'content'=>'',
                    'fileName'=>$addonPath . "Plugin.php",
                    'tpl'=> $tplPath . 'Plugin.tpl',
                    'items'=>[
                        ['name'=>'addon','value'=>$param['app']],
                        ['name'=>'addon_dir','value'=>ADDON_DIR],
                    ]
                ],
                [
                    'name'=>'plugin.ini',
                    'content'=>'',
                    'fileName'=>$addonPath . "plugin.ini",
                    'tpl'=> $tplPath . 'ini.tpl',
                    'items'=>[
                        ['name'=>'addon','value'=>$param['app']],
                        ['name'=>'addon_dir','value'=>ADDON_DIR],
                        ['name'=>'title','value'=>$param['title']],
                        ['name'=>'description','value'=>$param['description']],
                        ['name'=>'author','value'=>$param['author']],
                        ['name'=>'requires','value'=>$param['requires']],
                        ['name'=>'version','value'=>$param['version']],
                        ['name'=>'url','value'=> '/'. ADDON_DIR .'/'. $param['app']],
                        ['name'=>'time','value'=>date('Y-m-d H:i:s')],
                        ['name'=>'app','value'=>$param['app']],
                    ]
                ],
                [
                    'name'=>'config.php',
                    'content'=>'',
                    'fileName'=>$addonPath . "config.php",
                    'tpl'=> $tplPath . 'config.tpl',
                    'items'=>[
                    ]
                ],
                [
                    'name'=>'menu.php',
                    'content'=>'',
                    'fileName'=>$addonPath . "menu.php",
                    'tpl'=> $tplPath . 'menu.tpl',
                    'items'=>[]
                ],
                [
                    'name'=>'Index.php',
                    'content'=>'',
                    'fileName'=>$addonPath . "controller/Index.php",
                    'tpl'=> $tplPath . 'controller.tpl',
                    'items'=>[
                        ['name'=>'addon','value'=>$param['app']],
                    ]
                ],
                [
                    'name'=>'index.html',
                    'content'=>'',
                    'fileName'=>$addonPath . 'view/index/index.html',
                    'tpl'=> $tplPath . 'view.tpl',
                    'items'=>[
                        ['name'=>'addon','value'=>$param['app']],
                    ]
                ],
                [
                    'name'=>'plugin.js',
                    'content'=>'',
                    'fileName'=>$addonPath . 'plugin.js',
                    'tpl'=> $tplPath . 'js.tpl',
                    'items'=>[
                    ]
                ],
                [
                    'name'=>'install.sql',
                    'content'=>'',
                    'fileName'=>$addonPath . 'install.sql',
                    'tpl'=> $tplPath . 'sql.tpl',
                    'items'=>[
                    ]
                ],
                [
                    'name'=>'uninstall.sql',
                    'content'=>'',
                    'fileName'=>$addonPath . 'uninstall.sql',
                    'tpl'=> $tplPath . 'sql.tpl',
                    'items'=>[
                    ]
                ],
            ];
            if ($param['app']) {
                if($this->config['delete'] && $this->config['force']){
                    AuthRule::where('module', $param['app'])->force()->delete();
                }
                if(!$this->config['min']){
                    foreach ($fileList as $key => &$value) {
                        if($this->config['force'] && $this->config['delete']){
                            if(file_exists($value['fileName'])){
                                unlink($value['fileName']);
                            }
                            continue;
                        }
                        $items = $value['items'];
                        if($value['name']=='menu.php'){
                            $items = [['name'=>'menu','value'=>var_export($this->getMenu($param),true)]];
                            $replaceTpl = [];
                            $replace = [];
                            foreach ($items as $item){
                                $replaceTpl[] = '{%'.$item['name'].'%}';
                                $replace[] = $item['value'];
                            }
                            $value['content'] = str_replace( $replaceTpl, $replace, file_get_contents($value['tpl']));
                        }else{
                            if(empty($items)){
                                $value['content'] = file_get_contents($value['tpl']);
                            }else{
                                $replaceTpl = [];
                                $replace = [];
                                foreach ($items as $item){
                                    $replaceTpl[] = '{%'.$item['name'].'%}';
                                    $replace[] = $item['value'];
                                }
                                $value['content'] = str_replace( $replaceTpl, $replace, file_get_contents($value['tpl']));
                            }
                        }
                        $this->makeFile($value['fileName'], $value['content']);
                    }
                }else{
                    //把addons/cms 目录复制到临时目录runtime/cms_min，并把app/cms/复制到runtime/cms_min/app/cms
                    //把public/static/cms/目录复制到runtime/cms_min/public/static/cms
                    //把runtime/cms_min/plugin.ini内部 install改为0
                    $minPath = root_path('runtime/'.$param['app'].'_min_'.date('YmdHis'));
                    if(is_dir($minPath)){
                        FileHelper::delDir($minPath);
                    }
                    if(is_dir(root_path(ADDON_DIR .'/'.$param['app']))){
                        FileHelper::copyDir(root_path(ADDON_DIR .'/'.$param['app']), $minPath);
                    }else{
                        $output->error('插件目录不存在');
                        return false;
                    }
                    if(is_dir(app_path($param['app']))){
                        FileHelper::copyDir(app_path($param['app']), $minPath.'/app/'.$param['app']);
                    }
                    if(is_dir(public_path('static/'.$param['app']))){
                        FileHelper::copyDir(public_path('static/'.$param['app']), $minPath.'/public');
                    }
                    $pluginIni = $minPath.'/plugin.ini';
                    $pluginIniContent = file_get_contents($pluginIni);
                    $pluginIniContent = str_replace('install=1', 'install=0', $pluginIniContent);
                    file_put_contents($pluginIni, $pluginIniContent);
                    $zipFile = './public/'.$param['app'].'_min_'.date('YmdHis').'.zip';
                    // 确保runtime目录存在且有写权限
                    $runtimeDir = root_path('runtime');
                    if (!is_dir($runtimeDir)) {
                        mkdir($runtimeDir, 0755, true);
                    }
                    // 检查目录权限
                    if (!is_writable($runtimeDir)) {
                        $output->error('runtime目录没有写权限: ' . $runtimeDir);
                        return false;
                    }
                    try {
                        // 使用完整路径并确保目录存在
                        ZipHelper::zip($zipFile, $minPath);
                        // 检查压缩文件是否创建成功
                        if (file_exists($zipFile) && filesize($zipFile) > 0) {
                            $output->info('打包成功');
                            $output->info('打包文件路径：'.$zipFile);
                            $fileSize = filesize($zipFile);
                            $output->info('文件大小：' . ($fileSize > 1024 * 1024 ? round($fileSize / 1024 / 1024, 2) . 'MB' : round($fileSize / 1024, 2) . 'KB'));
                            // 清理临时目录
                            if (is_dir($minPath)) {
                                FileHelper::delDir($minPath);
                                $output->info('已清理临时目录：'.$minPath);
                            }
                        } else {
                            $output->error('打包失败：压缩文件未创建或为空');
                            return false;
                        }
                    } catch (\Exception $e) {
                        $output->error('打包异常: ' . $e->getMessage());
                        return false;
                    }
                }
            }
            $output->info('make success');
        }catch (\Exception $e){
            $output->writeln('----------------');
            $output->error($e->getMessage());
            $output->writeln('----------------');
        }
    }

    /**
     * @param $fileName
     * @param $data
     * @return true
     */
    public function makeFile($fileName,$data){
        $baseDir = dirname($fileName);
        if(!$this->config['force'] && file_exists($fileName)){
            return true;
        }
        if(!is_dir($baseDir)){
            mkdir($baseDir,0755,true);
        }
        file_put_contents($fileName, $data);
        return true;
    }
    /**
     *
     * 生成菜单
     * @param int $type
     */
    protected function getMenu(array $param): array
    {
        $controllers = CtrHelper::getControllersByApp($param['app']);
        $childMenu = [];
        foreach($controllers as $controller){
            $href = $controller['route_info'];
            $title = $controller['comment'];
            $menu = [
                'href' => $href,
                'title' => $title,
                'status' => 1,
                'type' => 1,
                'menu_status' => 1,
                'icon' => 'layui-icon layui-icon-app',
            ];
            foreach ($controller['methods'] as $item) {
                $menu['menulist'][] = [
                    'href' => $href . '/' . $item['name'],
                    'title' => $item['comment'],
                    'status' => 1,
                    'type' => 2,
                    'menu_status' => 0,
                ];
            }
            $childMenu[] = $menu;
        }
        
        $menuList = [
            'is_nav' => 1,//1导航栏；0 非导航栏
            'menu' => [ //菜单;
                'href' => ucfirst($this->config['app']) .'Manager',
                'title' => $this->config['title']?:ucfirst($this->config['app']) .'Manager',
                'status' => 1,
                'auth_verify' => 1,
                'type' => 1,
                'menu_status' => 1,
                'module' => $this->config['app'],
                'icon' => 'layui-icon layui-icon-app',
                'menulist' => [
                    $childMenu
                ]
            ]
        ];
        return $menuList;
    }

}
