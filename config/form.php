<?php

declare(strict_types=1);

/**
 * FormSchema 生产注册中心。业务或受信插件只允许通过服务启动扩展这些注册项。
 */
return [
    // 确认密钥至少 32 字节；缺失时禁用列表动作，不提供内置密钥。
    'list_confirmation_secret' => \think\facade\Env::get('form.list_confirmation_secret', ''),
    // 必须指向已迁移的数据库；专用非持久连接不参与业务事务，不自动建表。
    // 自定义数据库前缀时，table 必须填写迁移后的完整物理表名。
    'list_action_store' => [
        'dsn' => \think\facade\Env::get('form.list_action_store_dsn', ''),
        'username' => \think\facade\Env::get('form.list_action_store_username', ''),
        'password' => \think\facade\Env::get('form.list_action_store_password', ''),
        'table' => \think\facade\Env::get('form.list_action_store_table', 'fun_form_list_action_execution'),
    ],
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
