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
use app\backend\service\AddonConfigService;
use app\backend\service\PluginPackageService;
use app\common\controller\Backend;
use app\common\service\AuthCloudService;
use GuzzleHttp\Client;
use think\App;
use think\facade\Cache;
use think\facade\Console;
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

    protected array $noNeedLogin = [];
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
    public function install(string $name = '', string $type = '')
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        set_time_limit(0);
        $name = (string) (input('name') ?? $name);
        $type = (string) (input('type') ?? $type);
        $migrate = $this->booleanInput('migrate', true);
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            $this->error(lang('addon name is not right'));
        }

        try {
            $this->doInstall(
                $name,
                (int) input('plugins_id', 0),
                (int) input('version_id', 0),
                $type,
                $migrate
            );
        } catch (\Throwable $exception) {
            $this->addonService->recordFailure($name, $exception);
            $this->error($exception->getMessage());
        }
        $this->success($type === 'upgrade' ? 'upgrade success' : 'install success');
    }

    /**
     * @NodeAnnotation(title="离线安装")
     * @throws Exception
     */
    public function localinstall()
    {
        if (!$this->request->isPost() || !$this->request->isAjax()) {
            $this->error(lang('Invalid data'));
        }
        set_time_limit(0);
        $url = (string) input('url', '');
        $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
        $archive = realpath(public_path() . ltrim($path, '/'));
        $publicRoot = realpath(public_path());
        if (!$archive || !$publicRoot || !str_starts_with($archive, $publicRoot . DIRECTORY_SEPARATOR)) {
            $this->error('插件安装包路径无效');
        }

        $packageService = PluginPackageService::instance();
        $staged = [];
        $backup = null;
        $deployed = false;
        try {
            $staged = $packageService->stage($archive);
            $name = (string) $staged['name'];
            if ($this->addonService->isInstall($name)) {
                throw new Exception(lang('addons %s is already installed', [$name]));
            }
            $backup = $packageService->deploy($staged, $name);
            $deployed = true;
            $this->addonService->installAddon($name, 'local');
            $packageService->finish($staged, $backup);
        } catch (\Throwable $exception) {
            if ($deployed && $this->addonService->canRollbackDeployment()) {
                $packageService->rollback((string) $staged['name'], $backup);
            } else {
                $packageService->discard($staged);
            }
            $this->addonService->recordFailure((string) ($staged['name'] ?? ''), $exception);
            $this->error($exception->getMessage());
        }
        $this->success('install success');
    }

    /**
     * @NodeAnnotation(title="卸载")
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
     */
    public function uninstall()
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        set_time_limit(0);
        $name = input("name");
        try {
            $this->addonService->uninstallAddon((string) $name, $this->booleanInput('purge_data', false));
        }catch (Exception $e){
            $this->error($e->getMessage());
        }
        $this->success(lang('Uninstall successful'));
    }

    /**
     * @NodeAnnotation(title="更新插件数据库")
     */
    public function migrate()
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $name = (string) input('name', '');
        try {
            $result = $this->addonService->migrateAddon($name);
        } catch (\Throwable $exception) {
            $this->addonService->recordFailure($name, $exception);
            $this->error($exception->getMessage());
        }
        $this->success('database migration success', '', $result);
    }

    /**
     * @NodeAnnotation (title="禁用启用")
     * @throws \think\dbException\DataNotFoundException
     * @throws \think\dbException\DbException
     * @throws \think\dbException\ModelNotFoundException
     */
    public function modify()
    {
        if (!$this->request->isPost()) {
            $this->error(lang('Invalid data'));
        }
        $name = input("name");
        if (!preg_match("/^[a-zA-Z0-9]+$/", $name)) {
            $this->error(lang('addon name is not right'));
        }
        try {
            $this->addonService->modifyAddon($name);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
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
        $name = (string) $this->request->get('name', '');
        if (!preg_match('/^[a-zA-Z0-9]+$/', $name)) {
            $this->error(lang('addon name is not right'));
        }
        $record = $this->modelClass->where('name', $name)->find();
        if (!$record) {
            $this->error(lang('addon config is not found'));
        }

        $configService = AddonConfigService::instance();
        if ($this->request->isAjax()) {
            try {
                $params = input('params/a', []);
                if (!$params) {
                    throw new Exception(lang('addon can not be empty'));
                }
                $configService->save($name, $params);
            } catch (\Throwable $exception) {
                $this->error($exception->getMessage());
            }
            $this->success(lang('operation success'));
        }

        $config = $configService->get($name);
        $view = ['formData' => $config, 'title' => $record['name']];
        $configFile = root_path() . PLUGIN_DIR . DS . $name . DS . 'config.html';
        app()->view->engine()->layout($this->layout);
        return view(is_file($configFile) ? $configFile : '', $view);
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
    protected function doInstall(
        string $name,
        int $pluginsId = 0,
        int $versionId = 0,
        string $type = '',
        bool $migrate = true
    ): bool {
        $installed = $this->addonService->isInstall($name);
        if ($type !== 'upgrade' && $installed && (int) $installed->delete_time === 0) {
            throw new Exception(lang('addons %s is already installed', [$name]));
        }
        if ($type === 'upgrade' && (!$installed || (int) $installed->delete_time > 0)) {
            throw new Exception('插件尚未安装');
        }
        if ($type === 'upgrade' && (int) $installed->status === 1) {
            throw new Exception(lang('Please disable addons %s first', [$name]));
        }

        $packageService = PluginPackageService::instance();
        $staged = [];
        $backup = null;
        $archive = null;
        $deployed = false;
        try {
            $pluginDirectory = root_path() . PLUGIN_DIR . DS . $name;
            if ($type === 'upgrade' || !is_dir($pluginDirectory)) {
                $archive = $this->downloadCloudArchive($this->getCloudData($name, $pluginsId, $versionId));
                $staged = $packageService->stage($archive, $name);
                $backup = $packageService->deploy($staged, $name);
                $deployed = true;
            }

            if ($type === 'upgrade') {
                $this->addonService->updateAddon($name, $migrate);
            } else {
                $this->addonService->installAddon($name, $type);
            }
            $packageService->finish($staged, $backup);
        } catch (\Throwable $exception) {
            if ($deployed && $this->addonService->canRollbackDeployment()) {
                $packageService->rollback($name, $backup);
            } else {
                $packageService->discard($staged);
            }
            throw $exception;
        } finally {
            if ($archive && is_file($archive)) {
                @unlink($archive);
            }
        }
        Cache::clear();
        return true;
    }

    /**
     * @param array $params
     * @return void
     * @throws Exception
     */
    protected function downloadCloudArchive(array $params): string
    {
        $res = $this->authCloudService
            ->setApiUrl('/api/v2.plugins/down')
            ->setParams($params)
            ->setHeader()
            ->run();
        if (empty($res)) {
            throw new Exception(lang('Api request error'));
        }
        if (($res['code'] ?? 0) === 401) {
            $this->authCloudService->setToken()->setMember();
            throw new Exception(lang('please login again'));
        }
        if (($res['code'] ?? 0) !== 200) {
            throw new Exception((string) ($res['msg'] ?? lang('Api request error')));
        }

        $url = (string) ($res['data']['file_url'] ?? '');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['https', 'http'], true)) {
            throw new Exception('插件下载地址无效');
        }
        $directory = runtime_path('plugins' . DS . 'download');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new Exception('无法创建插件下载目录');
        }
        $archive = $directory . DS . $params['name'] . '-' . bin2hex(random_bytes(6)) . '.zip';
        try {
            (new Client())->get($url, [
                'sink' => $archive,
                'timeout' => 120,
                'connect_timeout' => 10,
                'allow_redirects' => ['max' => 3, 'protocols' => ['https', 'http']],
                'progress' => static function ($total, $downloaded): void {
                    if ($total > 104857600 || $downloaded > 104857600) {
                        throw new Exception('插件安装包超过 100MB 限制');
                    }
                },
            ]);
        } catch (\Throwable $exception) {
            @unlink($archive);
            throw new Exception('插件安装包下载失败：' . $exception->getMessage(), 0, $exception);
        }
        if (!is_file($archive) || filesize($archive) === 0 || filesize($archive) > 104857600) {
            @unlink($archive);
            throw new Exception('插件安装包无效或超过 100MB 限制');
        }
        return $archive;
    }

    private function booleanInput(string $name, bool $default): bool
    {
        $value = input($name, $default ? '1' : '0');
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
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
}
