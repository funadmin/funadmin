<?php

declare(strict_types=1);

namespace fun\command;

use app\common\crud\DefinitionValidator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use Throwable;

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
            (new DefinitionValidator())->validate($definition, app()->getRootPath());
            $output->writeln($this->json(['valid' => true, 'definitionHash' => $definition->hash()]));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
