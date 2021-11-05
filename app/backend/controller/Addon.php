<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */

namespace app\backend\controller;

use app\backend\service\AddonService;
use app\common\controller\Backend;
use app\common\service\AuthCloudService;
use fun\helper\FileHelper;
use fun\addons\Service;
use fun\helper\ZipHelper;
use think\App;
use think\facade\Cache;
use think\Exception;
use app\common\model\Addon as AddonModel;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="插件管理")
 * Class Addon
 * @package app\backend\controller
 */
class Addon extends Backend
{
    protected $addonService;
    protected $authCloudService;
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new AddonModel();
        $this->addonService = new AddonService();
        $this->authCloudService = AuthCloudService::instance();
    }
    /**
     * @NodeAnnotation(title="列表")
     * @return mixed|\think\response\Json|\think\response\View
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if($this->request->isPost()){
                //登录请求
                $data = $this->request->post();
                $this->authCloudService->setUserParams($data);
                $result = $this->authCloudService->setApiUrl('')->setMethod('post')
                    ->setParams($this->authCloudService->getUserParams())
                    ->run();
                if ($result['code'] == 200) {
                    $this->authCloudService->setAuth($result['data']);
                    $this->success(lang('login successful'));
                } else {
                    $this->error(lang('Login failed:' . $result['msg']));
                }
            }else{
                $param = $this->request->param();
                list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
                if($where){foreach($where as $k=>$v){$map[$v[0]] = trim($v[2],'%');}}
                $map['cateid'] = $param['cateid']??0;
                unset($map['status']);
                $auth = $this->authCloudService->setApiUrl('api/v1.plugins/index')->setMethod('GET')
                    ->setParams($map);
                if($this->authCloudService->getAuth()){
                    $res = $auth->setHeader()->run();
                }else{
                    $res = $auth->run();
                }
                $list = $res['data'];
                list($localAddons,$localNameArr) = $this->getLocalAddons();
                $addonNameArr = $list?array_keys($list):[];
                $where = [];
                if(isset($param['cateid']) && $param['cateid']){
                    $where[] = ['name','in',$addonNameArr];
                }
                if(empty($addonNameArr)){
                    $where[] = ['name','not in',$addonNameArr];
                }
                $addons =  $this->modelClass->where($where)->column('*', 'name');
                $list = array_merge($localAddons,$addons,$list?$list:[]);
                foreach ($list as $key => &$value) {
                    $value['plugins_id'] = isset($value['id'])?$value['id']:0;
                    unset($value['id']);
                    //是否已经安装过
                    if($localNameArr and in_array($key,$localNameArr)){
                        $config = get_addons_config($key);
                        if ($addons && !isset($addons[$key]) || !$addons) {
                            $class = get_addons_instance($key);
                            $addons["$key"] = $class->getInfo();
                            if ($addons[$key]) {
                                $addons[$key]['install'] = 0;
                                $addons[$key]['status'] = 0;
                            }
                            $addons[$key] = $value;
                        } else {
                            $addons[$key] = array_merge($value,$addons[$key]);
                            $addons[$key]['install'] = 1;
                        }
                        if(isset($config['domain']) && $config['domain']['value']){
                            $index = strpos($_SERVER['HTTP_HOST'],'.');
                            $url = substr_count($_SERVER['HTTP_HOST'],'.')>1?substr($_SERVER['HTTP_HOST'],$index+1):$_SERVER['HTTP_HOST'];
                            $addons[$key]['web'] = httpType().$config['domain']['value'].'.'.$url;
                        }else{
                            $addons[$key]['web'] = '/addons/'.$key;
                        }
                    }else{
                        $addons[$key]['insatll'] = 0;
                        $addons[$key]['status'] = 1;
                        $addons[$key] = $value;
                    }
                    if(isset($addons[$key]['pluginsVersion'])){
                        $addons[$key]['version_id'] = $addons[$key]['pluginsVersion'][0]['id'];
                    }else{
                        $addons[$key]['version_id'] = 0;
                    }
                }
                unset($value);
                $result = ['code' => 0, 'msg' => lang('Get Data Success'),
                    'data' => $addons, 'count' => count($addons)];
                return json($result);
            }
        }
        $res = $this->authCloudService->setApiUrl('api/v1.plugins/cateList')->setMethod('GET')
            ->setParams([])->run();
        $cateList = $res['data'];
        return view('',['auth'=>$this->authCloudService->getAuth()?1:0,'','cateList'=>$cateList]);
    }
    /**
     * @NodeAnnotation(title="安装")
     * @throws Exception
     */
    public function install($name='')
    {
        set_time_limit(0);
        $name = $this->request->param("name")??$name;
        $plugins_id = $this->request->param("plugins_id");
        $version_id = $this->request->param("version_id");
//        插件名是否为空
        if (!$name) {
            $this->error(lang('addon  %s can not be empty', [$name]));
        }
        //插件名是否符合规范
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(lang('addon name inright'));
        }
        //检查插件是否安装
        $list = $this->isInstall($name);
        if ($list and $list->status==1) {
            $this->error(lang('addons %s is already installed', [$name]));
        }
        list($addons,$localNameArr) = $this->getLocalAddons();
        if(!$localNameArr || !in_array($name,$localNameArr)){
            $params = [
                'plugins_id'=>$plugins_id,
                'name'=>$name,
                'version_id'=>$version_id,
                'version'=>'',
                "ip" => request()->ip(),
                "domain" => request()->domain(),
            ];
            $res = $this->authCloudService->setApiUrl('api/v1.plugins/down')->setMethod('GET')
                ->setParams($params)->setHeader()->setOptions()->run();
            if($res['code']!=200){
                $this->error($res['msg']);
            }
            $fileDir = '../runtime/addons/';
            if (!is_dir($fileDir)) {
                FileHelper::mkdirs($fileDir);
            }
            $content = file_get_contents($res['data']['file_url']);
            $fileName = $fileDir . $name . '.zip';
            @touch($fileName);
            file_put_contents($fileName, $content);
            ZipHelper::unzip($fileName, $file =  '../addons');
            @unlink($fileName);
        }
        $class = get_addons_instance($name);
        if (empty($class)) {
            $this->error(lang('addons %s is not ready', [$name]));
        }
        //安装插件
        $class->install();
        // 安装菜单
        $menu_config=$this->get_menu_config($class);
        if(!empty($menu_config)){
            if(isset($menu_config['is_nav']) && $menu_config['is_nav']==1){
                $pid = 0;
            }else{
                $pid = $this->addonService->addAddonManager()->id;
            }
            $menu[] = $menu_config['menu'];
            $this->addonService->addAddonMenu($menu,$pid);
        }
        $addon_info = get_addons_info($name);
        $addon_info['status'] = 1;
        if($list){
            $list->status=1;
            $res = $list->save();
        }else{
            $res =  $this->modelClass->save($addon_info);
        }
        if (!$res) {
            $this->error(lang('addon install fail'));
        }
        //添加数据库
        try{
            importsql($name);
        } catch (Exception $e){
            $this->error($e->getMessage());
        }
        $sourceAssetsDir = Service::getSourceAssetsDir($name);
        $destAssetsDir = Service::getDestAssetsDir($name);
        if (is_dir($sourceAssetsDir)) {
            FileHelper::copyDir($sourceAssetsDir, $destAssetsDir);
        }
        //复制文件到目录
        if(Service::getCheckDirs()){
            foreach (Service::getCheckDirs() as $k => $dir) {
                $sourcedir = Service::getAddonsNamePath($name). $dir;
                if (is_dir($sourcedir)) {
                    FileHelper::copyDir($sourcedir, app()->getRootPath().  $dir. DS .'static'.DS.'addons'.DS.$name);
                }
            }
        }
        try {
            Service::updateAddonsInfo($name);
            //刷新addon文件
            refreshaddons();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        Cache::clear();
        $this->success(lang('Install success'));
    }
    /**
     * @NodeAnnotation(title="离线安装")
     * @throws Exception
     */
    public function localinstall()
    {
        if($this->request->isAjax()){
            set_time_limit(0);
            $urls = parse_url(input('url'));
            $file = $urls['path']??'';
            if($file && file_exists('.'.$file)){
//
                try {
                    $res = ZipHelper::unzip('.'.$file,'../addons');
                }catch (\Exception $e){
                    $this->error($e->getMessage());
                }
                if($res){
                    $addon = substr($res,0,strpos($res, '/'));
                    $this->install($addon);
                }
                $this->success('upload success');
            }
        }
    }
    /**
     * @NodeAnnotation(title="卸载")
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function uninstall()
    {
        set_time_limit(0);
        $name = $this->request->param("name");
        if (!$name) {
            $this->error(lang(' addon name can not be empty'));
        }
        //插件名匹配
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(lang('addon name is not right'));
        }
        //获取插件信息
        $info =  $this->modelClass->where('name', $name)->find();
        if (empty($info)) {
            $this->error(lang('addon is not exist'));
        }
        if($info->status==1){
            $this->error(lang('Please disable addons %s first',[$name]));
        }
        if (!$info->delete()) {
            $this->error(lang('addon uninstall fail'));
        }
        //卸载插件
        $class = get_addons_instance($name);
        $class->uninstall();
        //删除菜单
        $menu_config=$this->get_menu_config($class);
        try {
            if(!empty($menu_config)){
                $menu[] = $menu_config['menu'];
                $this->addonService->delAddonMenu($menu);
            }
            //卸载sql;
            uninstallsql($name);
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
        // 移除插件基础资源目录
        $destAssetsDir = Service::getDestAssetsDir($name);
        if (is_dir($destAssetsDir)) {
            FileHelper::delDir($destAssetsDir);
        }
        //删除文件
        $list = Service::getGlobalAddonsFiles($name);
        foreach ($list as $k => $v) {
            @unlink(app()->getRootPath() . $v);
        }
        Service::updateAddonsInfo($name,1,0);
        try {
            //刷洗addon文件和配置
            refreshaddons();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        $this->success(lang('Uninstall successful'));
    }

    /**
     * @NodeAnnotation (title="禁用启用")
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function modify()
    {
        $name = $this->request->param("name");
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(lang('addon name is not right'));
        }
        $info =  $this->modelClass->where('name',$name)->find();
        $addoninfo = get_addons_info($name);
        $addoninfo['status'] = $addoninfo['status']?0:1;
        try {
            $info->status =$addoninfo['status'];
            Service::updateAddonsInfo($name,$addoninfo['status']);
            refreshaddons();
            $info->save();
            $class = get_addons_instance($name);
            $addoninfo['status']==1 ?$class->enabled():$class->disabled();
        }catch (\Exception $e){
            $this->error(lang($e->getMessage()));
        }
        $this->success(lang('operation success'));
    }

    /**
     * @NodeAnnotation (title="插件配置")
     * @return \think\response\View
     * @throws Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function config()
    {
        $name = $this->request->get("name");
        $id = $this->request->get("id");
        $one =  $this->modelClass->find($id);
        $config = get_addons_config($name);
        if ($this->request->isAjax()) {
            $params = $this->request->param('params/a',[],'trim');
            if ($params) {
                foreach ($config as $k => &$v) {
                    if (isset($params[$k])) {
                        if ($v['type'] == 'array') {
                            $arr = [];
                            $params[$k] = is_array($params[$k]) ? $params[$k] :[];
                            foreach ($params[$k]['key'] as $kk=>$vv){
                                $arr[$vv] =  $params[$k]['value'][$kk];
                            }
                            $params[$k] = $arr;
                            $value = $params[$k];
                            $v['content'] = $value;
                            $v['value'] = $value;
                        } else {
                            $value =  $params[$k];
                        }
                        $v['value'] = $value;
                    }
                }
                unset($v);
                $config_data = json_encode($config,JSON_UNESCAPED_UNICODE);
                if($one->save(['config'=>$config_data])){
                    set_addons_config($name,$config);
                    refreshaddons();
                    $this->success(lang('operation success'));
                }else{
                    $this->error(lang('operation failed'));
                }
            }
            $this->error(lang('addon can not be empty'));
        }
        if (!$name) {
            $this->error(lang('addon name can not be empty'));
        }
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(lang('addon name is not right'));
        }
        if (!$one) {
            $this->error(lang('addon config is not found'));
        }
        //模板引擎初始化
        $view = ['formData'=>$config,'title'=>$one->name];
        $configFile = app()->getRootPath() . 'addons' . DS . $name . DS . 'config.html';
        $viewFile = file_exists($configFile) ? $configFile : '';
        //重新加载引擎
        app()->view->engine()->layout($this->layout);
        return view($viewFile,$view);
    }

    /**
     * @NodeAnnotation (title="是否安装")
     * @param $name
     * @return array|false|\think\Model|null
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function isInstall($name)
    {
        if (empty($name)) {
            return false;
        }
        $addons =  $this->modelClass->where('name', $name)->find();
        return $addons;
    }
    /**
     * @param $class
     * @return mixed
     */
    protected function get_menu_config($class){
        $menu = $class->menu;
        return $menu;
    }

    /**
     * 获取插件列表
     * @return array
     */
    protected function getLocalAddons(){
        $list = get_addons_list();
        return [$list,array_keys($list)];
    }


}
