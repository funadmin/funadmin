<?php

return [
    //不需要验证权限的控制器
    'noRightController'=>[ 'ajax', 'login', 'index'],
    //不需要登录控制器
    'noLoginController'=>['login'],
    // 不需要鉴权
    'noRightNode'    =>['login/index', 'login/logout','ajax/lang','ajax/verify','login/verify','ajax/clearcache','ajax/setConfig'],
    // 不需要登陆
    'noLoginNode' => ['login/index', 'login/logout', 'ajax/lang', 'ajax/clearData','ajax/verify'],
    //超级管理员id
    'superAdminId'=>1,
    //是否演示站点
    'isDemo'=>0,
    'backendEntrance' => '/xxx.php/',

];


?>
