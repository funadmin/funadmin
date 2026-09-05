<?php

declare(strict_types=1);

namespace fun\command;

use app\common\crud\CrudGenerator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

final class CrudGenerate extends Command
{
    use CrudCommandSupport;

    protected function configure(): void
    {
        $this->setName('crud:generate')->setDescription('使用 preview token 生成 CRUD 文件')
            ->addArgument('definition', Argument::REQUIRED, 'Definition JSON 文件')
            ->addOption('confirm-token-file', null, Option::VALUE_REQUIRED, '权限必须为 0600 的 token 文件', '')
            ->addOption('allow-overwrite', null, Option::VALUE_REQUIRED, '允许覆盖的项目相对路径，逗号分隔', '');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $allowOverwrite = array_values(array_filter(array_map('trim', explode(',', (string) $input->getOption('allow-overwrite')))));
            $result = (new CrudGenerator(app()->getRootPath()))->generate(
                $this->loadDefinition((string) $input->getArgument('definition')),
                $this->readToken((string) $input->getOption('confirm-token-file')),
                $allowOverwrite,
                (string) (get_current_user() ?: 'cli')
            );
            unset($result['plan']['confirmToken']);
            $output->writeln($this->json($result));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
