<?php

declare(strict_types=1);

namespace app\console\command;

use app\console\ai\service\AiOutboxDispatcher;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use Throwable;

/** 投递审批恢复 outbox，可由常驻进程或计划任务重复执行。 */
final class AiOutboxDispatch extends Command
{
    protected function configure(): void
    {
        $this->setName('ai:outbox-dispatch')->setDescription('投递待处理的 AI outbox');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $output->writeln(json_encode(['dispatched'=>AiOutboxDispatcher::database()->dispatch()], JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
