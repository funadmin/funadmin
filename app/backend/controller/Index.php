<?php
/**
 * SpeedAdmin
 * ============================================================================
 * 版权所有 2018-2027 SpeedAdmin，并保留所有权利。
 * 网站地址: https://www.SpeedAdmin.cn
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/8/2
 */
namespace app\backend\controller;
use app\backend\service\AuthService;
use app\common\controller\Backend;
use think\facade\Db;
use think\facade\View;
use think\facade\Cache;
class Index extends Backend{

    protected $layout='';
    /**
     * @return string
     * @throws \Exception
     * 首页
     */
    public function index(){
        // 所有显示的菜单；
        return view();
    }
    /**
     * @return string
     * @throws \think\db\exception\BindParamException
     * @throws \think\db\exception\PDOException
     * 主页面
     */
    public function console(){
        $version = Db::query('SELECT VERSION() AS ver');
        $config = Cache::get('main_config');
        if(!$config){
            $config  = [
                'url'             => $_SERVER['HTTP_HOST'],
                'document_root'   => $_SERVER['DOCUMENT_ROOT'],
                'document_protocol'   => $_SERVER['SERVER_PROTOCOL'],
                'server_os'       => PHP_OS,
                'server_port'     => $_SERVER['SERVER_PORT'],
                'server_ip'       => $_SERVER['REMOTE_ADDR'],
                'server_soft'     => $_SERVER['SERVER_SOFTWARE'],
                'server_file'     => $_SERVER['SCRIPT_FILENAME'],
                'php_version'     => PHP_VERSION,
                'mysql_version'   => $version[0]['ver'],
                'max_upload_size' => ini_get('upload_max_filesize'),
            ];
            Cache::set('main_config',$config,3600);
        }
        View::assign('config', $config);
        return view();
    }

    /**
     * 退出登录
     */
    public function logout()
    {

        (new AuthService())->logout();
        $this->success(lang('Logout success'), url('login/index'));
    }


}