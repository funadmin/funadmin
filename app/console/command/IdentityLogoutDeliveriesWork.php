<?php

declare(strict_types=1);

namespace app\console\command;

use app\identity\service\BackchannelLogoutWorker;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use Throwable;

/** 扫描并投递 OIDC backchannel logout 队列。 */
final class IdentityLogoutDeliveriesWork extends Command
{
    protected function configure(): void
    {
        $this->setName('identity:logout-deliveries:work')
            ->setDescription('投递 OIDC backchannel logout deliveries')
            ->addOption('once', null, Option::VALUE_NONE, '只执行一轮')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '每轮最大投递数', '100');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $worker = new BackchannelLogoutWorker();
            $total = 0;
            do {
                $count = $worker->work((int) $input->getOption('limit'));
                $total += $count;
            } while (!$input->getOption('once') && $count > 0);
            $output->writeln(json_encode(['delivered' => $total], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
