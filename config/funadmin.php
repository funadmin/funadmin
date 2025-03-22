<?php

return [
    'mysqlPrefix'=>['__PREFIX__','__prefix__','{PREFIX}','{prefix}','fun_', 'Fun_', 'THINK_','think_'],
    //权限开关
    "auth_on" =>true,
    //超级管理员id

    'superAdminId'=>1,
    //是否演示站点
    'isDemo'=>0,
    //版本
    'version' => '7.0.0',

    'version_data' => date('Y-m-d'),

    'layui_version' => '2.10.1',

    'ip_check'=>false,

    'public_ajax_url'=>['ajax/uploads', 'ajax/getAttach', 'sys.attach/selectfiles','ajax/export','ajax/import'],
    //是否独立后台
    'standalone'=>1,
    //接口域名
    'api_domain'=>'https://www.funadmin.com',
    //接口地址
    'api_login_url'=>'/api/v2/token/build',
];


