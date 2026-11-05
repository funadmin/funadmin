<?php

declare(strict_types=1);

namespace app\admin\ai\command;

use app\admin\ai\service\AiChangeSetTransactionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 按 WAL hash 证明恢复 AI ChangeSet。 */
#[AsCommand(name: 'ai:change-set-recover')]
final class AiChangeSetRecover extends Command
{
    public function __construct(private readonly ?AiChangeSetTransactionService $transactions = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ai:change-set-recover')
            ->setDescription('检查或恢复 AI ChangeSet 事务')
            ->addArgument('transaction', Argument::REQUIRED, '待恢复的 transaction id');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $service = $this->transactions ?? new AiChangeSetTransactionService(base_path() . DIRECTORY_SEPARATOR, (string) config('ai.storage.private_path'));
            $result = $service->recover(trim((string) $input->getArgument('transaction')));
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
