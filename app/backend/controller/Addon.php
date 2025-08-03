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
use think\facade\Console;
use think\facade\Cookie;
/**
 * @ControllerAnnotation(title="插件管理")
 * Class Addon
 * @package app\backend\controller
 */
class Addon extends Backend
{

    protected array $noNeedLogin = ['enlang','verify','logout'];
    /**
     * @var AddonService 
     */
    protected AddonService $addonService;
    /**
     * @var AuthCloudService
     */
    protected AuthCloudService $authCloudService;
    protected mixed $app_version;
    public function __construct(App $app)
    {

        parent::__construct($app);
        $this->modelClass = new AddonModel();
        $this->addonService = app(AddonService::class);
        $this->authCloudService = app(AuthCloudService::class);
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
                try {
                    $data = $this->request->post();
                    // 获取访问令牌
                    $tokenResult = $this->authCloudService->getAccessToken($data);
                    // 获取用户信息
                    $member = $this->authCloudService->getMemberInfo($tokenResult['access_token']);
                    // 设置用户信息并返回成功
                    $this->authCloudService->setMember($member);
                } catch (Exception $e) {
                    $this->error(lang('Login failed:' . $e->getMessage()));
                }
                $this->success( lang( 'login successful'),'',$member);

            }else{
                $param = input();
                list($this->page, $this->pageSize,$sort,$where) = $this->buildParames();
                if($where){foreach($where as $k=>$v){$map[$v[0]] = trim($v[2],'%');}}
                if(empty($param['cateid'])){
                    $map['cateid'] = 0;
                }elseif(is_numeric($param['cateid'])){
                    $map['cateid'] = $param['cateid'];
                }
                $map['app_version'] = $this->app_version;
                $map['page'] = $param['page'];
                $map['limit'] = $param['limit'];
                unset($map['status']);
                $res = $this->authCloudService
                    ->setApiUrl('/api/v2.plugins/getList')
                    ->setParams($map)
                    ->setHeader()
                    ->run();
                $list = [];
                $addonNameArr = [];
                $addonNameArrAll = [];
                $count = 1;
                if (isset($res['code']) && $res['code'] == 200) {
                    $list = $res['data']['list'];
                    $allList = $res['data']['allList'];
                    $addonNameArr = $res['data']['searchNameList'];
                    $addonNameArrAll = $res['data']['nameList'];
                    $count = count($addonNameArr);
                }else if(isset($res['code']) && $res['code']==401){
                    $this->authCloudService->setToken()->setMember();
                }
                list($localAddons,$localNameArr) = $this->getLocalAddons();
                try {
                    $addonsInstalled =  $this->modelClass->where($where)->where('name','<>','')->column('*', 'name');
                    //$list = array_merge($localAddons,$addons,$list?$list:[]);
                    if(!empty($param['cateid']) && $param['cateid'] == 'local'){
                        $list= $localAddons;
                        foreach ($list as $key=>$item) {
                            if(in_array($key,$addonNameArrAll)) {
                                unset($list[$key]);
                            }
                        }
                        $count = 1;
                    }elseif(!empty($param['cateid']) && $param['cateid'] =='installed'){
                        $list= $addonsInstalled;
                        $count =1;
                    }
                    $addons = [];
                    foreach ($list as $key => &$value) {
                        if(in_array($key,$addonNameArrAll)){
                            $value = $allList[$key];
                        }
                        $value['plugins_id'] = isset($value['id'])?$value['id']:0;
                        unset($value['id']);
                        //是否已经安装过
                        if($localNameArr && in_array($key,$localNameArr)){
                            $config = get_addons_config($key);
                            $info = get_addons_info($key);
                            if (empty($addonsInstalled[$key])) {
                                $class = get_addons_instance($key);
                                $addons["$key"] = $class->getInfo();
                                if ($addons[$key]) {
                                    $addons[$key]['install'] = 0;
                                    $addons[$key]['status'] = 1;
                                }
                                $addons[$key] = $value;
                            } else {
                                $addons[$key] = array_merge($value,$addonsInstalled[$key]);
                                $addons[$key]['install'] = 1;
                            }
                            $addons[$key]['localVersion'] = $info['version'];
//                            if(isset($config['domain']) && $config['domain']['value']){
//                                $index = strpos($_SERVER['HTTP_HOST'],'.');
//                                $domain = explode(',', $config['domain']['value'])[0];
//                                $url = substr_count($_SERVER['HTTP_HOST'],'.')>1?substr($_SERVER['HTTP_HOST'],$index+1):$_SERVER['HTTP_HOST'];
////                                $addons[$key]['web'] = httpType().$domain.'.'.$url;
//                            }else{
////                                $addons[$key]['web'] =(string) addons_url($info['url']);
//                            }
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
                        'data' => $addons, 'count' => $count];
                }catch (Exception $e){
                    $this->error($e->getMessage());
                }
                return json($result);
            }
        }
        $res = $this->authCloudService
            ->setApiUrl('/api/v2.plugins/cateList')
            ->setParams([])->run();
        $cateList = $res['data']??[];
        $account = $this->authCloudService->getMember();
        return view('',['auth'=>$account?1:0, 'account'=>$account,'cateList'=>$cateList]);
    }

    /**
     *創建插件
     * @return \think\response\View
     */
    public function add(){
        if($this->request->isAjax()){
            $post = $this->request->post();
            $arr = [];
            if(is_numeric($post['app']))  $this->error(lang('The plugin name cannot be a number'));

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
    public function install(string $name='',string $type='')
    {
        set_time_limit(0);
        $name = input("name")??$name;
        $plugins_id = input("plugins_id",0);
        $version_id = input("version_id",0);
        $type = input("type")??$type;
//        插件名是否为空
        if (!$name) {
            $this->error(lang('addon  %s can not be empty', [$name]));
        }
        //插件名是否符合规范
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(lang('addon name is not right'));
        }
        if($type =='upgrade'){
            if(!$this->doUpgrade($name)){
                $this->error('upgrade failed');
            };
        }
        if( $this->doInstall( $name, $plugins_id, $version_id, $type)){
            $this->success('install success');
        }
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
                }catch (Exception $e){
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
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
     */
    public function uninstall()
    {
        set_time_limit(0);
        $name = input("name");
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
                list($menu,$pid) = $this->getMenu($menu_config);
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
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
        $this->success(lang('Uninstall successful'));
    }

    /**
     * @NodeAnnotation (title="禁用启用")
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
     */
    public function modify()
    {
        $name = input("name");
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
                list($menu,$pid) = $this->getMenu($menu_config);
                if( $addoninfo['status']){
                    $this->addonService->addAddonMenu($menu,$pid,$name);
                }else{
                    $this->addonService->delAddonMenu($menu,$name);
                }
            }
            refreshaddons();
            $info->save();
            $addoninfo['status']==1 ?$class->enabled():$class->disabled();
        }catch (Exception $e){
            $this->error(lang($e->getMessage()));
        }
        $this->success(lang('operation success'));
    }

    /**
     * @NodeAnnotation (title="插件配置")
     * @return \think\response\View
     * @throws Exception
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
     */
    public function config()
    {
        $name = $this->request->get("name");
        $id = $this->request->get("id");
        $one =  $this->modelClass->find($id);
        $config = get_addons_config($name);
        if ($this->request->isAjax()) {
            $params = input('params/a',[],'trim');
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
                            if($v['type']=='radio'){
                                if($value=='on'){
                                    $value = 1;
                                }
                                if($value=='off'){
                                    $value = 0;
                                }
                            }
                        }
                        $v['value'] = $value;
                    }
                }
                unset($v);
                $config_data = json_encode($config,JSON_UNESCAPED_UNICODE);
                if($one->save(['config'=>$config_data])){
                    $class = get_addons_instance($name);
                    if(method_exists($class,'config')){
                        $class->config();
                    }
                    set_addons_config($name,$config);
                    if(!empty($config['app_rewrite']['content'])) {
                        set_app_route($name, $config['app_rewrite']['content']);
                    }
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
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
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
     * 安装插件
     * @param string $name
     * @param int $plugins_id
     * @param int $version_id
     * @param string $type
     * @return true
     * @throws Exception
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
     */
    protected function doInstall(string $name,int $plugins_id=0,int $version_id=0,string $type=''){
        //检查插件是否安装
        $list = $this->isInstall($name);
        if ($list && $list->status==1) {
            $this->error(lang('addons %s is already installed', [$name]));
        }
        list($addons,$localNameArr) = $this->getLocalAddons();
        //本地存在空和更新则请求后端
        if(empty($type) || $type=='upgrade'){
            //不存在或者
            $postData = $this->getCloudData($name,$plugins_id,$version_id);
            if(!$localNameArr || !in_array($name,$localNameArr) || !isset($addons[$name])
            ){
                try {
                    $this->getCloudAddons($postData);
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
            }
            if($type =='upgrade'){
                try {
                    $this->getCloudAddons($postData);
                } catch (Exception $e) {
                    $this->error($e->getMessage());
                }
            }
        }
        $class = get_addons_instance($name);
        if (empty($class)) {
            $this->error(lang('addons %s is not ready', [$name]));
        }
        $addon_info = get_addons_info($name);
        if(!empty($addon_info['depend'])){
            $depend = explode(',',$addon_info['depend']);
            foreach ($depend as $v) {
                $dependAddon  = get_addons_info($v);
                if(empty($dependAddon)){
                    $this->error('Please install the dependent plugin first: '.$addon_info['depend']);
                }
            }
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
            list($menu,$pid) = $this->getMenu($menu_config);
            $this->addonService->addAddonMenu($menu,$pid,$name);
        }

        //安装插件
        $class->install();
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
        try {
            Service::updateAddonsInfo($name);
            //刷新addon文件
            refreshaddons();
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
        Cache::clear();
        return true;
    }
    /**
     * 更新 先卸载插件
     * @return bool
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
     */
    protected function doUpgrade(string $name='')
    {
        set_time_limit(0);
        $name = $name?:input("name");
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
                list($menu,$pid) = $this->getMenu($menu_config);
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
        $sql = root_path().'addons/'.$name.'/'.'update.sql';
        if(file_exists($sql)){
            importSqlData($sql);
        }
        Service::updateAddonsInfo($name,1,0);
        try {
            //刷新addon文件和配置
            refreshaddons();
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
        return true;
    }

    /**
     * @param array $params
     * @return void
     * @throws Exception
     */
    protected function getCloudAddons(array $params=[]): void
    {
        $res = $this->authCloudService
            ->setApiUrl('/api/v2.plugins/down')
            ->setParams($params)
            ->setHeader()
            ->run();
        if(empty($res)){
            $this->error(lang('Api request error'));
        }
        if(isset($res['code']) && $res['code'] == 401){
            $this->authCloudService->setToken()->setMember();
            $this->error(lang('please login again'));
        }
        if(isset($res['code']) && $res['code']!=200){
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
        $stream_opts = [
            "ssl" => [
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ]
        ];
        $content = file_get_contents($res['data']['file_url'],false,stream_context_create($stream_opts));
        $fileName = $fileDir . $params['name'] . '.zip';
        @touch($fileName);
        file_put_contents($fileName, $content);
        ZipHelper::unzip($fileName, $file =  '../addons');
        @unlink($fileName);

    }

    /**
     * 获取远程请求参数
     * @param $name
     * @param $plugins_id
     * @param $version_id
     * @return array
     */
    protected  function getCloudData(string $name,int $plugins_id=0,int $version_id=0): array
    {
        return  [
            'plugins_id'=>$plugins_id,
            'name'=>$name,
            'version_id'=>$version_id,
            'version'=> '',
            'app_version'=>$this->app_version,
            "ip" => request()->ip(),
            "domain" => request()->domain(),
            "access_token" => $this->authCloudService->getToken(),
        ];
    }
    /**
     * 退出云平台
     * @return void
     */
    public function logout(){
        $this->authCloudService->setToken()->setMember();
        $this->success(lang('logout success'));

    }

    /**
     * 获取菜单
     * @param mixed $config
     * @return array<array|int|mixed>
     */
    protected function getMenu($config = [])
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
                    $pid = $this->addonService->addAddonManager()->id;
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
                $pid = $this->addonService->addAddonManager()->id;
            }else{
                $menu[] = $menuArr;
                $pid = 0;
            }
        }
        return [$menu,$pid];
    }

}
