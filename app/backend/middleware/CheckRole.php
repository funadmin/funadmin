<?php
/**
 * +
 * | 后台中间件验证权限
 */

namespace app\backend\middleware;

use app\backend\service\AuthService;
use app\common\traits\Jump;
use think\facade\Request;
use think\facade\Session;

class CheckRole
{

    use Jump;


    public function handle($request, \Closure $next)
    {
        if(!Session::has('admin')) {

            $this->redirect(__u('backend/login/index'));
        }
        $auth = new AuthService();
        $auth->checkNode();
        //中间件handle方法的返回值必须是一个Response对象。
        return $next($request);
    }

    //中间件支持定义请求结束前的回调机制，你只需要在中间件类中添加end方法。
    public function end(\think\Response $response)
    {
        // 回调行为
    }
}