<?php

declare(strict_types=1);

namespace app\console\command;

use app\common\service\identity\IdentityOperationsService;
use app\common\service\identity\SigningKeyService;
use RuntimeException;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;
use Throwable;

final class IdentitySigningKey extends Command
{
    protected function configure(): void
    {
        $this->setName('identity:signing-key')->setDescription('轮换或退役 OIDC signing key')
            ->addOption('tenant', null, Option::VALUE_REQUIRED, '租户 ID')
            ->addOption('action', null, Option::VALUE_REQUIRED, 'rotate 或 retire', 'retire')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '最大退役数', '100')
            ->addOption('publish-window', null, Option::VALUE_REQUIRED, '旧 key 发布窗口秒数', '86400')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅报告；rotate 不支持 dry-run');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $tenantId = (int) $input->getOption('tenant');
            $action = (string) $input->getOption('action');
            $dryRun = (bool) $input->getOption('dry-run');
            if (!in_array($action, ['rotate', 'retire'], true)) throw new RuntimeException('action 必须为 rotate 或 retire');
            if ($action === 'rotate' && $dryRun) throw new RuntimeException('rotate 会生成私钥，不支持 dry-run');
            $operations = new IdentityOperationsService();
            $keys = new SigningKeyService();
            $result = $operations->withLock($tenantId, 'signing-key', fn (): array => $action === 'rotate'
                ? ['tenant_id' => $tenantId, 'rotated' => $keys->rotate($tenantId, (int) $input->getOption('publish-window'))]
                : $keys->retireExpired($tenantId, (int) $input->getOption('limit'), $dryRun));
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
