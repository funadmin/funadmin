<?php

declare(strict_types=1);

namespace app\admin\ai\command;

use app\admin\ai\service\AiOutboxDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 投递审批恢复 outbox，可由常驻进程或计划任务重复执行。 */
#[AsCommand(name: 'ai:outbox-dispatch')]
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
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
