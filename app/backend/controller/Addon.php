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

use app\backend\middleware\CheckRole;
use app\backend\middleware\SystemLog;
use app\backend\middleware\ViewNode;
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
use think\facade\Console;
use think\facade\Cookie;

/**
 * @ControllerAnnotation(title="插件管理")
 * Class Addon
 * @package app\backend\controller
 */
class Addon extends Backend
{

    protected $middleware = [
        CheckRole::class =>['except'=>['enlang','verify','logout']],
        ViewNode::class,
        SystemLog::class
    ];
    protected $addonService;
    protected $authCloudService;
    protected $app_version;
    public function __construct(App $app)
    {

        parent::__construct($app);
        $this->modelClass = new AddonModel();
        $this->addonService = new AddonService();
        $this->authCloudService = AuthCloudService::instance();
        $this->app_version = config('funadmin.version');
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
                    $member = $this->authCloudService->setApiUrl('api/v1.member/get')->setMethod('get')
                        ->setParams([])->setHeader([$this->authCloudService->authorization=>$result['data']['access_token']])->run();
                    $this->authCloudService->setMember($member['data']);
                    $this->success(lang('login successful'),'',$member['data']);
                } else {
                    $this->error(lang('Login failed:' . $result['msg']));
                }
            }else{
                $param = $this->request->param();
                list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
                if($where){foreach($where as $k=>$v){$map[$v[0]] = trim($v[2],'%');}}
                $map['cateid'] = $param['cateid']??0;
                $map['app_version'] = $this->app_version;
                unset($map['status']);
                $auth = $this->authCloudService->setApiUrl('api/v1.plugins/index')->setMethod('GET')
                    ->setParams($map);
                if($this->authCloudService->getAuth()){
                    $res = $auth->setHeader()->run();
                }else{
                    $res = $auth->run();
                }
                $list = [];
                if($res['code']==200){
                    $list = $res['data'];
                }else if($res['code']==401){
                    Cookie::set('auth_account','');
                }
                list($localAddons,$localNameArr) = $this->getLocalAddons();
                $addonNameArr = $list?array_keys($list):[];
                $where = [];
                if(isset($param['cateid']) && $param['cateid']){
                    $where[] = ['name','in',$addonNameArr];
                }
                if(empty($addonNameArr)){
                    $where[] = ['name','not in',$addonNameArr];
                }
                try {
                    $addons =  $this->modelClass->where($where)->where('name','<>','')->column('*', 'name');
                    $list = array_merge($localAddons,$addons,$list?$list:[]);
                    foreach ($list as $key => &$value) {
                        $value['plugins_id'] = isset($value['id'])?$value['id']:0;
                        unset($value['id']);
                        //是否已经安装过
                        if($localNameArr && in_array($key,$localNameArr)){
                            $config = get_addons_config($key);
                            $info = get_addons_info($key);
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
                            $addons[$key]['localVersion'] = $info['version'];
                            if(isset($config['domain']) && $config['domain']['value']){
                                $index = strpos($_SERVER['HTTP_HOST'],'.');
                                $domain = explode(',', $config['domain']['value'])[0];
                                $url = substr_count($_SERVER['HTTP_HOST'],'.')>1?substr($_SERVER['HTTP_HOST'],$index+1):$_SERVER['HTTP_HOST'];
                                $addons[$key]['web'] = httpType().$domain.'.'.$url;
                            }else{
                                $addons[$key]['web'] = $info['url'];
                            }
                        }else{
                            $addons[$key] = $value;
                            $addons[$key]['insatll'] = 0;
                            $addons[$key]['status'] = 1;
                            $addons[$key]['localVersion'] = empty($value['pluginsVersion'])?1:$value['pluginsVersion'][0]['id'];
                        }
                        if(!empty($addons[$key]['pluginsVersion'])){
                            $addons[$key]['version_id'] = $addons[$key]['pluginsVersion'][0]['id'];
                            $addons[$key]['lastVersion'] = $addons[$key]['pluginsVersion'][0]['version'];
                        }else{
                            $addons[$key]['version_id'] = 0;
                            $addons[$key]['lastVersion'] = $addons[$key]['localVersion'];
                        }
                    }
                    unset($value);
                    $result = ['code' => 0, 'msg' => lang('Get Data Success'),
                        'data' => $addons, 'count' => count($addons)];
                }catch (\Exception $e){
                    $this->error($e->getMessage());
                }
                return json($result);
            }
        }
        $res = $this->authCloudService->setApiUrl('api/v1.plugins/cateList')->setMethod('GET')
            ->setParams([])->run();
        $cateList = $res['data'];
        $account = $this->authCloudService->getMember();
        return view('',[
            'auth'=>$account?1:0, 'account'=>$account,'cateList'=>$cateList]);
    }

    /**
     *創建插件
     * @return \think\response\View
     */
    public function add(){
        if($this->request->isAjax()){
            $post = $this->request->post();
            $arr = [];
            if(is_numeric($post['name']))  $this->error(lang('The plugin name cannot be a number'));

            foreach ($post as $k => $v) {
                if ($k == '__token__') continue;
                if ($v === '') continue;
                if (is_array($v)) {
                    foreach ($v as $kk => $vv) {
                        $arr[] = ['--' . $k, $vv];
                    }
                } else {
                    $arr[] = ['--' . $k, $v];
                }
            }
            $result = [];
            array_walk_recursive($arr, function ($value) use (&$result) {
                array_push($result, $value);
            });
            $output = Console::call('addon', $result);
            $content = $output->fetch();
            if (strpos($content, 'success')) {
                $this->success(lang('make success'));
            }
            $this->error($content);
        }
        return view();
    }
    /**
     * @NodeAnnotation(title="安装")
     * @throws Exception
     */
    public function install($name='',$type='')
    {
        set_time_limit(0);
        $name = $this->request->param("name")??$name;
        $plugins_id = $this->request->param("plugins_id");
        $version_id = $this->request->param("version_id");
        $type = $this->request->param("type")??$type;
//        插件名是否为空
        if (!$name) {
            $this->error(lang('addon  %s can not be empty', [$name]));
        }
        //插件名是否符合规范
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(lang('addon name is not right'));
        }
        if($type =='upgrade'){
            $this->upgrade();
        }
        //检查插件是否安装
        $list = $this->isInstall($name);
        if ($list && $list->status==1) {
            $this->error(lang('addons %s is already installed', [$name]));
        }
        list($addons,$localNameArr) = $this->getLocalAddons();
        //本地存在空和更新则请求后端
        if(empty($type) || $type=='upgrade'){
            //不存在或者
            $params = [
                'plugins_id'=>$plugins_id,
                'name'=>$name,
                'version_id'=>$version_id,
                'version'=> '',
                'app_version'=>$this->app_version,
                "ip" => request()->ip(),
                "domain" => request()->domain(),
            ];
            if(!$localNameArr || !in_array($name,$localNameArr) || !isset($addons[$name])
            ){
                $this->getCloundAddons($params);
            }
            if($type =='upgrade'){
                $this->getCloundAddons($params);
            }
        }
        $class = get_addons_instance($name);
        if (empty($class)) {
            $this->error(lang('addons %s is not ready', [$name]));
        }
        //添加数据库
        try{
            if($type!='upgrade'){
                importsql($name);
            }
        } catch (Exception $e){
            $this->error($e->getMessage());
        }
        // 安装菜单
        $menu_config=get_addons_menu($name);
        if(!empty($menu_config)){
            if(isset($menu_config['is_nav']) && $menu_config['is_nav']==1){
                $pid = 0;
            }else{
                $pid = $this->addonService->addAddonManager()->id;
            }
            $menu[] = $menu_config['menu'];
            $this->addonService->addAddonMenu($menu,$pid,$name);
        }
        //安装插件
        $class->install();
        $addon_info = get_addons_info($name);
        $addon_info['status'] = 1;
        if($list){
            if($list->delete_time > 0){
                $this->modelClass->restore(['id'=>$list->id]);
            }
            $res = $this->modelClass->update(['status'=>1],['id'=>$list->id]);
        }else{
            $res =  $this->modelClass->save($addon_info);
        }
        if (!$res) {
            $this->error(lang('addon install fail'));
        }
        Service::copyApp($name,$delete = true);
        //复制文件到目录
        if(Service::getCheckDirs()){
            foreach (Service::getCheckDirs() as $k => $dir) {
                $sourcedir = Service::getAddonsNamePath($name). $dir;
                if (is_dir($sourcedir)) {
                    FileHelper::copyDir($sourcedir, app()->getRootPath().  $dir. DS .'static'.DS.'addons'.DS.$name,true);
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
                try {
                    $res = ZipHelper::unzip('.'.$file,'../addons');
                }catch (\Exception $e){
                    $this->error($e->getMessage());
                }
                if($res){
                    $index = strpos($res, '/');
                    $addon = $index ? substr($res,0,$index):$res;
                    $this->install($addon,'local');
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
        $info =  $this->modelClass->withTrashed()->where('name', $name)->find();
        if (empty($info)) {
            $this->error(lang('addon is not exist'));
        }
        if($info->status==1){
            $this->error(lang('Please disable addons %s first',[$name]));
        }
        if (!$info->delete(true)) {
            $this->error(lang('addon uninstall fail'));
        }
        //卸载插件
        $class = get_addons_instance($name);
        $class->uninstall();
        //删除菜单
        $menu_config=get_addons_menu($name);
        try {
            if(!empty($menu_config)){
                $menu[] = $menu_config['menu'];
                $this->addonService->delAddonMenu($menu,$name);
            }
            //卸载sql;
            uninstallsql($name);
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
        //还原文件
        Service::removeApp($name,$delete= true);
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
            // 安装菜单
            $class = get_addons_instance($name);
            $menu_config = get_addons_menu($name);
            if(!empty($menu_config)){
                if(isset($menu_config['is_nav']) && $menu_config['is_nav']==1){
                    $pid = 0;
                }else{
                    $pid = $this->addonService->addAddonManager()->id;
                }
                $menu[] = $menu_config['menu'];
                if( $addoninfo['status']){
                    $this->addonService->addAddonMenu($menu,$pid,$name);
                }else{
                    $this->addonService->delAddonMenu($menu,$name);
                }
            }
            refreshaddons();
            $info->save();
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
        $addons =  $this->modelClass->withTrashed()->where('name', $name)->find();
        return $addons;
    }

    /**
     * 获取插件列表
     * @return array
     */
    protected function getLocalAddons(){
        Cache::clear();
        $list = get_addons_list();
        return [$list,array_keys($list)];
    }

    /**
     * 更新 先卸载插件
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function upgrade()
    {
        set_time_limit(0);
        $name = $this->request->param("name");
        //获取插件信息
        $info =  $this->modelClass->withTrashed()->where('name', $name)->find();
        if($info && $info->status==1){
            $this->error(lang('Please disable addons %s first',[$name]));
        }
        if ($info && !$info->delete(true)) {
            $this->error(lang('addon uninstall fail'));
        }
        //卸载插件
        $class = get_addons_instance($name);
        $class->uninstall();
        //删除菜单
        $menu_config=get_addons_menu($name);
        try {
            if(!empty($menu_config)){
                $menu[] = $menu_config['menu'];
                $this->addonService->delAddonMenu($menu,$name);
            }
            //为了防止文件误删，这里先不卸载sql
//            uninstallsql($name);
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
        $sql = root_path().'addons/'.$name.'/'.'upgrade.sql';
        if(file_exists($sql)){
            importSqlData($sql);
        }
        //为了防止文件误删，这里先不删除文件
//        // 移除插件基础资源目录
//        $destAssetsDir = Service::getDestAssetsDir($name);
//        if (is_dir($destAssetsDir)) {
//            FileHelper::delDir($destAssetsDir);
//        }
//        //删除文件
//        $list = Service::getGlobalAddonsFiles($name);
//        foreach ($list as $k => $v) {
//            @unlink(app()->getRootPath() . $v);
//        }
        Service::updateAddonsInfo($name,1,0);
        try {
            //刷新addon文件和配置
            refreshaddons();
        }catch (\Exception $e){
            $this->error($e->getMessage());
        }
        return true;
    }

    /**
     * 获取远程安装包
     * @param $params
     * @return void
     * @throws \Exception
     */
    protected function getCloundAddons($params){
        $res = $this->authCloudService->setApiUrl('api/v1.plugins/down')->setMethod('GET')
            ->setParams($params)->setHeader()->setOptions()->run();
        if($res['code'] == 401){
            Cookie::delete('auth_account');
            $this->error(lang('please login aigin'));
        }
        if($res['code']!=200){
            $url = '';
            if(!empty($res['data']['url'])) {
                $url = $res['data']['url'];
            }
            $this->error($res['msg'],$url);
        }
        $fileDir = '../runtime/addons/';
        if (!is_dir($fileDir)) {
            FileHelper::mkdirs($fileDir);
        }
        $content = file_get_contents($res['data']['file_url']);
        $fileName = $fileDir . $params['name'] . '.zip';
        @touch($fileName);
        file_put_contents($fileName, $content);
        ZipHelper::unzip($fileName, $file =  '../addons');
        @unlink($fileName);

    }

    /**
     * 退出云平台
     * @return void
     */
    public function logout(){
        Cookie::delete('auth_account');
        Cookie::delete('clound_account');
        $this->success(lang('logout success'));

    }
}
