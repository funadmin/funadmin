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
use app\common\annotation\ControllerAnnotation;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\helper\Str;
use Doctrine\Common\Annotations\AnnotationReader;
use fun\helper\CtrHelper;
use app\backend\service\AddonService;
/**
 * Class Menu
 * @package app\backend\command
 * 功能待完善
 */
class Menu extends Command
{
    protected $config;
    protected $appList = ['backend','frontend','api'];
    protected $sysController = [
        'auth.Admin',
        'auth.Auth',
        'auth.AuthGroup',
        'member.Member',
        'member.MemberLevel',
        'member.MemberGroup',
        'sys.Adminlog',
        'sys.Attach',
        'sys.AttachGroup',
        'sys.Config',
        'sys.ConfigGroup',
        'sys.Blacklist',
        'sys.Language',
        'sys.Upgrade',
        'Addon',
        'Ajax',
        'Error',
        'Index',
        'Login',

    ];
    protected function configure()
    {
        $this->setName('menu')
            ->addOption('controller', 'c', Option::VALUE_OPTIONAL, '控制器名', null)
            ->addOption('app', '', Option::VALUE_OPTIONAL, 'app', 'backend')
            ->addOption('menuname', '', Option::VALUE_OPTIONAL, '菜单名称', null)
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, '强制覆盖或删除', 0)
            ->addOption('delete', 'd', Option::VALUE_OPTIONAL, '删除', 0)
            ->setDescription('Menu Command');
    }

    protected function execute(Input $input, Output $output)
    {
        $param = [];
        $param['controller'] = $input->getOption('controller');
        $param['app'] = $input->getOption('app');
        $param['force'] = $input->getOption('force');//强制覆盖或删除
        $param['delete'] = $input->getOption('delete');
        $param['menuname'] = $input->getOption('menuname');
        $this->config = $param;
        if ($param['app']=='backend' && in_array($param['controller'], $this->sysController)) {
            $output->error("{$param['controller']}系统控制器不能生成");
            return false;
        }
        if(empty($param['controller']) && $param['app']=='backend'){
            $output->error("Backend应用控制器不能为空");
            return false;
        }
        if(empty($param['controller']) && $param['app']!='backend'){
            if($param['force'] && $param['delete']){
                $ids = AuthRule::where('module', $param['app'])->column('id');
                if(!empty($ids)){
                    AuthRule::destroy($ids, true); // 第二个参数true表示真实删除
                }
                $output->info('delete success');
                return true;
            }
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
            // 构建主菜单项
            $mainMenu = [
                'href' => ucfirst($this->config['app']) .'Manager',
                'title' => ucfirst($this->config['app']) .'Manager',
                'status' => 1,
                'auth_verify' => 1,
                'type' => 1,
                'menu_status' => 1,
                'module' => $this->config['app'],
                'icon' => 'layui-icon layui-icon-app',
                'menulist' => $childMenu // 直接使用 $childMenu 数组，不要额外包装
            ];
            
            // 只传递菜单项数组给addAddonMenu方法
            $addonService = new AddonService();
            $addonService->addAddonMenu([$mainMenu], 0, $param['app']);
        }else{
            if($param['force'] && $param['delete']){
                $href = str_replace('/', '.', $param['controller']);
                $topRule = AuthRule::where('module', $param['app'])
                ->where('href','like','%'.$href.'%')
                ->where('pid',0)
                ->find();
                if($topRule){
                    $topRule->force()->delete();
                    AuthRule::where('pid',$topRule['id'])->force()->delete();
                }
                $output->info('delete success');
                return true;
            }
            $param['controller'] = str_replace('.', '/', $param['controller']);
            $controllerParts = explode('/', $param['controller']);
            $controllerName = ucfirst(array_pop($controllerParts)); // 获取最后一个斜杠后的字符串并首字母大写
            //合并$controllerName 和$param['controller']
            $controllerParts[] = $controllerName;
            $filePath = app_path($param['app'].'/controller').implode(DS, $controllerParts).'.php';
            try {
                $controllers  = CtrHelper::analyzeController($param['app'],$filePath);
                // 合并href、module、query为独立键
                $menu = [
                    'href' => $controllers['route_info'],
                    'title' => $controllers['comment'],
                    'module' => $param['app'],
                    'status' => 1,
                    'menu_status' => 1,
                    'type' => 1,
                    'auth_verify'=>1,
                    'icon' => 'layui-icon layui-icon-app',
                    'pid' => 0,
                ];
                /** @var AuthRule $parent_auth */
                $parent_auth = AuthRule::create($menu);
                foreach($controllers['methods'] as $key=>$value){
                    $menu= [
                        'href' => $controllers['route_info'].'/'.$value['name'],
                        'title' => $value['comment'],
                        'module' => $param['app'],
                        'status' => 1,
                        'menu_status' => 0,
                        'type' => 2,
                        'auth_verify'=>1,
                        'icon' => 'layui-icon layui-icon-app',
                        'pid' => $parent_auth->id,
                    ];
                    AuthRule::create($menu);
                }
                    
            } catch (\Exception $e) {
                $output->error($e->getMessage());
            }
        }
        
        $output->info('make success');
    }

}
