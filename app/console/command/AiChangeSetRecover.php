<?php

declare(strict_types=1);

namespace app\console\command;

use app\console\ai\service\AiChangeSetTransactionService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use Throwable;

/** 按 WAL hash 证明恢复 AI ChangeSet。 */
final class AiChangeSetRecover extends Command
{
    protected function configure(): void
    {
        $this->setName('ai:change-set-recover')
            ->setDescription('检查或恢复 AI ChangeSet 事务')
            ->addArgument('transaction', Argument::REQUIRED, '待恢复的 transaction id');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $service = new AiChangeSetTransactionService(root_path(), (string) config('ai.storage.private_path'));
            $result = $service->recover(trim((string) $input->getArgument('transaction')));
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
