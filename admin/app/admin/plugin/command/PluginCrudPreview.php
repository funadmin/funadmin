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

#[AsCommand(name: 'plugin:crud-preview')]
final class PluginCrudPreview extends Command
{
    use CrudCommandSupport;

    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

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
            $plan = (new CrudGenerator($this->projectRoot ?? base_path()))->plan($definition);
            $token = (string) ($plan['confirmToken'] ?? '');
            unset($plan['confirmToken']);
            $this->writeToken((string) $input->getOption('token-output'), $token);
            $output->writeln($this->json(['plan' => $plan, 'tokenFile' => (string) $input->getOption('token-output')]));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
