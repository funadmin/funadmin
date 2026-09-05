<?php

declare(strict_types=1);

namespace fun\crud;

use app\common\service\AdminWebCrudGenerator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use Throwable;

/**
 * CRUD 只读预览兼容入口。
 */
final class AdminWebCrud extends Command
{
    protected function configure(): void
    {
        $this->setName('curd')
            ->setDescription('已弃用：兼容转发到只读 crud:preview')
            ->addArgument('config', Argument::REQUIRED, '项目目录内的版本化 CRUD Definition JSON');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $output->warning('curd 已弃用，仅执行只读预览；请改用 crud:preview。');
            $result = (new AdminWebCrudGenerator())->run((string) $input->getArgument('config'), true, false);
            if ($result['output'] !== '') {
                $output->writeln($result['output']);
            }
            $output->info('CRUD preview 完成');
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
