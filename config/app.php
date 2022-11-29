<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------


return [
    // 应用地址
    'app_host'         => env('app.host', ''),
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 是否启用事件
    'with_event'       => true,
    // 默认应用
    'default_app'      => 'frontend',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',
    // 应用映射（自动多应用模式有效）
    'app_map'          => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => ['common'],
    // 开启应用快速访问 如果你完全不需要单应用模式，也可以设置使用严格的多应用模式
    'app_express'    =>    true,


    // 空控制器名
    'empty_controller'      => 'Error',
    // 默认异常页面的模板文件
//        'exception_tmpl'   => \think\facade\App::getAppPath(). '/common/view/tpl/think_exception.tpl',
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl' => \think\facade\App::getAppPath(). '/common/view/tpl/dispatch_jump.tpl',
    'dispatch_error_tmpl'   => \think\facade\App::getAppPath(). '/common/view/tpl/dispatch_jump.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '您访问的内容飞走了',
    // 显示错误信息
    'show_error_msg'   => true,

];
