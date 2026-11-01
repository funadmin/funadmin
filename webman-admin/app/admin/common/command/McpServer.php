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

namespace app\admin\common\command;

use app\common\service\McpService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Style\SymfonyStyle;
use support\Container;

/**
 * MCP服务器命令行工具
 * 用于启动和管理MCP服务器
 */
#[AsCommand(name: 'mcp')]
class McpServer extends Command
{
    /**
     * 配置命令
     */
    protected function configure(): void
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
    protected function execute(Input $input, Output $output): int
    {
        $action = $input->getArgument('action');

        switch ($action) {
            case 'start':
                return $this->startServer($input, $output);
                
            case 'info':
                return $this->showInfo($input, $output);
                
            default:
                (new SymfonyStyle($input, $output))->error("未知的操作: {$action}");
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
            $mcpService = Container::get(McpService::class);

            if ($transport !== 'stdio') {
                fwrite(STDERR, "不支持的传输协议: {$transport}\n");
                fwrite(STDERR, "官方 SDK 的 HTTP transport 需要由 PSR-7 Web 入口逐请求接入，不能在 CLI 内直接监听端口\n");
                return 1;
            }
            if (PHP_OS_FAMILY === 'Windows') {
                fwrite(STDERR, "在Windows系统上，STDIO传输不支持非阻塞管道\n");
                return 1;
            }

            return $mcpService->startWithStdio();

        } catch (\Throwable $e) {
            fwrite(STDERR, '启动MCP服务器失败: ' . $e->getMessage() . PHP_EOL);
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
        $io = new SymfonyStyle($input, $output);
        try {
            $mcpService = Container::get(McpService::class);
            $serviceInfo = $mcpService->getServiceInfo();

            $io->info('=== FunAdmin MCP服务器信息 ===');
            $io->info("服务名称: {$serviceInfo['name']}");
            $io->info("服务版本: {$serviceInfo['version']}");
            $io->info("工具数量: {$serviceInfo['tools']}");
            $io->info("资源数量: {$serviceInfo['resources']}");
            $io->info("服务状态: {$serviceInfo['status']}");
            
            $io->newLine();
            $io->info('=== 可用工具 ===');
            $tools = [
                'db-query' => '执行数据库查询操作（仅支持SELECT语句）',
                'sys-config' => '获取系统配置信息',
                'write-log' => '写入系统日志',
                'file-operation' => '文件读写操作',
                'user-management' => '用户管理相关操作',
                'system-info' => '获取系统运行信息',
                'crud' => '生成后台 API 与 Vue CRUD 页面只读预览',
                'think-command' => '执行安全白名单内的 Webman 命令',
            ];
            
            foreach ($tools as $name => $description) {
                $io->info("  - {$name}: {$description}");
            }
            
            $io->newLine();
            $io->info('=== 可用资源 ===');
            $resources = [
                'config://system' => '系统配置信息',
                'schema://database' => '数据库表结构信息',
            ];
            
            foreach ($resources as $uri => $description) {
                $io->info("  - {$uri}: {$description}");
            }
            
            $io->newLine();
            $io->info('=== 使用说明 ===');
            $io->info('启动STDIO服务器: php webman mcp start --transport=stdio');
            $io->info('查看服务器信息: php webman mcp info');

            return 0;

        } catch (\Throwable $e) {
            $io->error('获取服务器信息失败: ' . $e->getMessage());
            return 1;
        }
    }
}
