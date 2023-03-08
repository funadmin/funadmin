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
    protected  $title = '';
    //自定义日志内容
    protected  $post_data = '';
    protected  $get_data = '';
    protected  $header_data = '';
    protected  $method = '';
    protected  $ip = '';
    protected  $agent = '';
    protected  $app = '';
    protected  $controller = '';
    protected  $action = '';
    protected  $admin_id = '';
    protected  $username = '';
    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     * 保存记录
     */

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->post_data = json_encode(Request::post(),JSON_UNESCAPED_UNICODE);
        $this->get_data = json_encode(Request::get(),JSON_UNESCAPED_UNICODE);
        $this->header_data = json_encode( Request::header(),JSON_UNESCAPED_UNICODE);
        $this->method = Request::method();
        $this->ip = Request::ip();
        $this->agent =Request::server('HTTP_USER_AGENT');
        $this->app =  app('http')->getName();
        $this->admin_id   = Session::get('admin.id',0);
        $this->username   = Session::get('admin.username','Unknown');
    }
    public function save()
    {
        $url        = (Request::pathinfo());
        $content    = Request::param();
        $this->controller = Request::controller();
        $this->action = Request::action();
        if (strpos($url, 'enlang') !== false && Request::isAjax()) {
            $this->title = '[切换语言]';
        }elseif (strpos($url, 'ajax/clearData') !== false && Request::isAjax()) {
            $this->title = '[清楚缓存]';
        }elseif (strpos($url, 'login/index') !== false && Request::isAjax()) {
            $this->title = '[登录成功]';
            $this->username = json_decode($this->post_data,true)['username'];
        }else{
            //权限
            $auth = AuthRule::column('href','id');
            $url = str_replace('.'.config('view.view_suffix'),'',$url);
            $this->title =  AuthRule::where('href',$url)->where('module',$this->app)->value('title');
        }
        if(isset($this->post_data['password'])) unset($this->post_data['password']);
        //插入数据
        if (!empty($this->title) && !empty($content)) {
            AdminLog::create([
                'title'       => $this->title ?: '',
                'admin_id'    => $this->admin_id,
                'username'    => $this->username,
                'url'         => $url,
                'addons'      => 'app',
                'module'      => $this->app,
                'controller'      => $this->controller,
                'action'      => $this->action,
                'get_data'     => $this->get_data,
                'post_data'     => $this->post_data,
                'header_data'     => $this->header_data,
                'agent'       => $this->agent,
                'ip'          => $this->ip,
                'method'      => $this->method,
            ]);
        }
    }

}