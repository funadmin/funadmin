<?php

declare(strict_types=1);

return [
    'currency' => [
        'title' => '默认货币',
        'type' => 'select',
        'value' => 'CNY',
        'options' => [
            'CNY' => '人民币（CNY）',
            'USD' => '美元（USD）',
            'EUR' => '欧元（EUR）',
        ],
    ],
    'product_page_size' => [
        'title' => '商品每页数量',
        'type' => 'number',
        'value' => 20,
        'tip' => '商品列表默认分页数量。',
    ],
    'show_out_of_stock' => [
        'title' => '显示缺货商品',
        'type' => 'switch',
        'value' => true,
    ],
];
