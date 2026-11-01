<?php

declare(strict_types=1);

namespace app\common\service;

use PDO;
use RuntimeException;

/** 在显式维护窗口执行与普通 migration 隔离的破坏性 schema contract。 */
final class MaintenanceContractService
{
    private const SCOPE = 'maintenance-contract';

    public function __construct(
        private readonly PDO $connection,
        private readonly string $tablePrefix = 'fun_'
    ) {
    }

    public static function confirmationHash(string $database, string $sqlFile): string
    {
        $checksum = self::checksum($sqlFile);
        $version = pathinfo($sqlFile, PATHINFO_FILENAME);
        return hash('sha256', $database . '|' . $version . '|' . $checksum);
    }

    public static function assertSafety(
        string $database,
        bool $maintenanceMode,
        bool $backupConfirmed,
        string $confirmHash,
        string $sqlFile
    ): void {
        if ($database === '') {
            throw new RuntimeException('数据库名不能为空');
        }
        if (!$maintenanceMode) {
            throw new RuntimeException('必须先进入维护模式');
        }
        if (!$backupConfirmed) {
            throw new RuntimeException('必须显式确认已完成备份');
        }
        $expected = self::confirmationHash($database, $sqlFile);
        if (!hash_equals($expected, $confirmHash)) {
            throw new RuntimeException('确认 hash 无效；期望值：' . $expected);
        }
    }

    public function run(string $sqlFile, bool $maintenanceMode, bool $backupConfirmed, string $confirmHash): array
    {
        $database = (string) $this->connection->query('SELECT DATABASE()')->fetchColumn();
        self::assertSafety($database, $maintenanceMode, $backupConfirmed, $confirmHash, $sqlFile);
        $version = pathinfo($sqlFile, PATHINFO_FILENAME);
        $checksum = self::checksum($sqlFile);
        $repository = $this->identifier($this->tablePrefix . 'system_migration');
        $this->assertRepository($repository);

        $record = $this->connection->prepare("SELECT `checksum` FROM {$repository} WHERE `scope`=? AND `version`=?");
        $record->execute([self::SCOPE, $version]);
        $appliedChecksum = $record->fetchColumn();
        if ($appliedChecksum !== false) {
            if (!hash_equals((string) $appliedChecksum, $checksum)) {
                throw new RuntimeException('已执行的 maintenance contract 内容发生变化');
            }
            return ['version' => $version, 'checksum' => $checksum, 'executed' => false, 'droppedColumns' => []];
        }

        $operations = $this->operations($sqlFile);
        $this->assertLaravelPairs($operations);
        $dropped = [];
        foreach ($operations as [$table, $legacy, $modern]) {
            if (!$this->columnExists($table, $legacy)) {
                continue;
            }
            $this->connection->exec(
                'ALTER TABLE ' . $this->identifier($table) . ' DROP COLUMN ' . $this->identifier($legacy)
            );
            $dropped[] = $table . '.' . $legacy;
        }

        $insert = $this->connection->prepare(
            "INSERT INTO {$repository} (`scope`,`version`,`checksum`,`executed_at`) VALUES (?,?,?,?)"
        );
        $insert->execute([self::SCOPE, $version, $checksum, time()]);
        return ['version' => $version, 'checksum' => $checksum, 'executed' => true, 'droppedColumns' => $dropped];
    }

    private static function checksum(string $sqlFile): string
    {
        if (!is_file($sqlFile)) {
            throw new RuntimeException('Maintenance contract 不存在：' . $sqlFile);
        }
        $checksum = hash_file('sha256', $sqlFile);
        if ($checksum === false) {
            throw new RuntimeException('无法计算 maintenance contract checksum');
        }
        return $checksum;
    }

    private function operations(string $sqlFile): array
    {
        $sql = (string) file_get_contents($sqlFile);
        $sql = str_replace('fun_', $this->tablePrefix, $sql);
        preg_match_all(
            "/CONTRACT table=([a-z0-9_]+) legacy=(create_time|update_time|delete_time|sort) modern=(created_at|updated_at|deleted_at|sort_order)/",
            $sql,
            $matches,
            PREG_SET_ORDER
        );
        if (!$matches) {
            throw new RuntimeException('Maintenance contract 没有受控列操作');
        }
        return array_map(static fn (array $match): array => [$match[1], $match[2], $match[3]], $matches);
    }

    private function assertLaravelPairs(array $operations): void
    {
        foreach ($operations as [$table, $legacy, $modern]) {
            if ($this->columnExists($table, $legacy) && !$this->columnExists($table, $modern)) {
                throw new RuntimeException("{$table}.{$legacy} 缺少 Laravel 对应列 {$modern}");
            }
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?'
        );
        $statement->execute([$table, $column]);
        return $statement->fetchColumn() !== false;
    }

    private function assertRepository(string $repository): void
    {
        $statement = $this->connection->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'
        );
        $statement->execute([$this->tablePrefix . 'system_migration']);
        if ($statement->fetchColumn() === false) {
            throw new RuntimeException('核心 migration repository 不存在');
        }
    }

    private function identifier(string $value): string
    {
        if (preg_match('/^[a-zA-Z0-9_]+$/', $value) !== 1) {
            throw new RuntimeException('数据库标识符不安全');
        }
        return '`' . $value . '`';
    }
}
