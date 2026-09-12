<?php

declare(strict_types=1);

namespace app\console\command;

use app\common\plugin\sdk\PluginScaffolder;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/** 生成 Manifest v2 插件开发骨架。 */
final class PluginMake extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:make')->setDescription('生成 Manifest v2 插件开发骨架')
            ->addArgument('name', Argument::REQUIRED, '插件名称')
            ->addOption('title', null, Option::VALUE_REQUIRED, '插件标题', '')
            ->addOption('application', null, Option::VALUE_NONE, '生成独立应用')
            ->addOption('console', null, Option::VALUE_NONE, '生成 Console 应用')
            ->addOption('admin-web', null, Option::VALUE_NONE, '生成 Admin Web 模块')
            ->addOption('preview', null, Option::VALUE_NONE, '仅输出生成计划，不写入文件')
            ->addOption('no-application', null, Option::VALUE_NONE, '不生成独立应用')
            ->addOption('no-console', null, Option::VALUE_NONE, '不生成 Console 应用')
            ->addOption('no-admin-web', null, Option::VALUE_NONE, '不生成 Admin Web 模块');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $scaffolder = new PluginScaffolder(root_path('plugins'));
            $name = (string) $input->getArgument('name');
            $positiveSelection = $input->getOption('application') || $input->getOption('console') || $input->getOption('admin-web');
            $application = ($positiveSelection ? (bool) $input->getOption('application') : true) && !$input->getOption('no-application');
            $console = ($positiveSelection ? (bool) $input->getOption('console') : true) && !$input->getOption('no-console');
            $adminWeb = ($positiveSelection ? (bool) $input->getOption('admin-web') : true) && !$input->getOption('no-admin-web');
            $result = $input->getOption('preview')
                ? $scaffolder->plan($name, $application, $console, $adminWeb)
                : $scaffolder->scaffold($name, (string) $input->getOption('title'), $application, $console, $adminWeb);
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
