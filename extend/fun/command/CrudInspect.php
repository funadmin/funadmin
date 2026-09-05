<?php

declare(strict_types=1);

namespace fun\command;

use app\backend\service\DevCrudService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

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
            $service = new DevCrudService(app()->getRootPath(), (array) config('crud.connections', []));
            $output->writeln(json_encode($service->infer((string) $input->getOption('connection'), (string) $input->getArgument('table')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
