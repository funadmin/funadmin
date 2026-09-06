<?php

declare(strict_types=1);

// route:list 运行在根应用，显式纳入后台控制器并保留多应用 URL 前缀。
$annotationRoute = config('annotation.route', []);
$annotationRoute['controllers'] = [
    root_path('app/backend/controller') => [
        'namespace' => 'app\\backend\\controller',
        'name' => 'backend',
    ],
];
config(['route' => $annotationRoute], 'annotation');
