<?php

declare(strict_types=1);

namespace app\console\command;

use app\common\service\identity\IdentityOperationsService;
use app\identity\service\BackchannelLogoutWorker;
use RuntimeException;
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
            ->addOption('tenant', null, Option::VALUE_REQUIRED, '租户 ID')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '每轮最大投递数', '100')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅报告可投递数量');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $tenantId = (int) $input->getOption('tenant');
            if ($tenantId <= 0) throw new RuntimeException('必须提供有效租户 ID');
            $limit = max(1, min(1000, (int) $input->getOption('limit')));
            $worker = new BackchannelLogoutWorker();
            $operations = new IdentityOperationsService();
            if ((bool) $input->getOption('dry-run')) {
                $total = $operations->withLock($tenantId, 'logout-deliveries', fn (): int => $worker->preview($tenantId, $limit));
                $output->writeln(json_encode(['tenant_id' => $tenantId, 'dry_run' => true, 'matched' => $total], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                return 0;
            }
            $total = $operations->withLock($tenantId, 'logout-deliveries', function () use ($worker, $limit, $tenantId, $input): int {
                $total = 0;
                do {
                    $count = $worker->work($limit, $tenantId);
                    $total += $count;
                } while (!$input->getOption('once') && $count > 0);
                return $total;
            });
            $output->writeln(json_encode(['tenant_id' => $tenantId, 'delivered' => $total], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
