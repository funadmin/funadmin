<?php

namespace app\backend\middleware;

use app\backend\service\AuthService;
use think\App;
use think\facade\Lang;
use think\facade\Request;
use think\facade\View;
use think\helper\Str;
class ViewNode
{
    public function handle($request, \Closure $next)
    {

        [$modulename, $controllername, $actionname] = [app('http')->getName(), $request->controller(), Request::action()];
        $controllers = explode('.', $controllername);
        $jsname = '';
        foreach ($controllers as $vo) {
            empty($jsname) ? $jsname = strtolower(Str::camel(parse_name($vo))) : $jsname .= '/' . strtolower(Str::camel(parse_name($vo)));
        }
        $controllername = strtolower(Str::camel(parse_name($controllername)));
        $actionname = strtolower(Str::camel(parse_name($actionname)));
        $requesturl = "{$modulename}/{$controllername}/{$actionname}";
        $this->entrance = config('backend.backendEntrance');
        $autojs = file_exists(app()->getRootPath()."public".DS."static".DS."{$modulename}".DS."js".DS."{$jsname}.js") ? true : false;
        $jspath ="{$modulename}/js/{$jsname}.js";
        $authNode = (new AuthService())->nodeList();
        $config = [
            'entrance'    => $this->entrance,//入口
            'addonname'    => '',
            'modulename'    => $modulename,
            'moduleurl'    => rtrim(__u("/{$modulename}", [], false), '/'),
            'controllername'       =>$controllername,
            'actionname'           => $actionname,
            'requesturl'          => $requesturl,
            'jspath' => "{$jspath}",
            'autojs'           => $autojs,
            'authNode'           => $authNode,
            'superAdmin'           => session('admin.id')==1?true:false,
            'lang'           =>  strip_tags(Lang::getLangset()),
            'site'           =>   syscfg('site'),
            'upload'           =>  syscfg('upload'),
        ];
        View::assign('config',$config);
        $request->modulename =$modulename;
        return $next($request);
    }

    //中间件支持定义请求结束前的回调机制，你只需要在中间件类中添加end方法。
    public function end(\think\Response $response)
    {
        // 回调行为
    }
}