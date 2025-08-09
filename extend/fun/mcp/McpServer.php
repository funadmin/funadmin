<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: AI Assistant
 * Date: 2024
 */

namespace fun\mcp;

use app\common\service\McpService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Argument;
use think\console\input\Option;

/**
 * MCP服务器命令行工具
 * 用于启动和管理MCP服务器
 */
class McpServer extends Command
{
    /**
     * 配置命令
     */
    protected function configure()
    {
        $this->setName('mcp')
            ->setDescription('启动MCP(Model Context Protocol)服务器')
            ->addArgument('action', Argument::OPTIONAL, '执行的操作 (start|info)', 'start')
            ->addOption('host', 'H', Option::VALUE_OPTIONAL, 'HTTP/SSE服务器监听地址', '127.0.0.1')
            ->addOption('port', 'p', Option::VALUE_OPTIONAL, 'HTTP/SSE服务器监听端口', '8080')
            ->addOption('transport', 't', Option::VALUE_OPTIONAL, '传输协议 (stdio|http|sse)', 'sse')
            ->setHelp('此命令用于启动和管理MCP服务器');
    }

    /**
     * 执行命令
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        $action = $input->getArgument('action');

        switch ($action) {
            case 'start':
                return $this->startServer($input, $output);
                
            case 'info':
                return $this->showInfo($input, $output);
                
            default:
                $output->error("未知的操作: {$action}");
                return 1;
        }
    }

    /**
     * 启动MCP服务器
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function startServer(Input $input, Output $output): int
    {
        try {
            $transport = $input->getOption('transport');
            $mcpService = app(McpService::class);


            $output->info('正在启动FunAdmin MCP服务器...');
            $output->info('服务器信息:');
            
            $serviceInfo = $mcpService->getServiceInfo();
            $output->info("  名称: {$serviceInfo['name']}");
            $output->info("  版本: {$serviceInfo['version']}");
            $output->info("  工具数量: {$serviceInfo['tools']}");
            $output->info("  资源数量: {$serviceInfo['resources']}");
            $output->info("  传输协议: {$transport}");
            if ($transport === 'sse') {
                $host = $input->getOption('host');
                $port = $input->getOption('port');
                
                $output->info('使用SSE传输协议启动服务器...');
                $output->info("监听地址: http://{$host}:{$port}/mcp");
                $output->info('服务器已准备就绪，等待客户端连接...');
                
                // 启动SSE服务器（使用StreamableHttpServerTransport）
                $mcpService->startWithSse($host, $port, 'mcp');
                
            } elseif ($transport === 'stdio') {
                if (PHP_OS_FAMILY === 'Windows') {
                    $output->error('在Windows系统上，STDIO传输不支持非阻塞管道');
                    $output->info('建议使用SSE传输: php think mcp:server start --transport=sse');
                    return 1;
                }
                $output->info('使用STDIO传输协议启动服务器...');
                $output->info('服务器已准备就绪，等待客户端连接...');
                // 启动STDIO服务器
                $mcpService->startWithStdio();
                
            } elseif ($transport === 'http') {
                $host = $input->getOption('host');
                $port = $input->getOption('port');
                
                $output->info("使用HTTP传输协议启动服务器...");
                $output->info("监听地址: http://{$host}:{$port}/mcp");
                $output->info('服务器已准备就绪，等待客户端连接...');
                
                // 启动HTTP服务器（使用HttpServerTransport）
                $mcpService->startWithHttp($host, $port, 'mcp');
                
            } else {
                $output->error("不支持的传输协议: {$transport}");
                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $output->error('启动MCP服务器失败: ' . $e->getMessage());
            $output->error('错误详情: ' . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * 显示服务器信息
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function showInfo(Input $input, Output $output): int
    {
        try {
            $mcpService = McpService::instance();
            $serviceInfo = $mcpService->getServiceInfo();

            $output->info('=== FunAdmin MCP服务器信息 ===');
            $output->info("服务名称: {$serviceInfo['name']}");
            $output->info("服务版本: {$serviceInfo['version']}");
            $output->info("工具数量: {$serviceInfo['tools']}");
            $output->info("资源数量: {$serviceInfo['resources']}");
            $output->info("服务状态: {$serviceInfo['status']}");
            
            $output->info('');
            $output->info('=== 可用工具 ===');
            $tools = [
                'db_query' => '执行数据库查询操作（仅支持SELECT语句）',
                'get_config' => '获取系统配置信息',
                'write_log' => '写入系统日志',
                'file_operation' => '文件读写操作',
                'user_management' => '用户管理相关操作',
                'system_info' => '获取系统运行信息'
            ];
            
            foreach ($tools as $name => $description) {
                $output->info("  - {$name}: {$description}");
            }
            
            $output->info('');
            $output->info('=== 可用资源 ===');
            $resources = [
                'config://system' => '系统配置信息',
                'schema://database' => '数据库表结构信息',
                'docs://api' => 'API接口文档'
            ];
            
            foreach ($resources as $uri => $description) {
                $output->info("  - {$uri}: {$description}");
            }
            
            $output->info('');
            $output->info('=== 使用说明 ===');
            $output->info('启动STDIO服务器: php think mcp:server start');
            $output->info('启动HTTP服务器: php think mcp:server start --transport=http');
            $output->info('查看服务器信息: php think mcp:server info');

            return 0;

        } catch (\Exception $e) {
            $output->error('获取服务器信息失败: ' . $e->getMessage());
            return 1;
        }
    }
}
