<?php

declare(strict_types=1);

namespace fun\command;

use app\console\service\PluginInfrastructureService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/** 检查并恢复插件 publication journal。 */
final class PluginPublicationRecover extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:publication-recover')
            ->setDescription('检查或恢复插件 publication journal')
            ->addArgument('token', Argument::OPTIONAL, '待检查或恢复的 operation token', '')
            ->addOption('all-stale', null, Option::VALUE_NONE, '恢复全部未完成 journal')
            ->addOption('inspect', null, Option::VALUE_NONE, '只读检查，不执行恢复');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $infrastructure = new PluginInfrastructureService();
            $publisher = $infrastructure->appPublisher();
            $token = trim((string) $input->getArgument('token'));
            $all = (bool) $input->getOption('all-stale');
            $inspect = (bool) $input->getOption('inspect');
            if ($token === '' && !$all) {
                $output->error('请提供 token 或 --all-stale');
                return 1;
            }
            if ($token !== '') {
                $journal = $publisher->inspect($token);
                if (!$inspect) {
                    $infrastructure->recoverPublication($token, ($journal['manual_recovery'] ?? false) === true);
                    $journal = $publisher->inspect($token);
                }
                $output->writeln(json_encode($journal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                return 0;
            }
            $results = [];
            foreach ($publisher->stale() as $journal) {
                $journalToken = (string) $journal['token'];
                if (!$inspect && ($journal['manual_recovery'] ?? false) !== true) {
                    $infrastructure->recoverPublication($journalToken);
                }
                $results[] = $publisher->inspect($journalToken);
            }
            $output->writeln(json_encode($results, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
