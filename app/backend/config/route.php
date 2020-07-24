<?php
/**
 * Created by SpeedAdmin.
 * Copyright SpeedAdmin.
 * Author: Yuege
 * Date: 2020/3/9
 * Time: 14:39
 */


return [
    'middleware' => [
        //节点
        app\backend\middleware\ViewNode::class,
        //角色权限
        app\backend\middleware\CheckRole::class,
        //日志
        app\backend\middleware\SystemLog::class,
    ],
];