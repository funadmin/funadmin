#!/usr/bin/env php
<?php
/**
 * FunAdmin MCP服务器启动脚本（稳定版）
 * 增强连接稳定性，解决断线问题
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: AI Assistant
 * Date: 2024
 */

declare(strict_types=1);

// 检查PHP版本
echo "检查PHP版本: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    fwrite(STDERR, "PHP版本过低，需要PHP 8.1.0或更高版本\n");
    exit(1);
}
echo "✓ PHP版本检查通过\n";

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 定义应用路径
define('APP_PATH', __DIR__ . '/');
echo "应用路径: " . APP_PATH . "\n";

// 检查composer autoload
echo "检查composer autoload...\n";
if (!file_exists('vendor/autoload.php')) {
    fwrite(STDERR, "找不到composer autoload文件，请运行 composer install\n");
    exit(1);
}

require_once 'vendor/autoload.php';
echo "✓ Composer autoload加载成功\n";

// 正确初始化ThinkPHP应用
echo "初始化ThinkPHP应用...\n";
try {
    $app = new \think\App();
    $app->initialize();
    echo "✓ ThinkPHP应用初始化成功\n";
} catch (\Throwable $e) {
    echo "✗ ThinkPHP初始化失败: " . $e->getMessage() . "\n";
    exit(1);
}

// 加载配置文件
$configFile = __DIR__ . '/config/mcp.php';
if (file_exists($configFile)) {
    $mcpConfig = config('mcp');
} else {
    $mcpConfig = [];
}

// 获取传输协议配置
$transport = $mcpConfig['transport'] ?? 'sse';
$host = $mcpConfig['host'] ?? '127.0.0.1';
$port = (int)($mcpConfig['port'] ?? 8080);
$mcpPath = $mcpConfig['path'] ?? 'mcp';

// 设置超时和内存限制
$timeout = $mcpConfig['timeout'] ?? 300000;
$memoryLimit = $mcpConfig['memory_limit'] ?? '512M';
$maxExecutionTime = $mcpConfig['max_execution_time'] ?? 0;

// 应用配置
ini_set('memory_limit', $memoryLimit);
ini_set('max_execution_time', $maxExecutionTime);
set_time_limit(0);
ignore_user_abort(true);

// 设置错误处理
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/runtime/log/mcp_error.log');

echo "=== FunAdmin MCP服务器启动（稳定版）===\n";
echo "传输协议: {$transport}\n";
echo "监听地址: {$host}:{$port}\n";
echo "超时设置: {$timeout}ms\n";
echo "内存限制: {$memoryLimit}\n";
echo "执行时间限制: {$maxExecutionTime}s\n";

use app\common\service\McpService;
use think\facade\Log;

/**
 * 标准错误输出日志记录器
 */
class StderrLogger extends \Psr\Log\AbstractLogger
{
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        fwrite(STDERR, sprintf(
                "[%s] [%s] %s%s\n",
                date('Y-m-d H:i:s'),
                strtoupper($level),
                $message,
                $contextStr
        ));
    }
}

/**
 * 信号处理函数
 */
function handleSignal($signal) {
    $logger = new StderrLogger();
    $logger->info("接收到信号: {$signal}，正在优雅关闭服务器...");

    // 清理资源
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    exit(0);
}

// 注册信号处理器
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, 'handleSignal');
    pcntl_signal(SIGINT, 'handleSignal');
    pcntl_signal(SIGHUP, 'handleSignal');
}

/**
 * 连接监控函数
 */
