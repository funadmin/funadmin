<?php

namespace app\backend\middleware;

use app\backend\service\AuthService;
use think\App;
use think\facade\Config;
use think\facade\Lang;
use think\facade\Request;
use think\facade\View;
use think\helper\Str;
class ViewNode
{
    public function handle($request, \Closure $next)
    {
        [$appname, $controllername, $actionname] = [app('http')->getName(), $request->controller(), Request::action()];
        $controllers = explode('.', $controllername);
        $jsname = '';
        foreach ($controllers as $vo) {
            empty($jsname) ? $jsname = strtolower(Str::camel(parse_name($vo))) : $jsname .= '/' . strtolower(Str::camel(parse_name($vo)));
        }
        $controllername = strtolower(Str::camel(parse_name($controllername)));
        $actionname = strtolower(Str::camel(parse_name($actionname)));
        $requesturl = "{$appname}/{$controllername}/{$actionname}";
        $autojs = file_exists(app()->getRootPath()."public".DS."static".DS."{$appname}".DS."js".DS."{$jsname}.js") ? true : false;
        $jspath ="{$appname}/js/{$jsname}.js";
        $config = [
            'appname'    => $appname,
            'moduleurl'    => rtrim(__u("/{$appname}", [], false), '/'),
            'module'    => '/backend/',
            'controllername'       =>$controllername,
            'actionname'           => $actionname,
            'requesturl'          => $requesturl,
            'jspath' => "{$jspath}",
            'autojs'           => $autojs,
            'superAdmin'           => session('admin.id')==1 || session('admin.group') && in_array(1,session('admin.group')?explode(',',session('admin.group')):'')?true:false,
            'lang'           =>  strip_tags(Lang::getLangset()),
            'site'           =>   syscfg('site'),
            'upload'           =>  syscfg('upload'),
            'publicAjaxUrl'         =>config('funadmin.publicAjaxUrl'),
        ];
        View::assign('config',$config);
        $request->appname =$appname;
        return $next($request);
    }

    //中间件支持定义请求结束前的回调机制，你只需要在中间件类中添加end方法。
    public function end(\think\Response $response)
    {
        // 回调行为
    }
}
