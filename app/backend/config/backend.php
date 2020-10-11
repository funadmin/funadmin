<?php

return [
    //    不需要验证权限的控制器
    'noRightController'=>[ 'ajax', 'login', 'index',],
    //不需要登录控制器
    'noLoginController'=>['login'],
    // 不需要鉴权
    'noRightNode'    =>['login/index', 'login/logout','ajax/lang','ajax/verfiy','login/verfiy','ajax/clearcache'],
    // 不需要登陆
    'noLoginNode' => ['login/index', 'login/logout', 'ajax/lang', 'ajax/clearData','ajax/verfiy'],
    //
    'superAdminId'=>'1',

    'isDemo'=>false,


];


?>