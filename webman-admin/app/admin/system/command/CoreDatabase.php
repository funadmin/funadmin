<?php

declare(strict_types=1);

namespace app\admin\system\command;

use app\common\service\database\CoreAdoptionBackup;
use app\common\service\database\CoreAdoptionPlan;
use app\common\service\database\CoreAdoptionService;
use app\common\service\database\CoreDatabaseAudit;
use app\common\service\database\CoreSeedPlan;
use app\common\service\database\MigrationService;
use app\common\service\install\InstallSupport;
use RuntimeException;
use support\Db;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface as Input;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface as Output;
use Throwable;

/** 根 SQL 核心迁移的只读预检、私有备份及显式确认接管入口。 */
#[AsCommand(name: 'database:core')]
final class CoreDatabase extends Command
{
    public function __construct(private readonly ?\Illuminate\Database\Connection $connection = null)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('database:core')->setDescription('核心数据库预检、备份与保留数据接管')
            ->addArgument('action', InputArgument::REQUIRED, 'preflight / backup / apply')
            ->addOption('reference', null, InputOption::VALUE_REQUIRED, '私有规范回放报告')
            ->addOption('plan', null, InputOption::VALUE_REQUIRED, '私有接管计划')
            ->addOption('receipt', null, InputOption::VALUE_REQUIRED, '私有备份凭证')
            ->addOption('confirm', null, InputOption::VALUE_REQUIRED, '预检确认摘要')
            ->addOption('maintenance', null, InputOption::VALUE_NONE, '确认服务已进入维护窗口，停止业务写入');
    }

    protected function execute(Input $input, Output $output): int
    {
        try {
            $action = (string) $input->getArgument('action');
            if (!in_array($action, ['preflight', 'backup', 'apply'], true)) throw new RuntimeException('未知核心数据库操作');
            $reference = $this->readPrivate((string) $input->getOption('reference'));
            $plan = $action === 'preflight' ? null : $this->readPrivate((string) $input->getOption('plan'));
            if ($this->connection === null) InstallSupport::activateInstalledConfiguration();
            $connection = $this->connection ?? Db::connection();
            if ($connection->getDriverName() !== 'mysql') throw new RuntimeException('核心 SQL 入口只支持 MySQL');
            $connection->statement("SET time_zone = '+00:00'");
            if ($action === 'preflight') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
                $connection->statement('SET TRANSACTION READ ONLY');
                $connection->beginTransaction();
                try {
                    $audit = (new CoreDatabaseAudit(MigrationService::coreDirectory()))->inspect($connection);
                    $rows = CoreSeedPlan::readSnapshot($connection, $audit['schema']);
                    $plan = CoreAdoptionPlan::build($reference, $audit, $rows);
                } finally { $connection->rollBack(); }
                $result = ['database' => $plan['database'], 'prefix' => $plan['prefix'], 'mode' => $plan['mode'], 'digest' => $plan['digest'], 'impact' => $plan['impact'], 'blocker_count' => count($plan['blockers']), 'plan' => $this->savePrivate('core-plan', $plan), 'apply_allowed' => false];
            } else {
                $backup = new CoreAdoptionBackup($this->privateDirectory());
                $confirmation = (string) $input->getOption('confirm');
                if ($action === 'backup') {
                    $receipt = $backup->capture($connection, $reference, $plan, $confirmation);
                    $result = ['database' => $plan['database'], 'digest' => $plan['digest'], 'receipt' => $this->savePrivate('core-receipt', $receipt), 'backup_checksum' => $receipt['checksum'], 'apply_allowed' => false];
                } else {
                    $receipt = $this->readPrivate((string) $input->getOption('receipt'));
                    $result = (new CoreAdoptionService($backup))->apply($connection, $reference, $plan, $confirmation, $receipt, (bool) $input->getOption('maintenance'));
                }
            }
            $output->writeln(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 0;
        } catch (Throwable $error) {
            $output->writeln(json_encode(['error' => $error->getMessage(), 'applied' => false], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return 1;
        }
    }

    private function privateDirectory(): string
    {
        $path = base_path('runtime/private');
        if (is_link($path) || !is_dir($path) || realpath($path) === false) throw new RuntimeException('核心报告必须位于私有 runtime 目录');
        return (string) realpath($path);
    }

    private function readPrivate(string $path): array
    {
        $directory = $this->privateDirectory();
        if ($path === '' || is_link($path) || !is_file($path) || realpath(dirname($path)) !== $directory || (fileperms($path) & 0077) !== 0) throw new RuntimeException('核心报告必须为访问受限的私有普通文件');
        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function savePrivate(string $kind, array $document): string
    {
        $path = $this->privateDirectory() . '/' . $kind . '-' . bin2hex(random_bytes(16)) . '.json';
        $mask = umask(0077);
        try { $handle = fopen($path, 'x'); } finally { umask($mask); }
        if ($handle === false) throw new RuntimeException('无法创建私有核心报告');
        try {
            $json = json_encode($document, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (!chmod($path, 0600) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('私有核心报告写入失败');
        } finally { fclose($handle); }
        return $path;
    }
}
