<?php

declare(strict_types=1);

namespace app\admin\development\command;

use app\common\crud\CrudGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'crud:generate')]
final class CrudGenerate extends Command
{
    use CrudCommandSupport;

    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

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
            $result = (new CrudGenerator($this->projectRoot ?? base_path()))->generate(
                $this->loadDefinition((string) $input->getArgument('definition')),
                $this->readToken((string) $input->getOption('confirm-token-file')),
                $allowOverwrite,
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
