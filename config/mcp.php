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
    'port' => env('MCP_PORT', 8080), // 修改默认端口避免冲突
    'path' => env('MCP_PATH', 'mcp'),
    
    // 连接超时配置（毫秒）- 优化超时设置
    'timeout' => env('MCP_TIMEOUT', 300000), // 5分钟总超时
    'connect_timeout' => env('MCP_CONNECT_TIMEOUT', 30000), // 30秒连接超时
    'read_timeout' => env('MCP_READ_TIMEOUT', 60000), // 60秒读取超时
    'write_timeout' => env('MCP_WRITE_TIMEOUT', 30000), // 30秒写入超时
    
    // 重试配置 - 增加重试机制
    'retry_attempts' => env('MCP_RETRY_ATTEMPTS', 5), // 增加重试次数
    'retry_delay' => env('MCP_RETRY_DELAY', 2000), // 增加重试延迟
    'retry_backoff' => env('MCP_RETRY_BACKOFF', 1.5), // 重试退避倍数
    
    // 心跳配置 - 新增心跳机制
    'heartbeat_enabled' => env('MCP_HEARTBEAT_ENABLED', true),
    'heartbeat_interval' => env('MCP_HEARTBEAT_INTERVAL', 30), // 30秒心跳间隔
    'heartbeat_timeout' => env('MCP_HEARTBEAT_TIMEOUT', 90), // 90秒心跳超时
    
    // 连接池配置
    'pool_enabled' => env('MCP_POOL_ENABLED', true),
    'pool_size' => env('MCP_POOL_SIZE', 20), // 增加连接池大小
    'pool_timeout' => env('MCP_POOL_TIMEOUT', 5000), // 连接池超时
    
    // 调试模式
    'debug' => env('MCP_DEBUG', false),
    
    // 日志级别
    'log_level' => env('MCP_LOG_LEVEL', 'info'),
    'log_connection_events' => env('MCP_LOG_CONNECTION_EVENTS', true), // 记录连接事件
    
    // 内存和执行时间限制 - 优化资源限制
    'memory_limit' => env('MCP_MEMORY_LIMIT', '512M'), // 增加内存限制
    'max_execution_time' => env('MCP_MAX_EXECUTION_TIME', 0), // 无限制执行时间
    
    // 缓冲区配置
    'buffer_size' => env('MCP_BUFFER_SIZE', 16384), // 增加缓冲区大小
    'output_buffering' => env('MCP_OUTPUT_BUFFERING', true),
    
    // 错误处理配置
    'error_handling' => [
        'log_errors' => true,
        'display_errors' => false,
        'error_reporting' => E_ALL,
        'catch_fatal_errors' => true,
        'log_connection_errors' => true,
    ],
    
    // 性能优化配置
    'performance' => [
        'enable_gzip' => true,
        'enable_cache' => true,
        'cache_ttl' => 3600,
        'enable_keep_alive' => true,
        'keep_alive_timeout' => 300, // 5分钟keep-alive
        'max_requests_per_connection' => 1000,
    ],
    
    // 安全配置
    'security' => [
        'enable_cors' => true,
        'allowed_origins' => ['*'],
        'rate_limit_enabled' => false,
        'rate_limit_requests' => 1000,
        'rate_limit_window' => 3600,
    ],
    
    // 监控配置
    'monitoring' => [
        'enable_metrics' => true,
        'metrics_interval' => 60, // 60秒收集一次指标
        'enable_health_check' => true,
        'health_check_interval' => 30, // 30秒健康检查
    ],
];
