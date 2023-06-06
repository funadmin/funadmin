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
 * Date: 2021/5/19
 * Time: 17:28
 */

namespace app\backend\controller\sys;

use app\common\controller\Backend;
use app\common\model\Languages as LanguagesModel;
use app\common\service\AuthCloudService;
use fun\helper\FileHelper;
use fun\helper\HttpHelper;
use fun\helper\ZipHelper;
use think\App;
use think\Exception;
use think\facade\Db;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="系统更新")
 * Class Upgrade
 * @package app\backend\controller\sys
 */
class Upgrade extends Backend
{

    protected $backup_dir;
    protected $authCloudService;
    protected $lockFile;
    protected $now_version;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->backup_dir = '../../backup/';
        $this->now_version = config('funadmin.version');
        $this->lockFile = '../../backup/'.$this->now_version.'.lock';
        $this->authCloudService = AuthCloudService::instance();
    }
    /**
     * @NodeAnnotation('List')
     * @return \think\response\Json|\think\response\View
     */
    public function index()
    {
        if ($this->request->isPost()) {
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
        }
        $data['now_version'] = $this->now_version;
        $version = Db::query('SELECT VERSION() AS ver');
        $view = [
            'url' => $_SERVER['HTTP_HOST'],
            'document_root' => $_SERVER['DOCUMENT_ROOT'],
            'document_protocol' => $_SERVER['SERVER_PROTOCOL'],
            'server_os' => PHP_OS,
            'server_port' => $_SERVER['SERVER_PORT'],
            'server_ip' => $_SERVER['REMOTE_ADDR'],
            'server_soft' => $_SERVER['SERVER_SOFTWARE'],
            'server_file' => $_SERVER['SCRIPT_FILENAME'],
            'php_version' => PHP_VERSION,
            'mysql_version' => $version[0]['ver'],
            'auth' => $this->authCloudService->getAuth() ? 1 : 0,
        ];
        $view = array_merge($data, $view);
        return view('', $view);
    }

    /**
     * @NodeAnnotation('check')
     * 检测版本信息
     * @return mixed
     */
    public function check()
    {
        if (!$this->authCloudService->getAuth()) {
            $this->error(lang('请先登录FunAdmin系统'));
        }
        $params = [
            "ip" => request()->ip(),
            "domain" => request()->domain(),
            "version" => $this->now_version,
        ];
        $result = $this->authCloudService->setApiUrl('api/v1.version/getVersion')
            ->setParams($params)->setHeader()->run();
        if ($result['code'] == 200) {
            session('upgradeInfo', $result['data']);
            $result['data']['content'] =  $result['data']['content']? explode("\n", $result['data']['content']):'';
            $this->success('发现新的更新包', '', $result);
        } else {
            $this->error('您现在是最新的版本');
        }
    }

    /**
     * @NodeAnnotation('backup');
     */
    public function backup()
    {
        if ($this->request->isPost()) {
            if (!$this->authCloudService->getAuth()) {
                $this->error(lang('请先登录FunAdmin系统'));
            }
            if (!is_dir($this->backup_dir)) {
                FileHelper::mkdirs($this->backup_dir);
            }
            $zipFile = '../../backup/' . date('YmdHis') . '_v' . $this->now_version . '.zip';
            if (!is_file($zipFile)) {
                FileHelper::createFile($zipFile, '');
            }
            FileHelper::createFile('../../backup/'.$this->now_version.'.lock',time());
            ZipHelper::zip($zipFile, '../');
            $this->success(lang('backup success'));
        }
    }

    /**
     * @NodeAnnotation('install')
     * @throws \Exception
     */
    public function install()
    {
        if ($this->request->isPost()) {
            if (!$this->authCloudService->getAuth()) {
                $this->error(lang('请先登录FunAdmin系统'));
            }
            if(!file_exists($this->lockFile)){
                $this->error('请先备份');
            }
            $updateInfo = session('upgradeInfo');
            $content = file_get_contents($updateInfo['file_url']);
            $filename = $updateInfo['version'];
            $fileDir = '../runtime/upgrade/';
            if (!is_dir($fileDir)) {
                FileHelper::mkdirs($fileDir);
            }
            $fileName = $fileDir . $filename . '.zip';
            @touch($fileName);
            file_put_contents($fileName, $content);
            ZipHelper::unzip($fileName, $file = $fileDir . $filename . '/');
            $dir = scandir($fileDir . $filename . '/');
            try {
                foreach ($dir as $k => $v) {
                    if ($v == '.' || $v == '..') continue;
                    $file = $fileDir . $filename . '/' . $v;
                    if ($v == 'upgrade.sql') {
                        importSqlData($file);
                    } else if (is_file($file)){
                        @copy($file,'../'.$v);
                    }else{
                        FileHelper::copyDir($file, '../' . $v);
                    }
                }
            }catch (\Exception $e){
                $this->error($e->getMessage());
            }
            @unlink($fileName);
            FileHelper::delDir($fileDir . $filename);
            @unlink($this->lockFile);
            $version = $updateInfo['version'];
            session('upgradeInfo','');
            setConfig('../config/funadmin.php','version',$version);
            $this->success('更新成功');
        }
    }

}
