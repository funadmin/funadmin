<?php

declare(strict_types=1);

// route:list 运行在根应用，显式纳入管理控制器并保留多应用 URL 前缀。
$annotationRoute = config('annotation.route', []);
$annotationRoute['controllers'] = [
    root_path('app/admin/controller') => [
        'namespace' => 'app\\admin\\controller',
        'name' => 'admin',
    ],
];
config(['route' => $annotationRoute], 'annotation');
