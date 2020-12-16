<?php
/**
 * +
 * | 后台中间件验证权限
 */
namespace app\backend\middleware;


use app\common\service\AdminLogService;

class SystemLog
{
    public function handle($request, \Closure $next)
    {

        //进行操作日志的记录
        AdminLogService::instance()->save();
        //中间件handle方法的返回值必须是一个Response对象。
        return $next($request);
    }
    //中间件支持定义请求结束前的回调机制，你只需要在中间件类中添加end方法。
    public function end(\think\Response $response)
    {
        // 回调行为
    }
}