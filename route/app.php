<?php

declare(strict_types=1);

// route:list 运行在根应用，显式纳入管理控制器并保留多应用 URL 前缀。
$annotation = config('annotation', []);
$annotation['route']['controllers'] = [
    root_path('app/admin/controller') => [
        'namespace' => 'app\\admin\\controller',
        'name' => 'admin',
    ],
];
config('annotation', $annotation);
