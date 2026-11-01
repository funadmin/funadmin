<?php

declare(strict_types=1);

namespace app\admin\development\command;

use app\common\crud\DefinitionValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'crud:validate')]
final class CrudValidate extends Command
{
    use CrudCommandSupport;

    protected function configure(): void
    {
        $this->setName('crud:validate')->setDescription('验证版本化 CRUD Definition')
            ->addArgument('definition', Argument::REQUIRED, 'Definition JSON 文件');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $definition = $this->loadDefinition((string) $input->getArgument('definition'));
            (new DefinitionValidator())->validate($definition, base_path() . DIRECTORY_SEPARATOR);
            $output->writeln($this->json(['valid' => true, 'definitionHash' => $definition->hash()]));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
