<?php

declare(strict_types=1);

namespace app\common\service\database;

use RuntimeException;

/** 只保存结构与受影响元数据；不复制业务表，不提供自动整库恢复。 */
final class CoreAdoptionBackup
{
    public function __construct(private readonly string $directory)
    {
        if (is_link($directory) || !is_dir($directory) || !is_writable($directory)) throw new RuntimeException('接管备份目录必须为可写私有普通目录');
        if (!chmod($directory, 0700)) throw new RuntimeException('无法限制备份目录访问权限');
    }

    public function capture(object $connection, array $reference, array $plan, string $confirmation): array
    {
        if ($connection->transactionLevel() !== 0) throw new RuntimeException('备份需要独立一致性只读事务');
        $connection->statement("SET time_zone = '+00:00'");
        $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $connection->statement('SET TRANSACTION READ ONLY');
        $connection->beginTransaction();
        try {
            $audit = (new CoreDatabaseAudit(MigrationService::coreDirectory()))->inspect($connection);
            $seeds = CoreSeedPlan::readSnapshot($connection, $audit['schema']);
            CoreAdoptionPlan::assertCurrent($plan, $reference, $audit, $seeds, $confirmation);
            if ($plan['blockers'] !== []) throw new RuntimeException('接管仍有阻断项，禁止生成可应用备份凭证');
            $metadata = [];
            foreach (['admin_menu', 'permission', 'permission_field', 'casbin_rule', 'auth_group', 'admin_department', 'auth_group_department', 'auth_group_inherit', 'auth_group_field_permission', 'migrations', 'system_migration'] as $table) {
                if (!isset($audit['schema'][$connection->getTablePrefix() . $table])) continue;
                $metadata[$table] = $connection->table($table)->get()->map(static fn (object $row): array => (array) $row)->all();
            }
            return $this->save([
                'plan_digest' => $plan['digest'], 'target_digest' => $plan['target_digest'],
                'database' => $audit['database'], 'prefix' => $audit['prefix'], 'server_identity' => $audit['server_identity'],
                'source_checksums' => $audit['source_checksums'], 'schema' => $audit['schema'],
                'protected_row_hashes' => $audit['protected_row_hashes'], 'table_rows' => $audit['table_rows'], 'metadata' => $metadata,
            ]);
        } finally { $connection->rollBack(); }
    }

    public function save(array $evidence): array
    {
        foreach (['plan_digest', 'target_digest'] as $key) {
            if (preg_match('/^[a-f0-9]{64}$/D', (string) ($evidence[$key] ?? '')) !== 1) throw new RuntimeException('备份缺少绑定摘要');
        }
        foreach (['database', 'prefix', 'server_identity', 'schema', 'metadata'] as $key) {
            if (!array_key_exists($key, $evidence)) throw new RuntimeException('备份证据不完整');
        }
        $path = realpath($this->directory) . '/core-adoption-' . bin2hex(random_bytes(16)) . '.json';
        $mask = umask(0077);
        try { $handle = fopen($path, 'x'); } finally { umask($mask); }
        if ($handle === false) throw new RuntimeException('无法独占创建接管备份');
        try {
            if (!chmod($path, 0600)) throw new RuntimeException('无法限制备份访问权限');
            $json = json_encode(['format' => 'core-adoption-backup-v1', 'evidence' => $evidence], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $json) !== strlen($json) || !fflush($handle)) throw new RuntimeException('备份写入不完整');
            if (function_exists('fsync') && !fsync($handle)) throw new RuntimeException('备份持久化失败');
        } finally { fclose($handle); }
        $receipt = ['path' => $path, 'checksum' => hash_file('sha256', $path)];
        $this->verify($receipt, $evidence['plan_digest'], $evidence['target_digest']);
        return $receipt;
    }

    public function verify(array $receipt, string $planDigest, string $targetDigest): array
    {
        $path = (string) ($receipt['path'] ?? '');
        if (is_link($path) || !is_file($path) || realpath(dirname($path)) !== realpath($this->directory)
            || (fileperms($path) & 0777) !== 0600) throw new RuntimeException('备份路径或访问权限不安全');
        $checksum = hash_file('sha256', $path);
        if (!is_string($checksum) || !hash_equals((string) ($receipt['checksum'] ?? ''), $checksum)) throw new RuntimeException('备份 checksum 不匹配');
        $document = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $evidence = $document['evidence'] ?? [];
        if (($document['format'] ?? '') !== 'core-adoption-backup-v1' || ($evidence['plan_digest'] ?? '') !== $planDigest
            || ($evidence['target_digest'] ?? '') !== $targetDigest) throw new RuntimeException('备份目标或确认摘要漂移');
        return $evidence;
    }
}
