<?php

declare(strict_types=1);

namespace app\console\command;

use app\console\plugin\service\PluginPackageService;
use app\common\plugin\sdk\PluginArchiveService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/** 创建确定性插件包并通过安装暂存链重验。 */
final class PluginPackage extends Command
{
    protected function configure(): void
    {
        $this->setName('plugin:package')->setDescription('创建确定性 Manifest v2 插件包')
            ->addArgument('name', Argument::REQUIRED, '插件名称')
            ->addOption('output', null, Option::VALUE_REQUIRED, 'ZIP 输出文件或目录', '')
            ->addOption('sign-key-file', null, Option::VALUE_REQUIRED, '包外 Base64 Ed25519 私钥文件', '');
    }

    protected function execute(Input $input, Output $output): int
    {
        $packageService = app(PluginPackageService::class);
        try {
            $keyFile = (string) $input->getOption('sign-key-file');
            $secretKey = '';
            if ($keyFile !== '') {
                $realKey = realpath($keyFile);
                $pluginsRoot = realpath(root_path('plugins'));
                if ($realKey === false || !is_file($realKey) || !is_readable($realKey)
                    || ($pluginsRoot !== false && str_starts_with($realKey, $pluginsRoot . DIRECTORY_SEPARATOR))) {
                    throw new \RuntimeException('签名私钥必须是插件目录外的可读文件');
                }
                $secretKey = (string) file_get_contents($realKey);
            }
            $archive = new PluginArchiveService(root_path('plugins'), static function (string $file) use ($packageService, $secretKey): void {
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
            $output->error($exception->getMessage());
            return 1;
        }
    }
}
