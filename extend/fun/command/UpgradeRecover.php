<?php

declare(strict_types=1);

namespace fun\command;

use app\backend\service\UpgradeService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Throwable;

/** 安全恢复租约已过期的系统升级任务。 */
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
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
