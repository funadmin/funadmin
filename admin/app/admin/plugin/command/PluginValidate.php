<?php

declare(strict_types=1);

namespace app\admin\plugin\command;

use app\common\plugin\model\Plugin;
use app\common\plugin\sdk\DependencyValidator;
use app\common\plugin\sdk\Manifest;
use app\common\plugin\sdk\PluginScaffolder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/** 校验插件 Manifest v2 与原生目录约定。 */
#[AsCommand(name: 'plugin:validate')]
final class PluginValidate extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

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
            $manifest = Manifest::fromDirectory(($this->projectRoot ?? base_path()) . '/plugins/' . $name);
            $records = [];
            foreach (Plugin::whereNull('deleted_at')->get() as $record) {
                $records[(string) $record->code] = [
                    'version' => (string) $record->version,
                    'lifecycle_state' => (string) $record->lifecycle_state,
                ];
            }
            (new DependencyValidator((string) config('funadmin.version'), PHP_VERSION))->assertSatisfied($manifest, $records);
            $host = new \app\common\plugin\sdk\HostPackageRepository(($this->projectRoot ?? base_path()) . '/vendor/composer/installed.php');
            (new DependencyValidator((string) config('funadmin.version'), PHP_VERSION, $host))->assertComposerSatisfied($manifest);
            $others = [];
            foreach ($records as $code => $record) {
                if ($record['lifecycle_state'] === 'enabled' && $code !== $name) {
                    $others[$code] = ($this->projectRoot ?? base_path()) . '/plugins/' . $code;
                }
            }
            $conflicts = (new \app\common\plugin\sdk\ComposerConflictDetector($host))->detect($manifest->directory(), $others);
            $output->writeln(json_encode([
                'valid' => true,
                'code' => $manifest->code(),
                'version' => $manifest->version(),
                'message' => '插件校验通过',
                'warnings' => $conflicts,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
