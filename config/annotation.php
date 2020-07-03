<?php

return [
    'inject' => [
        'enable'     => true,
        'namespaces' => [],
    ],
    'route'  => [
        'enable'      => true,
        'controllers' => [],
    ],
    'ignore' => [],
    'custom' => [
        # 格式：注解类 => 注解操作类
        \app\annotation\Param::class => \app\annotation\handler\Param::class,
    ]
];
