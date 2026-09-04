<?php

declare(strict_types=1);

namespace fun\curd;

use app\common\service\AdminWebCrudGenerator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
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
            ->setDescription('根据 JSON 配置生成后台 API 与 Vue CRUD 页面')
            ->addArgument('config', Argument::REQUIRED, '项目目录内的 JSON 配置文件')
            ->addOption('dry', null, Option::VALUE_NONE, '只预览生成文件，不写入')
            ->addOption('force', 'f', Option::VALUE_NONE, '允许覆盖已存在的生成文件');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $result = (new AdminWebCrudGenerator())->run(
                (string) $input->getArgument('config'),
                (bool) $input->getOption('dry'),
                (bool) $input->getOption('force')
            );
            if ($result['output'] !== '') {
                $output->writeln($result['output']);
            }
            $output->info('CRUD 生成完成');
            return 0;
        } catch (Throwable $e) {
            $output->error($e->getMessage());
            return 1;
        }
    }
}
