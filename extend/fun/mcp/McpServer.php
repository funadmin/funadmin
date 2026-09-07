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
            ->addOption('transport', 't', Option::VALUE_OPTIONAL, '传输协议 (stdio)', 'stdio')
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


            if ($transport !== 'stdio') {
                $output->error("不支持的传输协议: {$transport}");
                $output->info('官方 SDK 的 HTTP transport 需要由 PSR-7 Web 入口逐请求接入，不能在 CLI 内直接监听端口');
                return 1;
            }
            if (PHP_OS_FAMILY === 'Windows') {
                $output->error('在Windows系统上，STDIO传输不支持非阻塞管道');
                return 1;
            }

            return $mcpService->startWithStdio();

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
                'db-query' => '执行数据库查询操作（仅支持SELECT语句）',
                'sys-config' => '获取系统配置信息',
                'write-log' => '写入系统日志',
                'file-operation' => '文件读写操作',
                'user-management' => '用户管理相关操作',
                'system-info' => '获取系统运行信息',
                'crud' => '生成后台 API 与 Vue CRUD 页面只读预览',
                'think-command' => '执行安全白名单内的 ThinkPHP 命令',
            ];
            
            foreach ($tools as $name => $description) {
                $output->info("  - {$name}: {$description}");
            }
            
            $output->info('');
            $output->info('=== 可用资源 ===');
            $resources = [
                'config://system' => '系统配置信息',
                'schema://database' => '数据库表结构信息',
            ];
            
            foreach ($resources as $uri => $description) {
                $output->info("  - {$uri}: {$description}");
            }
            
            $output->info('');
            $output->info('=== 使用说明 ===');
            $output->info('启动STDIO服务器: php think mcp start --transport=stdio');
            $output->info('查看服务器信息: php think mcp info');

            return 0;

        } catch (\Exception $e) {
            $output->error('获取服务器信息失败: ' . $e->getMessage());
            return 1;
        }
    }
}
