<?php

declare(strict_types=1);

namespace app\admin\plugin\command;

use app\admin\plugin\service\PluginService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use support\Container;

/** 手动重建可信插件激活状态清单。 */
#[AsCommand(name: 'plugin:activation-cache')]
final class PluginActivationCacheRebuild extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('plugin:activation-cache')->setDescription('重建可信插件激活状态清单');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $service = $this->projectRoot === null ? Container::get(PluginService::class) : new PluginService($this->projectRoot);
            $service->refreshActivationCache();
            $output->writeln('<info>可信插件激活状态清单已重建</info>');
            return 0;
        } catch (\Throwable $exception) {
            (new \Symfony\Component\Console\Style\SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
