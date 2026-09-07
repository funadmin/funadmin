<?php

declare(strict_types=1);

namespace fun\command;

use fun\plugins\PluginScaffolder;
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
            ->addOption('no-application', null, Option::VALUE_NONE, '不生成独立应用')
            ->addOption('no-console', null, Option::VALUE_NONE, '不生成 Console 应用')
            ->addOption('no-admin-web', null, Option::VALUE_NONE, '不生成 Admin Web 模块');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $result = (new PluginScaffolder(root_path('plugins')))->scaffold(
                (string) $input->getArgument('name'),
                (string) $input->getOption('title'),
                !$input->getOption('no-application'),
                !$input->getOption('no-console'),
                !$input->getOption('no-admin-web')
            );
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
