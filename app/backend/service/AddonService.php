<?php

/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\backend\service;

use app\backend\model\AuthRule;
use app\common\model\Addon;
use app\common\service\AbstractService;
use app\common\traits\Jump;
use fun\helper\SignHelper;
use think\App;
use think\db\exception\PDOException;
use think\Exception;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Request;
use think\facade\Session;
use fun\addons\Service;

class AddonService extends AbstractService
{
    use Jump;

    protected $myaddon = 'myaddon';
    public function __construct()
    {
        parent::__construct();

    }
    //添加菜单
    public function addAddonMenu(array $menu,int $pid = 0,string $module = 'backend'){
        foreach ($menu as $v){
            $hasChild = isset($v['menulist']) && $v['menulist'] ? true : false;
            try {
                $v['pid'] = $pid ;
                $v['href'] = trim($v['href'],'/');
                $v['module'] = $module;
                $menu = AuthRule::withTrashed()->where('href',$v['href'])->where('module',$module)->find();
                if($menu){
                    $menu->restore();
                }else{
                    $menu = AuthRule::create($v);
                }
                if ($hasChild) {
                    $this->addAddonMenu($v['menulist'], $menu->id,$module);
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
        $this->delMenuCache();
    }
    //循环删除菜单
    public function delAddonMenu(array $menu,string $module = 'backend'){
        foreach ($menu as $k=>$v){
            $hasChild = isset($v['menulist']) && $v['menulist'] ? true : false;
            try {
                $v['href'] = trim($v['href'],'/');
                $menu_rule = AuthRule::withTrashed()->where('href',$v['href'])->where('module',$module)->find();
                if($menu_rule){
                    $menu_rule->force()->delete();
                    if ($hasChild) {
                        $this->delAddonMenu($v['menulist'],$module);
                    }
                }
                //删除主菜单；
                $manager = AuthRule::withTrashed()->where('href',$this->myaddon)->find();
                if($manager){
                    $manager_child =  AuthRule::withTrashed()->where('pid',$manager->id)->find();
                    if(!$manager_child){
                        $manager->force()->delete();
                    }
                }
            } catch (Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
        $this->delMenuCache();

    }
    //添加管理菜单
    public function addAddonManager(){
        $data = array(
            "title" => '已装插件',
            'href' => $this->myaddon,
            'menu_status' => 1,
            'type' => 1,//1菜单
            //状态，1是显示，0是不显示
            "status" => 1,
            "icon" => 'layui-icon layui-icon-app',
            //父ID
            "pid" => 0,
            //排序
            "sort" => 50,
        );
        $manager = AuthRule::where('href',$this->myaddon)->find();
        if(!$manager){
            $manager = AuthRule::create($data);
        }elseif($manager && $manager->menu_status==0){
            $manager->menu_status=1;
            $manager->status=1;
            $manager->save();
        }
        $this->delMenuCache();
        return $manager;
    }
    /**
     * 删除菜单配置缓存
     */
    public function delMenuCache(){

        Cache::delete('initMenuConfig' . session('admin.id'));
        Cache::delete('adminMenus_' . session('admin.id'));

    }
    /**
     * 安装插件
     * @param string $name
     * @param string $type
     * @return bool
     */
    public function installAddon(string $name,string $type=''){
        $class = get_addons_instance($name);
        if (empty($class)) {
            throw new Exception(lang('addons %s is not ready', [$name]));
        }
        $addon_info = get_addons_info($name);
        if(!empty($addon_info['depend'])){
            $depend = explode(',',$addon_info['depend']);
            foreach ($depend as $v) {
                $dependAddon  = get_addons_info($v);
                if(empty($dependAddon)){
                    throw new Exception('Please install the dependent plugin first: '.$addon_info['depend']);
                }
            }
        }
        //添加数据库
        try{
            if($type!='upgrade'){
                importsql($name);
            }else{
                $sqlFile = root_path().'addons/'.$name.'/'.'upgrade.sql';
                if(!file_exists($sqlFile)){
                    $sqlFile = root_path().'addons/'.$name.'/'.'update.sql';
                }
                if(file_exists($sqlFile)){
                    importsql($name,$sqlFile);
                }
            }
        } catch (Exception $e){
            throw new Exception($e->getMessage());
        }

        // 安装菜单
        $menu_config = get_addons_menu($name);
        if(!empty($menu_config)){
            list($menu,$pid) = $this->getMenu($menu_config);
            $this->addAddonMenu($menu,$pid,$name);
        }
        $model = new Addon();
        //安装插件
        $class->install();
        $addon_info['status'] = 1;
        $list = $this->isInstall($name);
        if($list){
            if($list->delete_time > 0){
                $model->restore(['id'=>$list->id]);
            }
            $res = $model->update(['status'=>1],['id'=>$list->id]);
        }else{
            $res =  $model->save($addon_info);
        }
        if (!$res) {
            throw new Exception(lang('addon install fail'));
        }
        Service::copyApp($name,$delete = true);
        try {
            Service::updateAddonsInfo($name);
            //刷新addon文件
            refreshaddons();
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
        return true;
    }
    public function uninstallAddon(string $name){
        try {
            if (!$name) {
                throw new Exception(lang(' addon name can not be empty'));
            }
            //插件名匹配
            if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
                throw new Exception(lang('addon name is not right'));
            }
            $model = new Addon();
            //获取插件信息
            $info =  $model->withTrashed()->where('name', $name)->find();
            if (empty($info)) {
                throw new Exception(lang('addon is not exist'));
            }
            if($info->status==1){
                throw new Exception(lang('Please disable addons %s first',[$name]));
            }
            if (!$info->force()->delete()) {
                throw new Exception(lang('addon uninstall fail'));
            }
            //卸载插件
            $class = get_addons_instance($name);
            $class->uninstall();
            //删除菜单
            $menu_config=get_addons_menu($name);
                if(!empty($menu_config)){
                    list($menu,$pid) = $this->getMenu($menu_config);
                    $this->delAddonMenu($menu,$name);
                }
                //卸载sql;
                uninstallsql($name);
            //还原文件
            Service::removeApp($name,$delete= true);
            Service::updateAddonsInfo($name,1,0);
            refreshaddons();
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
        return true;

    }
    /**
     * @NodeAnnotation (title="是否安装")
     * @param $name
     * @return array|false|\think\Model|null
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
     */
    public function isInstall($name)
    {
        if (empty($name)) {
            return false;
        }
        $addons =  Addon::withTrashed()->where('name', $name)->find();
        return $addons;
    }
    /**
     * 获取菜单
     * @param mixed $config
     * @return array<array|int|mixed>
     */
    public function getMenu($config = [])
    {
        $is_nav = $config['is_nav']??1;
        $menuArr = $config['menu'];
        $menu = [];
        if(!empty($menuArr[0]) && is_array($menuArr[0])){
            foreach ($menuArr as $value) {
                if($is_nav==-1){
                    $menu = array_merge($menu,$value['menulist']);
                    $pid = 0;
                }elseif($is_nav==0){
                    $menu[] = $value;
                    $pid = $this->addAddonManager()->id;
                }else{
                    $menu[] = $value;
                    $pid = 0;
                }
            }
        }else{
            if($is_nav==-1){
                $menu = array_merge($menu,$menuArr['menulist']);
                $pid = 0;
            }elseif($is_nav==0){
                $menu[] = $menuArr;
                $pid = $this->addAddonManager()->id;
            }else{
                $menu[] = $menuArr;
                $pid = 0;
            }
        }
        return [$menu,$pid];
    }
    /**
     * 修改插件状态
     * @param string $name
     * @return void
     */
    public function modifyAddon(string $name){
        $info =  Addon::where('name',$name)->find();
        $addoninfo = get_addons_info($name);
        $addoninfo['status'] = $addoninfo['status']?0:1;
        try {
            $info->status =$addoninfo['status'];
            Service::updateAddonsInfo($name,$addoninfo['status']);
            // 安装菜单
            $class = get_addons_instance($name);
            $menu_config = get_addons_menu($name);
            if(!empty($menu_config)){
                list($menu,$pid) = $this->getMenu($menu_config);
                if( $addoninfo['status']){
                    $this->addAddonMenu($menu,$pid,$name);
                }else{
                    $this->delAddonMenu($menu,$name);
                }
            }
            refreshaddons();
            $info->save();
            $addoninfo['status']==1 ?$class->enabled():$class->disabled();
        }catch (Exception $e){
            throw new Exception($e->getMessage());
        }
    }
}
