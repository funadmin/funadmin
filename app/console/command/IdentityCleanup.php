<?php

declare(strict_types=1);

namespace app\console\command;

use app\common\service\identity\IdentityOperationsService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use Throwable;

final class IdentityCleanup extends Command
{
    protected function configure(): void
    {
        $this->setName('identity:cleanup')->setDescription('批量清理过期 SSO code/token/session/audit')
            ->addOption('tenant', null, Option::VALUE_REQUIRED, '租户 ID')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '每类最大处理数', '1000')
            ->addOption('audit-retention-days', null, Option::VALUE_REQUIRED, '审计保留天数', '180')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅报告不删除');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $tenantId = (int) $input->getOption('tenant');
            $service = new IdentityOperationsService();
            $result = $service->withLock($tenantId, 'cleanup', fn (): array => $service->cleanup(
                $tenantId,
                (int) $input->getOption('limit'),
                (bool) $input->getOption('dry-run'),
                (int) $input->getOption('audit-retention-days')
            ));
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
