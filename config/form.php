<?php

declare(strict_types=1);

/**
 * FormSchema 生产注册中心。业务或受信插件只允许通过服务启动扩展这些注册项。
 */
return [
    'idempotency_ttl' => 86400,
    'action_timeout_ms' => 3000,
    'action_max_chain' => 5,
    'data_sources' => [
        // 'customer.search' => ['permission' => 'customer:list', 'parameters' => ['keyword'], 'capabilityVersion' => '1', 'handler' => callable],
    ],
    'validators' => [
        // 'customer.unique-email' => ['capabilityVersion' => '1', 'handler' => callable(mixed $value, array $values, array $options): bool|string],
    ],
    'actions' => [
        // 'customer.recalculate' => ['permission' => 'customer:update', 'parameters' => ['customer_id'], 'capabilityVersion' => '1', 'timeoutMs' => 3000, 'maxChain' => 5, 'handler' => callable],
    ],
];
