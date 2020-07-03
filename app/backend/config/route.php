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
        app\backend\middleware\ViewNode::class,
        app\backend\middleware\CheckRole::class,
        //日志
        app\backend\middleware\AdminLog::class,
    ],
];