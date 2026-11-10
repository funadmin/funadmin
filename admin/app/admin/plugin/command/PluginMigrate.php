<?php

declare(strict_types=1);

namespace app\admin\plugin\command;

use app\admin\plugin\service\PluginMigrationAdoptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 接管效果已存在的插件 migration：核对当前结构后只登记，不执行。 */
#[AsCommand(name: 'plugin:migrate')]
final class PluginMigrate extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('plugin:migrate')
            ->setDescription('接管插件 migration：核对其效果（表、列、索引）已存在后只登记 system_migration，不执行 SQL')
            ->addArgument('code', Argument::REQUIRED, '插件标识')
            ->addOption('adopt', null, Option::VALUE_REQUIRED, '要接管的 migration 版本（文件名，不含目录）')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只核对并输出每项效果，不写库；不可接管时退出码为 1');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $version = (string) $input->getOption('adopt');
            if ($version === '') {
                throw new \InvalidArgumentException('必须用 --adopt=<version> 指定要接管的 migration；正常执行 migration 请走插件更新/迁移流程');
            }
            $result = (new PluginMigrationAdoptionService($this->projectRoot))
                ->adopt((string) $input->getArgument('code'), $version, (bool) $input->getOption('dry-run'));
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            // dry-run 的退出码也表达可否接管：不可接管（正式执行会被拒绝）返回 1，JSON 照常输出。
            return $result['alreadyRecorded'] || $result['adoptable'] ? 0 : 1;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
