<?php

declare(strict_types=1);

namespace app\admin\plugin\command;

use app\admin\plugin\service\PluginPackageService;
use app\common\plugin\sdk\PluginArchiveService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputArgument as Argument;
use Symfony\Component\Console\Input\InputOption as Option;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Symfony\Component\Console\Style\SymfonyStyle;
use support\Container;
use Throwable;

/** 创建确定性插件包并通过安装暂存链重验。 */
#[AsCommand(name: 'plugin:package')]
final class PluginPackage extends Command
{
    public function __construct(private readonly ?string $projectRoot = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('plugin:package')->setDescription('创建确定性 Manifest v2 插件包')
            ->addArgument('name', Argument::REQUIRED, '插件名称')
            ->addOption('output', null, Option::VALUE_REQUIRED, 'ZIP 输出文件或目录', '')
            ->addOption('sign-key-file', null, Option::VALUE_REQUIRED, '包外 Base64 Ed25519 私钥文件', '');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $pluginsDirectory = ($this->projectRoot ?? base_path()) . '/plugins';
            $packageService = $this->projectRoot === null
                ? Container::get(PluginPackageService::class)
                : new PluginPackageService($this->projectRoot);
            $keyFile = (string) $input->getOption('sign-key-file');
            $secretKey = '';
            if ($keyFile !== '') {
                $realKey = realpath($keyFile);
                $pluginsRoot = realpath($pluginsDirectory);
                if ($realKey === false || !is_file($realKey) || !is_readable($realKey)
                    || ($pluginsRoot !== false && str_starts_with($realKey, $pluginsRoot . DIRECTORY_SEPARATOR))) {
                    throw new \RuntimeException('签名私钥必须是插件目录外的可读文件');
                }
                $secretKey = (string) file_get_contents($realKey);
            }
            $archive = new PluginArchiveService($pluginsDirectory, static function (string $file) use ($packageService, $secretKey): void {
                if ($secretKey !== '') $packageService->signLocalArchive($file, $secretKey);
                // 无密钥时只生成开发包，不能作为可信本地包安装。
                $staged = $packageService->stage($file, '', '', $secretKey !== '');
                $packageService->discard($staged);
            });
            $result = $archive->package(
                (string) $input->getArgument('name'),
                (string) $input->getOption('output')
            );
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            (new SymfonyStyle($input, $output))->error($exception->getMessage());
            return 1;
        }
    }
}
