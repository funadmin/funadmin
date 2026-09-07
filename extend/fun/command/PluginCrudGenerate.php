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

final class PluginCrudGenerate extends Command
{
    use CrudCommandSupport;

    protected function configure(): void
    {
        $this->setName('plugin:crud-generate')->setDescription('使用 preview token 原子生成插件 CRUD')
            ->addArgument('definition', Argument::REQUIRED, '插件 CRUD Definition JSON 文件')
            ->addOption('confirm-token-file', null, Option::VALUE_REQUIRED, '权限必须为 0600 的 token 文件', '');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $definition = $this->loadDefinition((string) $input->getArgument('definition'));
            if (($definition->get('target')['type'] ?? '') !== 'plugin') {
                throw new \InvalidArgumentException('plugin:crud-generate 仅接受插件 target');
            }
            $result = (new CrudGenerator(app()->getRootPath()))->generate(
                $definition,
                $this->readToken((string) $input->getOption('confirm-token-file')),
                [],
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
