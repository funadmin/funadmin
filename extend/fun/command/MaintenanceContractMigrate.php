<?php

declare(strict_types=1);

namespace fun\command;

use app\common\service\MaintenanceContractService;
use PDO;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use Throwable;

/** 在显式维护窗口执行 Laravel 字段 contract migration。 */
final class MaintenanceContractMigrate extends Command
{
    protected function configure(): void
    {
        $this->setName('maintenance:contract-migrate')
            ->setDescription('安全删除已完成切换的 legacy 公共字段')
            ->addOption('backup-confirm', null, Option::VALUE_NONE, '确认已完成并验证数据库备份')
            ->addOption('confirm-hash', null, Option::VALUE_REQUIRED, '绑定数据库、版本和 checksum 的确认 hash', '')
            ->addOption('maintenance-mode', null, Option::VALUE_NONE, '确认应用已进入维护模式')
            ->addOption('show-confirm-hash', null, Option::VALUE_NONE, '仅输出当前数据库所需确认 hash，不执行');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $config = (array) config('database.connections.mysql');
            $connection = $this->connection($config);
            $sqlFile = app()->getRootPath() . 'database/maintenance/001_drop_legacy_time_columns.sql';
            $database = (string) $connection->query('SELECT DATABASE()')->fetchColumn();
            $expectedHash = MaintenanceContractService::confirmationHash($database, $sqlFile);
            if ((bool) $input->getOption('show-confirm-hash')) {
                $output->writeln($expectedHash);
                return 0;
            }
            $result = (new MaintenanceContractService($connection, (string) ($config['prefix'] ?? 'fun_')))->run(
                $sqlFile,
                (bool) $input->getOption('maintenance-mode'),
                (bool) $input->getOption('backup-confirm'),
                (string) $input->getOption('confirm-hash')
            );
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }

    private function connection(array $config): PDO
    {
        $host = (string) ($config['hostname'] ?? '127.0.0.1');
        $port = (string) ($config['hostport'] ?? '3306');
        $database = (string) ($config['database'] ?? '');
        $charset = (string) ($config['charset'] ?? 'utf8mb4');
        if ($database === '') {
            throw new \RuntimeException('数据库名不能为空');
        }
        return new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset={$charset}",
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
}
