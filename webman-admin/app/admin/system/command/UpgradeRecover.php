<?php

declare(strict_types=1);

namespace app\admin\system\command;

use app\admin\system\service\UpgradeService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 安全恢复租约已过期的系统升级任务。 */
#[AsCommand(name: 'upgrade:recover-stale')]
final class UpgradeRecover extends Command
{
    protected function configure(): void
    {
        $this->setName('upgrade:recover-stale')->setDescription('恢复或释放租约已过期的系统升级任务');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $result = UpgradeService::instance()->recoverStale();
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
