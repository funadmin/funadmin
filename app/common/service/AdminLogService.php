<?php
/**
 * FunAadmin
 * ============================================================================
 * 版权所有 2017-2028 FunAadmin，并保留所有权利。
 * 网站地址: https://www.FunAadmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp6实现
 * ============================================================================
 * Author: yuege
 * Date: 2017/8/2
 */
namespace app\common\service;

use app\backend\controller\auth\Admin;
use app\backend\model\AdminLog;
use app\backend\model\AuthRule;
use think\App;
use think\facade\Request;
use think\facade\Session;

class AdminLogService extends AbstractService
{

    /**
     * @var string
     * title
     */
    protected static $title = '';
    //自定义日志内容
    protected static $post_data = '';
    protected static $get_data = '';
    protected static $controller = '';
    protected static $action = '';
    /**
     * @var string
     * url
     */
    protected static $url = '';

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 保存记录
     */
    public function save()
    {
        $admin_id   = Session::get('admin.id',0);
        $username   = Session::get('admin.username','Unknown');
        $url        = (Request::baseUrl());
        $method     = strtoupper(Request::method());
        $ip         = Request::ip();
        $agent      = Request::server('HTTP_USER_AGENT');
        $content    = Request::param();
        self::$controller = Request::controller();
        self::$action = Request::action();
        $module     = app('http')->getName();
        if ($content && Request::isGet()) {
            $contents = json_encode($content);
            if(strpos($contents,'limit') !==false && strpos($contents,'page')!==false ){
              $content = '';
            }else{
                //去除登录密码
                foreach ($content as $k => $v) {
                    if (stripos($k, 'password') !== false)  unset($content[$k]);
                }
                self::$get_data = json_encode($content);
            }
        }elseif ($content && Request::isPost()){
            self::$post_data  = json_encode($content);
        }elseif (!$content && Request::isGet()){
            self::$get_data = '菜单点击|刷新';
        }elseif (!$content && Request::isPost()){
            self::$post_data = '清除缓存|切换语言';
        }
        if (strpos($url, 'enlang') !== false && Request::isAjax()) {
            self::$title = '[切换语言]';
        }elseif (strpos($url, 'ajax/clearData') !== false && Request::isAjax()) {
            self::$title = '[清楚缓存]';
        }elseif (strpos($url, 'login/index') !== false && Request::isAjax()) {
            self::$title = '[登录成功]';
            $username = json_decode(self::$post_data,true)['username'];

        }else{
            //权限
            $auth = AuthRule::column('href','id');
            foreach ($auth as $k=>&$v){
               $v = __u($v);
            }
            $url = str_replace('.html','',$url).'.html';
            $key = array_search($url,$auth);
            if($key>=0){
                $auth = AuthRule::where('id',$key)->find();
                if($auth) self::$title=$auth->title;
            }
        }
        if(strpos($url,'addons/')!==false){
            $module = 'addons';
        }
        //插入数据
        if (!empty(self::$title) and $content) {
            AdminLog::save([
                'title'       => self::$title ? self::$title : '',
                'get_data'     => self::$get_data,
                'post_data'     => self::$post_data,
                'url'         => $url,
                'admin_id'    => $admin_id,
                'username'    => $username,
                'agent'       => $agent,
                'ip'          => $ip,
                'method'      => $method,
                'module'      => $module,
                'addons'      => 'app',
                'controller'      => self::$controller,
                'action'      => self::$action,
            ]);
        }
    }
    /**
     * 保存插件历史记录
     */
    public function saveaddonslog($data)
    {
        AdminLog:save($data);
    }

}