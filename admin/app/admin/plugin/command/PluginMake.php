<?php

declare(strict_types=1);

namespace app\admin\plugin\command;

use app\common\plugin\sdk\PluginScaffolder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 生成 Manifest v2 插件开发骨架。 */
#[AsCommand(name: 'plugin:make')]
final class PluginMake extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('plugin:make')->setDescription('生成 Manifest v2 插件开发骨架')
            ->addArgument('name', Argument::REQUIRED, '插件名称')
            ->addOption('title', null, Option::VALUE_REQUIRED, '插件标题', '')
            ->addOption('application', null, Option::VALUE_NONE, '生成独立应用')
            ->addOption('admin', null, Option::VALUE_NONE, '生成 Admin 应用')
            ->addOption('web', null, Option::VALUE_NONE, '生成 Admin Web 模块')
            ->addOption('preview', null, Option::VALUE_NONE, '仅输出生成计划，不写入文件')
            ->addOption('no-application', null, Option::VALUE_NONE, '不生成独立应用')
            ->addOption('no-admin', null, Option::VALUE_NONE, '不生成 Admin 应用')
            ->addOption('no-web', null, Option::VALUE_NONE, '不生成 Admin Web 模块');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $scaffolder = new PluginScaffolder(($this->projectRoot ?? base_path()) . '/plugins');
            $name = (string) $input->getArgument('name');
            $positiveSelection = $input->getOption('application') || $input->getOption('admin') || $input->getOption('web');
            $application = ($positiveSelection ? (bool) $input->getOption('application') : true) && !$input->getOption('no-application');
            $admin = ($positiveSelection ? (bool) $input->getOption('admin') : true) && !$input->getOption('no-admin');
            $adminWeb = ($positiveSelection ? (bool) $input->getOption('web') : true) && !$input->getOption('no-web');
            $result = $input->getOption('preview')
                ? $scaffolder->plan($name, $application, $admin, $adminWeb)
                : $scaffolder->scaffold($name, (string) $input->getOption('title'), $application, $admin, $adminWeb);
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
