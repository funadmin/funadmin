<?php

declare(strict_types=1);

namespace fun\command;

use app\console\service\PluginService;
use think\console\Command;
use think\console\Input;
use think\console\Output;

/** 手动重建已启用插件的应用级运行时清单。 */
final class PluginRuntimeCacheRebuild extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:runtime-cache')->setDescription('重建插件运行时编译清单');
    }

    protected function execute(Input $input, Output $output): int
    {
        app(PluginService::class)->refreshRuntimeCache();
        $output->writeln('<info>插件运行时编译清单已重建</info>');
        return 0;
    }
}
