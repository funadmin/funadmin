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

    'ip_check'=>false,

    // 所有后台请求默认按权限节点校验；以下仅为登录后所有管理员共享的基础入口。
    'auth_login_only_routes'=>[
        'plugin/logout',
        'auth/me', 'auth/menus', 'auth/logout',
    ],
    // 复用已有权限节点，避免同一能力出现多个授权口径。
    'auth_route_aliases'=>[
        'plugin/localinstall' => 'plugin/install',
        'plugin/uninstall' => 'plugin/install',
        'system/role/all' => 'systemrole/index',
        'system/role/parent-options' => 'systemrole/index',
        'system/role/permission-tree' => 'systemrole/permissions',
        'backend/systemoperationlog:detail' => 'console/systemoperationlog/index',
        'backend/systempermission:detail' => 'console/systempermission/tree',
        'development/crud/validate' => 'devcrud/validate',
        'system/role/:id' => 'systemrole/delete',
        'system/dept/:id' => 'systemdepartment/delete',
        'system/user/:id' => 'systemadmin/delete',
        'system/menu/:id' => 'systemmenu/delete',
        'system/permission/:id' => 'systempermission/delete',
        'system/language/:id' => 'systemlanguage/delete',
        'system/log/operation/:id' => 'systemoperationlog/delete',
    ],

    'sys_app'=>['console','api','index','common','install'],

    'crud_deny_app'=>['common','install'],
    //接口域名
    'api_domain'=>'https://www.funadmin.com',
    // 系统升级仅信任该 Ed25519 发布者公钥；为空时生产流程 fail closed。
    'upgrade_public_key'=>(string) env('FUNADMIN_UPGRADE_PUBLIC_KEY', ''),
    'upgrade_signature_algorithm'=>'ed25519',
    'upgrade_hosts'=>[],
    'upgrade_manifest_ttl'=>300,
    'upgrade_lease_seconds'=>1800,
];


