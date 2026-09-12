<?php

declare(strict_types=1);

namespace app\console\command;

use app\common\service\identity\IdentityOperationsService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use Throwable;

final class IdentityHealth extends Command
{
    protected function configure(): void
    {
        $this->setName('identity:health')->setDescription('检查 SSO domain/application/secret/backchannel 健康状态')
            ->addOption('tenant', null, Option::VALUE_REQUIRED, '租户 ID')
            ->addOption('secret-warning-days', null, Option::VALUE_REQUIRED, 'secret 到期告警窗口', '30');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $tenantId = (int) $input->getOption('tenant');
            $service = new IdentityOperationsService();
            $result = $service->withLock($tenantId, 'health', fn (): array => $service->health($tenantId, (int) $input->getOption('secret-warning-days')));
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return ($result['healthy'] ?? false) ? 0 : 2;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