function monitorConnection($mcpService, $logger) {
    $lastHeartbeat = time();
    $heartbeatInterval = 30; // 30秒心跳间隔

    while (true) {
        try {
            $currentTime = time();

            // 检查心跳
            if ($currentTime - $lastHeartbeat >= $heartbeatInterval) {
                $logger->debug('发送心跳信号');
                $lastHeartbeat = $currentTime;

                // 检查内存使用
                $memoryUsage = memory_get_usage(true);
                $memoryLimit = ini_get('memory_limit');
                $logger->debug('内存使用情况', [
                        'usage' => formatBytes($memoryUsage),
                        'limit' => $memoryLimit
                ]);
            }

            // 检查连接状态
            $serviceInfo = $mcpService->getServiceInfo();
            if ($serviceInfo['status'] !== 'ready') {
                $logger->warning('服务状态异常', $serviceInfo);
            }

            sleep(5); // 每5秒检查一次

        } catch (\Throwable $e) {
            $logger->error('连接监控错误: ' . $e->getMessage());
            sleep(10); // 错误后等待10秒
        }
    }
}

/**
 * 格式化字节数
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

try {
    $logger = new StderrLogger();
    $logger->info("正在启动FunAdmin MCP服务器 (传输协议: {$transport})...");

    echo "创建MCP服务实例...\n";

    // 创建MCP服务实例并设置日志记录器
    $mcpService = app(McpService::class);
    $mcpService->setLogger($logger);

    echo "✓ MCP服务实例创建成功\n";

    $serviceInfo = $mcpService->getServiceInfo();

    $logger->info('服务器配置信息:', [
            'name' => $serviceInfo['name'],
            'version' => $serviceInfo['version'],
            'tools' => $serviceInfo['tools'],
            'resources' => $serviceInfo['resources'],
            'transport' => $transport,
            'timeout' => $timeout,
            'memory_limit' => $memoryLimit,
            'config' => $serviceInfo['config'] ?? []
    ]);

    // 启动连接监控（在后台线程中）
    if (function_exists('pcntl_fork')) {
        $monitorPid = pcntl_fork();
        if ($monitorPid == 0) {
            // 子进程执行监控
            monitorConnection($mcpService, $logger);
            exit(0);
        }
    }

    // 根据传输协议启动服务器
    switch ($transport) {
        case 'stdio':
            if (PHP_OS_FAMILY === 'Windows') {
                echo "✗ 在Windows系统上，STDIO传输不支持非阻塞管道\n";
                echo "建议使用SSE传输: MCP_TRANSPORT=sse php mcp-server-stable.php\n";
                exit(1);
            }

            echo "使用STDIO传输协议启动服务器...\n";
            echo "服务器已准备就绪，等待客户端连接...\n";
            $mcpService->startWithStdio();
            break;

        case 'http':
            echo "使用HTTP传输协议启动服务器...\n";
            echo "监听地址: http://{$host}:{$port}/{$mcpPath}\n";
            echo "服务器已准备就绪，等待HTTP连接...\n";
            $mcpService->startWithHttp($host, $port, $mcpPath);
            break;

        case 'sse':
            echo "使用SSE传输协议启动服务器...\n";
            echo "监听地址: http://{$host}:{$port}/{$mcpPath}\n";
            echo "服务器已准备就绪，等待SSE连接...\n";
            $mcpService->startWithSse($host, $port, $mcpPath);
            break;

        default:
            echo "✗ 不支持的传输协议: {$transport}\n";
            echo "支持的协议: stdio, http, sse\n";
            echo "使用示例:\n";
            echo "  MCP_TRANSPORT=stdio php mcp-server-stable.php\n";
            echo "  MCP_TRANSPORT=http php mcp-server-stable.php\n";
            echo "  MCP_TRANSPORT=sse php mcp-server-stable.php\n";
            exit(1);
    }

    $logger->info('MCP服务器已正常关闭');
    exit(0);

} catch (\Throwable $e) {
    $logger = new StderrLogger();
    $logger->critical('MCP服务器启动失败', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
    ]);

    echo "\n=== 详细错误信息 ===\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈跟踪:\n" . $e->getTraceAsString() . "\n";

    // 记录到ThinkPHP日志
    if (class_exists('\think\facade\Log')) {
        Log::critical('MCP服务器启动失败', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
        ]);
    }

    exit(1);
}

#mcp 配置
// {
//     "mcpServers": {
//       "funadmin": {
//         "url":"127.0.0.1:8081/mcp"
//       }
//     }
//   }
