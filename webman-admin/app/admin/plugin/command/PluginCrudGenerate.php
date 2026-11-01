<?php

declare(strict_types=1);

namespace app\admin\plugin\command;

use app\admin\development\command\CrudCommandSupport;
use app\common\crud\CrudGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'plugin:crud-generate')]
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
            $result = (new CrudGenerator(base_path() . DIRECTORY_SEPARATOR))->generate(
                $definition,
                $this->readToken((string) $input->getOption('confirm-token-file')),
                [],
                (string) (get_current_user() ?: 'cli')
            );
            unset($result['plan']['confirmToken']);
            $output->writeln($this->json($result));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
