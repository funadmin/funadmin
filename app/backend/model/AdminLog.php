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
namespace app\backend\model;

use speed\helper\DataHelper;
use think\facade\Request;
use think\facade\Session;
use think\facade\Db;
use think\facade\Route;
use app\backend\model\AuthRule;

class AdminLog extends BackendModel
{

    protected static $title = '';
    //自定义日志内容
    protected static $content = '';
    protected static $url = '';
    public $request=null;
    public function __construct(array $data = [])
    {
        parent::__construct($data);
    }


    /*
     * 管理员日志记录
     */
    public static function record()
    {
        //入库信息
        $admin_id   = Session::get('admin.id',0);
        $username   = Session::get('admin.username','Unknown');
        $url        = Request::baseUrl();
        $method     = strtoupper(Request::method());
        $title      = self::$title;
        $ip         = Request::ip();
        $agent      = Request::server('HTTP_USER_AGENT');
        $content    = Request::param();

        if ($content) {
            //去除登录密码
            foreach ($content as $k => $v) {
                if (stripos($k, 'password') !== false) {
                    unset($content[$k]);
                }
            }
            $content = json_encode($content);
        }elseif (!$content && Request::isGet()){
            $content = '点击菜单';
        }elseif (!$content && Request::isPost()){
            $content = '清除缓存|切换语言';
        }
        //登录处理
        if (strpos($url, 'login/index') !== false && Request::isAjax()) {
            $title = '[登录成功]';
        }else{
            //权限
            $auth = AuthRule::column('href','href');
            foreach ($auth as $k=>$v){
                $auth[$k] = url($v);
            }
            $key = array_search($url,$auth);
            if($key){
                $auth = AuthRule::where('href',$key)->find();
                if($auth) $title=$auth->title;
            }
        }
        //插入数据
        if (!empty($title)) {
            self::create([
                'title'       => $title ? $title : '',
                'content'     => $content,
                'url'         => $url,
                'admin_id'    => $admin_id,
                'username'    => $username,
                'agent'       => $agent,
                'ip'          => $ip,
                'method'      => $method,
             ]);
        }


    }

}