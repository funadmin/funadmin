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
    protected static $header_data = '';
    protected static $method = '';
    protected static $ip = '';
    protected static $agent = '';
    protected static $module = '';
    protected static $controller = '';
    protected static $action = '';
    protected static $admin_id = '';
    protected static $username = '';
    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 保存记录
     */

    public function __construct(App $app)
    {
        parent::__construct($app);
        self::$post_data = json_encode(Request::post(),JSON_UNESCAPED_UNICODE);
        self::$get_data = json_encode(Request::get(),JSON_UNESCAPED_UNICODE);
        self::$header_data = json_encode( Request::header(),JSON_UNESCAPED_UNICODE);
        self::$method = Request::method();
        self::$ip = Request::ip();
        self::$agent =Request::server('HTTP_USER_AGENT');
        self::$module =  app('http')->getName();
        self::$admin_id   = Session::get('admin.id',0);
        self::$username   = Session::get('admin.username','Unknown');
    }
    public function save()
    {

        $url        = (Request::baseUrl());
        $content    = Request::param();
        self::$controller = Request::controller();
        self::$action = Request::action();
        if (strpos($url, 'enlang') !== false && Request::isAjax()) {
            self::$title = '[切换语言]';
        }elseif (strpos($url, 'ajax/clearData') !== false && Request::isAjax()) {
            self::$title = '[清楚缓存]';
        }elseif (strpos($url, 'login/index') !== false && Request::isAjax()) {
            self::$title = '[登录成功]';
            self::$username = json_decode(self::$post_data,true)['username'];
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
        //插入数据
        if (!empty(self::$title) and $content) {
            AdminLog::create([
                'title'       => self::$title ? self::$title : '',
                'admin_id'    => self::$admin_id,
                'username'    => self::$username,
                'url'         => $url,
                'addons'      => 'app',
                'module'      => self::$module,
                'controller'      => self::$controller,
                'action'      => self::$action,
                'get_data'     => self::$get_data,
                'post_data'     => self::$post_data,
                'header_data'     => self::$header_data,
                'agent'       => self::$agent,
                'ip'          => self::$ip,
                'method'      => self::$method,
            ]);
        }
    }
    /**
     * 保存插件历史记录
     */
    public function saveaddonslog($data)
    {
        $datas = [
            'title' =>'',
            'admin_id'    => self::$admin_id,
            'username'    => self::$username,
            'get_data'     => self::$get_data,
            'post_data'     => self::$post_data,
            'header_data'     => self::$header_data,
            'agent'       => self::$agent,
            'ip'          => self::$ip,
            'method'      => self::$method,

        ];
        $data  = array_merge($data,$datas);
        AdminLog::create($data);
    }

}