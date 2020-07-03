<?php
// +----------------------------------------------------------------------
// | 路由设置
// +----------------------------------------------------------------------

return [

    // URL普通方式参数 用于自动生成
    'url_common_param'      => true,
    // 是否开启路由延迟解析
    'url_lazy_route'        => false,
    // 是否强制使用路由
    'url_route_must'        => false,
    // 合并路由规则
    'route_rule_merge'      => true,
    // 路由是否完全匹配
    'route_complete_match'  => true,
    // 是否开启路由缓存
    'route_check_cache'     => false,
    // 路由缓存连接参数
    'route_cache_option'    => [],
    // 路由缓存Key
    'route_check_cache_key' => '',
    // 默认的路由变量规则
    'default_route_pattern' => '[\w\.]+',
    // 是否开启请求缓存 true自动缓存 支持设置请求缓存规则
    'request_cache'         => false,
    // 请求缓存有效期
    'request_cache_expire'  => null,
    // 全局请求缓存排除规则
    'request_cache_except'  => [],
    //跨应用路由
    'cross_app_route'	=>	true,
];
