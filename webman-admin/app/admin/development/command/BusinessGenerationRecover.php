<?php

declare(strict_types=1);

namespace app\admin\development\command;

use app\admin\development\service\GenerationTransactionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 检查并恢复业务源码生成 WAL。 */
#[AsCommand(name: 'business:generation-recover')]
final class BusinessGenerationRecover extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('business:generation-recover')
            ->setDescription('检查或恢复业务源码生成事务')
            ->addArgument('transaction', Argument::OPTIONAL, '待检查或恢复的 transaction id', '')
            ->addOption('all-stale', null, Option::VALUE_NONE, '恢复全部未完成事务')
            ->addOption('inspect', null, Option::VALUE_NONE, '只读检查，不执行恢复');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $service = GenerationTransactionService::production($this->projectRoot);
            $transaction = trim((string) $input->getArgument('transaction'));
            $all = (bool) $input->getOption('all-stale');
            $inspect = (bool) $input->getOption('inspect');
            if ($transaction === '' && !$all) {
                (new SymfonyStyle($input, $output))->error('请提供 transaction 或 --all-stale');
                return 1;
            }
            if ($transaction !== '') {
                $journal = $service->inspect($transaction);
                if (!$inspect) {
                    if (($journal['state'] ?? '') === 'recovery_required') {
                        throw new \RuntimeException('事务需要人工恢复，拒绝自动猜测');
                    }
                    $journal = $service->recover($transaction);
                }
                $output->writeln($this->encode($journal));
                return 0;
            }
            $results = [];
            foreach ($service->stale() as $journal) {
                if (($journal['state'] ?? '') === 'recovery_required') {
                    $results[] = $journal;
                    continue;
                }
                $results[] = $inspect ? $journal : $service->recover((string) $journal['transaction_id']);
            }
            $output->writeln($this->encode($results));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
