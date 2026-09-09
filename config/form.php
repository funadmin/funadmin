<?php

declare(strict_types=1);

/**
 * FormSchema 生产注册中心。业务或受信插件只允许通过服务启动扩展这些注册项。
 */
return [
    'data_sources' => [
        // 'customer.search' => ['permission' => 'customer:list', 'parameters' => ['keyword'], 'handler' => callable],
    ],
    'validators' => [
        // 'customer.unique-email' => callable(mixed $value, array $values, array $options): bool|string,
    ],
    'actions' => [
        // 'customer.recalculate' => ['permission' => 'customer:update', 'parameters' => ['customer_id'], 'handler' => callable],
    ],
];
