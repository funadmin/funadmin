<?php

declare(strict_types=1);

namespace app\admin\development\command;

use app\admin\development\service\DevCrudService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'crud:inspect')]
final class CrudInspect extends Command
{
    protected function configure(): void
    {
        $this->setName('crud:inspect')->setDescription('只读检查白名单连接的数据表结构')
            ->addArgument('table', Argument::REQUIRED, '数据表名')
            ->addOption('connection', null, Option::VALUE_REQUIRED, '配置连接名', 'mysql');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $service = new DevCrudService(base_path() . DIRECTORY_SEPARATOR, (array) config('crud.connections', []));
            $output->writeln(json_encode($service->infer((string) $input->getOption('connection'), (string) $input->getArgument('table')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
