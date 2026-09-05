<?php

return [
    'mysqlPrefix'=>['__PREFIX__','__prefix__','{PREFIX}','{prefix}','fun_', 'Fun_', 'THINK_','think_'],
    //权限开关
    "auth_on" =>true,
    //超级管理员id

    'superAdminId'=>1,
    // 超级角色 ID，与超级管理员账号 ID 分离。
    'superRoleId'=>1,
    // Casbin RBAC domain；当前单租户统一使用 default。
    'auth_domain'=>'default',
    //是否演示站点
    'isDemo'=>0,
    //版本
    'version' => '8.0',

    'version_data' => date('Y-m-d'),

    'layui_version' => '2.11.4',

    'ip_check'=>false,

    'public_ajax_url'=>['ajax/uploads', 'ajax/getAttach', 'ajax/export','ajax/import'],

    // 所有后台请求默认按权限节点校验；以下仅为登录后所有管理员共享的基础入口。
    'auth_login_only_routes'=>[
        'index/index', 'index/console', 'index/logout', 'index/enlang',
        'ajax/refreshmenu', 'ajax/lang', 'ajax/getattach',
        'plugin/logout',
        'auth/me', 'auth/menus', 'auth/logout',
    ],
    // 全局配置和缓存只允许超级管理员操作。
    'auth_super_only_routes'=>['ajax/clearcache', 'ajax/setconfig', 'ajax/getlist'],
    // 复用已有权限节点，避免同一能力出现多个授权口径。
    'auth_route_aliases'=>[
        'plugin/localinstall' => 'plugin/install',
        'plugin/uninstall' => 'plugin/install',
        'system/role/all' => 'systemrole/index',
        'system/role/parent-options' => 'systemrole/index',
        'system/role/permission-tree' => 'systemrole/permissions',
        'backend/systemoperationlog:detail' => 'systemoperationlog/index',
        'backend/systempermission:detail' => 'systempermission/tree',
    ],

    'sys_app'=>['backend','api','index','common','install'],

    'curd_deny_app'=>['common','install'],
    //接口域名
    'api_domain'=>'https://www.funadmin.com',
];


