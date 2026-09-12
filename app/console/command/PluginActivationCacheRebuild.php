<?php

declare(strict_types=1);

namespace app\console\command;

use app\console\plugin\service\PluginService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/** 手动重建可信插件激活状态清单。 */
final class PluginActivationCacheRebuild extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:activation-cache')->setDescription('重建可信插件激活状态清单');
    }

    protected function execute(Input $input, Output $output): int
    {
        app(PluginService::class)->refreshActivationCache();
        $output->writeln('<info>可信插件激活状态清单已重建</info>');
        return 0;
    }
}
