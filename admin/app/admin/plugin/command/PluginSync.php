<?php

declare(strict_types=1);

namespace app\admin\plugin\command;

use app\admin\plugin\service\PluginRebaselineService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 开发环境专用：把插件源同步到已安装位置并重建发布登记。 */
#[AsCommand(name: 'plugin:sync')]
final class PluginSync extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('plugin:sync')
            ->setDescription('开发环境专用：将插件源同步到已安装位置并重建发布登记')
            ->addArgument('code', Argument::REQUIRED, '插件标识');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $result = (new PluginRebaselineService($this->projectRoot))->sync((string) $input->getArgument('code'));
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
