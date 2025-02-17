<?php
// +----------------------------------------------------------------------
// | 模板设置
// +----------------------------------------------------------------------
use think\facade\Env;
return [
    // 模板引擎类型使用Think
    'type'         => 'Think',
    // 默认模板渲染规则 1 解析为小写+下划线 2 全部转换小写 3 保持操作方法
    'auto_rule'    => 2,
    // 模板基础路径
    'view_base'    => '',
    // 模板路径
    'view_path'    => '',
    // 模板后缀
    'view_suffix'  => 'html',
    // 模板文件名分隔符
    'view_depr'    => DIRECTORY_SEPARATOR,
    // 模板引擎普通标签开始标记
    'tpl_begin'    => '{',
    // 模板引擎普通标签结束标记
    'tpl_end'      => '}',
    // 标签库标签开始标记
    'taglib_begin' => '{',
    // 标签库标签结束标记
    'taglib_end'   => '}',

    'tpl_replace_string' => [
        '__STATIC__' => Env::get('APP_STATIC', '/static'),
        '__PLUGINS__'     => Env::get('APP_PLUGINS', '/static/plugins'),
        '__CDN__'     => '',
    ]
];
