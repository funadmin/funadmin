<?php

declare(strict_types=1);

namespace app\admin\development\command;

use app\common\crud\CrudGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'crud:preview')]
final class CrudPreview extends Command
{
    use CrudCommandSupport;

    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('crud:preview')->setDescription('预览 CRUD 生成计划并输出敏感确认字段')
            ->addArgument('definition', Argument::REQUIRED, 'Definition JSON 文件');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $plan = (new CrudGenerator($this->projectRoot ?? base_path()))->plan($this->loadDefinition((string) $input->getArgument('definition')));
            $token = (string) ($plan['confirmToken'] ?? '');
            unset($plan['confirmToken']);
            $output->writeln($this->json(['plan' => $plan, 'sensitive' => ['confirmToken' => $token]]));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
