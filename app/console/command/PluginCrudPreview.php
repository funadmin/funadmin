<?php

declare(strict_types=1);

namespace app\console\command;

use app\common\crud\CrudGenerator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

final class PluginCrudPreview extends Command
{
    use CrudCommandSupport;

    protected function configure(): void
    {
        $this->setName('plugin:crud-preview')->setDescription('预览插件 CRUD 原子生成计划')
            ->addArgument('definition', Argument::REQUIRED, '插件 CRUD Definition JSON 文件')
            ->addOption('token-output', null, Option::VALUE_REQUIRED, '确认 token 输出文件（创建为 0600）', '');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $definition = $this->loadDefinition((string) $input->getArgument('definition'));
            if (($definition->get('target')['type'] ?? '') !== 'plugin') {
                throw new \InvalidArgumentException('plugin:crud-preview 仅接受插件 target');
            }
            $plan = (new CrudGenerator(app()->getRootPath()))->plan($definition);
            $token = (string) ($plan['confirmToken'] ?? '');
            unset($plan['confirmToken']);
            $this->writeToken((string) $input->getOption('token-output'), $token);
            $output->writeln($this->json(['plan' => $plan, 'tokenFile' => (string) $input->getOption('token-output')]));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
