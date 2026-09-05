<?php

declare(strict_types=1);

namespace fun\curd;

use app\common\service\AdminWebCrudGenerator;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/**
 * Vue 后台 CRUD 生成命令。
 */
class AdminWebCrud extends Command
{
    protected function configure()
    {
        $this->setName('curd')
            ->setDescription('根据 JSON 配置生成后台 API 与 Vue CRUD 页面')
            ->addArgument('config', Argument::REQUIRED, '项目目录内的版本化 CRUD Definition JSON')
            ->addOption('write', null, Option::VALUE_NONE, '从标准输入读取确认 token 并写入；默认仅 dry-run')
            ->addOption('confirm-token-file', null, Option::VALUE_REQUIRED, '可选的 0600 确认 token 文件', '')
            ->addOption('allow-overwrite', null, Option::VALUE_REQUIRED, '精确允许覆盖的项目相对路径，逗号分隔', '');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $allowOverwrite = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $input->getOption('allow-overwrite'))
            )));
            $write = (bool) $input->getOption('write');
            $confirmToken = $write ? $this->readConfirmToken((string) $input->getOption('confirm-token-file')) : '';
            $result = (new AdminWebCrudGenerator())->run(
                (string) $input->getArgument('config'),
                !$write,
                false,
                $confirmToken,
                $allowOverwrite,
                (string) (get_current_user() ?: 'cli')
            );
            if ($result['output'] !== '') {
                $output->writeln($result['output']);
            }
            $output->info((bool) $input->getOption('write') ? 'CRUD 写入完成' : 'CRUD dry-run 完成');
            return 0;
        } catch (Throwable $e) {
            $output->error($e->getMessage());
            return 1;
        }
    }

    private function readConfirmToken(string $tokenFile): string
    {
        $tokenFile = trim($tokenFile);
        if ($tokenFile === '') {
            $token = stream_get_contents(STDIN);
        } else {
            clearstatcache(true, $tokenFile);
            $stat = @lstat($tokenFile);
            if ($stat === false || (($stat['mode'] & 0170000) !== 0100000) || ($stat['mode'] & 0777) !== 0600) {
                throw new \RuntimeException('确认 token 文件必须是权限 0600 的普通文件');
            }
            $token = file_get_contents($tokenFile);
        }
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            throw new \RuntimeException('写入模式必须从 stdin 或 0600 token 文件读取确认 token');
        }
        return $token;
    }
}
