<?php
/**
 * MCP 服务配置文件
 * 优化连接稳定性，解决断线问题
 */

return [
    // 传输协议配置
    'transport' => env('MCP_TRANSPORT', 'sse'), // stdio, http, sse
    
    // 服务器监听配置
    'host' => env('MCP_HOST', '127.0.0.1'),
    'port' => env('MCP_PORT', 8080),
    'path' => env('MCP_PATH', 'mcp'),
    
    // 连接超时配置（毫秒）
    'timeout' => env('MCP_TIMEOUT', 0),
    'connect_timeout' => env('MCP_CONNECT_TIMEOUT', 10000),
    'read_timeout' => env('MCP_READ_TIMEOUT', 30000),
    
    // 重试配置
    'retry_attempts' => env('MCP_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('MCP_RETRY_DELAY', 1000),
    
    // 调试模式
    'debug' => env('MCP_DEBUG', false),
    
    // 日志级别
    'log_level' => env('MCP_LOG_LEVEL', 'info'),
    
    // 内存限制
    'memory_limit' => env('MCP_MEMORY_LIMIT', '256M'),
    
    // 执行时间限制（秒，0表示无限制）
    'max_execution_time' => env('MCP_MAX_EXECUTION_TIME', 0),
    
    // 缓冲区大小
    'buffer_size' => env('MCP_BUFFER_SIZE', 8192),
    
    // 心跳间隔（秒）
    'heartbeat_interval' => env('MCP_HEARTBEAT_INTERVAL', 30),
    
    // 连接池大小
    'pool_size' => env('MCP_POOL_SIZE', 10),
    
    // 错误处理
    'error_handling' => [
        'log_errors' => true,
        'display_errors' => false,
        'error_reporting' => E_ALL,
    ],
    
    // 性能优化
    'performance' => [
        'enable_gzip' => true,
        'enable_cache' => true,
        'cache_ttl' => 3600,
    ],
];
