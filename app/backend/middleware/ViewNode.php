<?php

namespace app\backend\middleware;

use app\backend\lib\Auth;
use think\App;
use think\Console;
use think\facade\Cache;
use think\facade\Env;
use think\facade\Lang;
use think\facade\Request;
use think\facade\View;

class ViewNode
{
    public function handle($request, \Closure $next)
    {
        [$modulename, $controllername, $actionname] = [app('http')->getName(), $request->controller(), Request::action()];
        $controllers = explode('.', $controllername);
        $jsname = '';
        foreach ($controllers as $vo) {
            empty($jsname) ? $jsname = parse_name($vo) : $jsname .= '/' . parse_name($vo);
        }
        $autojs = file_exists(app()->getRootPath()."public".DIRECTORY_SEPARATOR."static".DIRECTORY_SEPARATOR."{$modulename}".DIRECTORY_SEPARATOR."js".DIRECTORY_SEPARATOR."{$jsname}.js") ? true : false;
        $jspath ="{$modulename}/js/{$jsname}.js";
        $auth = new Auth();
        $authNode = $auth->nodeList();
        $data = [
            'modulename'    => $modulename,
            'moduleurl'    => rtrim(url("/{$modulename}", [], false), '/'),
            'controllername'       => parse_name($controllername),
            'actionname'           => parse_name($actionname),
            'requesturl'          => parse_name("{$modulename}/{$controllername}/{$actionname}"),
            'jspath' => "{$jspath}",
            'autojs'           => $autojs,
            'authNode'           => $authNode,
            'superAdmin'           => session('admin.id')==1?1:0,
            'lang'           =>  strip_tags( Lang::getLangset()),
        ];

        View::assign($data);
        $request->modulename =$modulename;
        return $next($request);
    }

    //中间件支持定义请求结束前的回调机制，你只需要在中间件类中添加end方法。
    public function end(\think\Response $response)
    {
        // 回调行为
    }
}