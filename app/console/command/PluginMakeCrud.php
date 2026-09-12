<?php

declare(strict_types=1);

namespace app\console\command;

use app\common\crud\PluginCrudDefinitionFactory;
use app\console\development\service\DevCrudService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

final class PluginMakeCrud extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:make-crud')->setDescription('从数据表推断插件 CRUD Definition 并安全预览')
            ->addArgument('plugin', Argument::REQUIRED, '插件名称')
            ->addArgument('entity', Argument::REQUIRED, '实体名称')
            ->addOption('table', null, Option::VALUE_REQUIRED, '待 inspect 的数据表', '')
            ->addOption('target', null, Option::VALUE_REQUIRED, 'application、console 或 both', 'both')
            ->addOption('connection', null, Option::VALUE_REQUIRED, '配置连接名', 'mysql')
            ->addOption('definition-output', null, Option::VALUE_REQUIRED, '可选 Definition JSON 输出路径', '');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $root = app()->getRootPath();
            $connection = (string) $input->getOption('connection');
            $table = (string) $input->getOption('table');
            $inspection = (new DevCrudService($root, (array) config('crud.connections', [])))->infer($connection, $table);
            $definition = (new PluginCrudDefinitionFactory($root))->fromInspection(
                (string) $input->getArgument('plugin'),
                (string) $input->getArgument('entity'),
                $table,
                (string) $input->getOption('target'),
                $inspection,
                $connection
            );
            $result = ['definition' => $definition->toArray(), 'next' => '运行 plugin:crud-preview 并通过 --token-output 保存确认 token'];
            $definitionOutput = trim((string) $input->getOption('definition-output'));
            if ($definitionOutput !== '') {
                $this->writeDefinition($root, $definitionOutput, $definition->toArray());
                $result['definitionFile'] = $definitionOutput;
            }
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }

    private function writeDefinition(string $root, string $path, array $definition): void
    {
        $absolute = \app\common\crud\PathGuard::resolve($root, $path, '项目目录');
        if (file_exists($absolute) || is_link($absolute)) {
            throw new \RuntimeException('Definition 输出文件已存在');
        }
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0755, true) && !is_dir(dirname($absolute))) {
            throw new \RuntimeException('无法创建 Definition 输出目录');
        }
        if (file_put_contents($absolute, json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('无法写入 Definition 文件');
        }
    }
}
