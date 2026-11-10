<?php

declare(strict_types=1);

namespace app\admin\plugin\command;

use app\admin\plugin\service\PluginRebaselineService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 按已安装文件或插件源重建插件发布登记基线。 */
#[AsCommand(name: 'plugin:rebaseline')]
final class PluginRebaseline extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('plugin:rebaseline')
            ->setDescription('按已安装文件或插件源重建插件发布登记（sha256/tree hash）')
            ->addArgument('code', Argument::REQUIRED, '插件标识')
            ->addOption('from', null, Option::VALUE_REQUIRED, '基线来源：installed 或 source', PluginRebaselineService::FROM_INSTALLED)
            ->addOption('force', null, Option::VALUE_NONE, '已安装文件与插件源不一致时仍以所选来源为准')
            ->addOption('dry-run', null, Option::VALUE_NONE, '只列出差异与登记变更，不写库');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $service = new PluginRebaselineService($this->projectRoot);
            $code = (string) $input->getArgument('code');
            $from = (string) $input->getOption('from');
            $force = (bool) $input->getOption('force');
            if ((bool) $input->getOption('dry-run')) {
                $plan = $service->plan($code, $from);
                unset($plan['native'], $plan['files']);
                $plan['dryRun'] = true;
                $plan['blocked'] = ($plan['differences'] !== [] && !$force) || $plan['staleJournals'] !== [];
                $output->writeln(json_encode($plan, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                return 0;
            }
            $result = $service->apply($code, $from, $force);
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
