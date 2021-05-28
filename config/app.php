<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

use think\facade\Env;

return [
    // 应用地址
    'app_host'         => Env::get('app.host', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 是否启用事件
    'with_event'       => true,
    // 应用映射（自动多应用模式有效）
    'app_map'          => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [

    ],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => ['common','backend'],
    // 开启应用快速访问 如果你完全不需要单应用模式，也可以设置使用严格的多应用模式
    'app_express'    =>    true,
    // 默认应用
    'default_app'      => 'frontend',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 空控制器名
    'empty_controller'      => 'Error',
    // 默认异常页面的模板文件
//    'exception_tmpl'   => \think\facade\App::getAppPath(). '/common/view/tpl/think_exception.tpl',
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl' => \think\facade\App::getAppPath(). '/common/view/tpl/dispatch_jump.tpl',
    'dispatch_error_tmpl'   => \think\facade\App::getAppPath(). '/common/view/tpl/dispatch_jump.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '您访问的内容飞走了',
    // 显示错误信息
    'show_error_msg'   => true,
    //版本
    'version' => '1.1.2'
];
