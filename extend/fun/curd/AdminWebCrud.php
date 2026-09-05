<?php

declare(strict_types=1);

namespace fun\curd;

use app\common\service\AdminWebCrudGenerator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use Throwable;

/**
 * Vue 后台 CRUD 生成命令。
 */
class AdminWebCrud extends Command
{
    protected function configure()
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
        } catch (Throwable $e) {
            $output->error($e->getMessage());
            return 1;
        }
    }
}
