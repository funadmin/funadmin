<?php

declare(strict_types=1);

namespace fun\command;

use app\common\crud\CrudGenerator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use Throwable;

final class CrudPreview extends Command
{
    use CrudCommandSupport;

    protected function configure(): void
    {
        $this->setName('crud:preview')->setDescription('预览 CRUD 生成计划并输出敏感确认字段')
            ->addArgument('definition', Argument::REQUIRED, 'Definition JSON 文件');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $plan = (new CrudGenerator(app()->getRootPath()))->plan($this->loadDefinition((string) $input->getArgument('definition')));
            $token = (string) ($plan['confirmToken'] ?? '');
            unset($plan['confirmToken']);
            $output->writeln($this->json(['plan' => $plan, 'sensitive' => ['confirmToken' => $token]]));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
