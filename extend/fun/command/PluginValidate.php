<?php

declare(strict_types=1);

namespace fun\command;

use app\common\model\Plugin;
use fun\plugins\DependencyValidator;
use fun\plugins\Manifest;
use fun\plugins\PluginScaffolder;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use Throwable;

/** 校验插件 Manifest v2 与原生目录约定。 */
final class PluginValidate extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:validate')->setDescription('校验插件 Manifest v2 与目录约定')
            ->addArgument('name', Argument::REQUIRED, '插件名称');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $name = (string) $input->getArgument('name');
            PluginScaffolder::assertValidName($name);
            $manifest = Manifest::fromDirectory(root_path('plugins/' . $name));
            $records = [];
            foreach (Plugin::whereNull('deleted_at')->select() as $record) {
                $records[(string) $record->code] = [
                    'version' => (string) $record->version,
                    'lifecycle_state' => (string) $record->lifecycle_state,
                    'needs_reinstall' => (int) ($record->needs_reinstall ?? 0),
                ];
            }
            (new DependencyValidator((string) config('funadmin.version'), PHP_VERSION))->assertSatisfied($manifest, $records);
            $output->writeln(json_encode([
                'valid' => true,
                'code' => $manifest->code(),
                'version' => $manifest->version(),
                'message' => '插件校验通过',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
