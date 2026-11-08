<?php

declare(strict_types=1);

/*
 * 表单列表注册动作的执行配置（含代码生成器产生的自定义动作）。
 *
 * - list_action_store：幂等执行记录使用默认数据库连接上已有的 {前缀}form_list_action_execution 表，
 *   通过独立的非持久连接自动提交，不参与业务事务。
 *   二次确认令牌的 HMAC 密钥无需配置：首次使用时自动生成并保存在同库 {前缀}system_secret 表（secret_table），之后复用。
 */
$database = require __DIR__ . '/database.php';
$connection = (array) ($database['connections'][$database['default']] ?? []);
$dsn = ($connection['driver'] ?? '') === 'sqlite'
    ? 'sqlite:' . (string) ($connection['database'] ?? '')
    : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', (string) ($connection['host'] ?? '127.0.0.1'), (int) ($connection['port'] ?? 3306),
        (string) ($connection['database'] ?? ''), (string) ($connection['charset'] ?? 'utf8mb4'));

return [
    'list_action_store' => [
        'dsn' => $dsn,
        'username' => ($connection['driver'] ?? '') === 'sqlite' ? null : (string) ($connection['username'] ?? ''),
        'password' => ($connection['driver'] ?? '') === 'sqlite' ? null : (string) ($connection['password'] ?? ''),
        'table' => (string) ($connection['prefix'] ?? '') . 'form_list_action_execution',
        'secret_table' => (string) ($connection['prefix'] ?? '') . 'system_secret',
    ],
];
