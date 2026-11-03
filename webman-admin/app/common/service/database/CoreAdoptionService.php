<?php

declare(strict_types=1);

namespace app\common\service\database;

use RuntimeException;

/** 接管写入口：确认、备份与同连接互斥必须全部成立，不伪造 SQL 执行历史。 */
final class CoreAdoptionService
{
    public function __construct(private readonly CoreAdoptionBackup $backup, private readonly ?\Closure $faultHook = null)
    {
    }

    public function apply(object $connection, array $reference, array $plan, string $confirmation, array $receipt, bool $maintenance): array
    {
        if (!$maintenance) throw new RuntimeException('接管应用必须显式确认维护窗口并暂停业务写入');
        if ($connection->getDriverName() !== 'mysql' || $connection->transactionLevel() !== 0) throw new RuntimeException('接管需要独立 MySQL 连接，不允许外层事务');
        if (!hash_equals((string) ($plan['digest'] ?? ''), $confirmation)
            || !hash_equals(CoreSchemaPlan::fingerprint($plan), $confirmation)) throw new RuntimeException('接管确认摘要或计划漂移');
        if (!hash_equals((string) ($plan['reference_digest'] ?? ''), CoreSchemaPlan::fingerprint($reference))) throw new RuntimeException('规范回放报告漂移');
        if ($connection->getDatabaseName() !== $plan['database'] || $connection->getTablePrefix() !== $plan['prefix']) throw new RuntimeException('接管目标连接漂移');
        if ($plan['mode'] !== 'adopt') throw new RuntimeException('已有核心历史必须使用真实增量入口');
        if ($plan['blockers'] !== []) throw new RuntimeException('接管仍有结构或种子冲突');
        $evidence = $this->backup->verify($receipt, $plan['digest'], $plan['target_digest']);
        if (($evidence['database'] ?? '') !== $plan['database'] || ($evidence['prefix'] ?? '') !== $plan['prefix']
            || ($evidence['server_identity'] ?? []) !== $plan['server_identity']) throw new RuntimeException('备份目标实例漂移');
        $pdo = $connection->getPdo();
        $lock = 'migration:' . substr(hash('sha256', $connection->getDatabaseName()), 0, 40);
        $statement = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $statement->execute([$lock]);
        if ((int) $statement->fetchColumn() !== 1) throw new RuntimeException('数据库迁移正在执行');
        try {
            $connection->statement("SET time_zone = '+00:00'");
            $audit = new CoreDatabaseAudit(MigrationService::coreDirectory());
            $actual = $audit->inspect($connection);
            if ($actual['server_identity'] !== $plan['server_identity'] || $actual['source_checksums'] !== $plan['source_checksums']) throw new RuntimeException('接管源或实例漂移');
            $table = $connection->getTablePrefix() . 'core_database_adoption';
            $existing = isset($actual['schema'][$table]) ? $connection->table('core_database_adoption')->where('plan_digest', $plan['digest'])->first() : null;
            if ($existing !== null && $existing->stage === 'complete') {
                $completedEvidence = json_decode($existing->evidence, true, 512, JSON_THROW_ON_ERROR);
                if (!hash_equals((string) $existing->after_digest, $actual['digest'])
                    || !hash_equals((string) ($completedEvidence['seed_digest'] ?? ''), CoreSchemaPlan::fingerprint(CoreSeedPlan::readSnapshot($connection, $actual['schema'])))) throw new RuntimeException('接管完成后目标数据漂移，禁止重复覆盖');
                return ['stage' => 'complete', 'plan_digest' => $plan['digest'], 'after_digest' => $existing->after_digest];
            }
            if ($existing === null) {
                $rows = CoreSeedPlan::readSnapshot($connection, $actual['schema']);
                CoreAdoptionPlan::assertCurrent($plan, $reference, $actual, $rows, $confirmation);
                if (!isset($actual['schema'][$table])) {
                    $create = array_values(array_filter($plan['schema_plan']['operations'], static fn (array $op): bool => $op['kind'] === 'create_table' && $op['table'] === 'core_database_adoption'));
                    if (count($create) !== 1) throw new RuntimeException('接管登记表缺失且无已审核建表操作');
                    $connection->statement($create[0]['sql']);
                    $actual = $audit->inspect($connection);
                }
                $now = date('Y-m-d H:i:s');
                $recordId = $connection->table('core_database_adoption')->insertGetId([
                    'plan_digest' => $plan['digest'], 'database_name' => $plan['database'], 'table_prefix' => $plan['prefix'],
                    'server_uuid' => $plan['server_identity']['uuid'], 'source_checksums' => json_encode($plan['source_checksums'], JSON_THROW_ON_ERROR),
                    'reference_digest' => $plan['reference_digest'], 'before_digest' => $plan['target_digest'],
                    'backup_path' => $receipt['path'], 'backup_checksum' => $receipt['checksum'], 'stage' => 'structure',
                    'completed_operations' => '[]', 'evidence' => json_encode(['checkpoint' => $actual['digest'], 'seed_digest' => CoreSchemaPlan::fingerprint(CoreSeedPlan::readSnapshot($connection, $actual['schema'])), 'pending' => null], JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $completed = [];
            } else {
                if ($existing->stage !== 'structure') throw new RuntimeException('未知接管阶段，禁止自动恢复');
                $checkpoint = json_decode($existing->evidence, true, 512, JSON_THROW_ON_ERROR);
                if (($checkpoint['pending'] ?? null) !== null) throw new RuntimeException('DDL 执行与登记之间中断，必须先人工核验 pending 阶段，禁止盲目重放');
                if (!hash_equals((string) ($checkpoint['checkpoint'] ?? ''), $actual['digest'])
                    || !hash_equals((string) ($checkpoint['seed_digest'] ?? ''), CoreSchemaPlan::fingerprint(CoreSeedPlan::readSnapshot($connection, $actual['schema'])))) throw new RuntimeException('接管结构阶段后目标漂移');
                $recordId = (int) $existing->id;
                $completed = json_decode($existing->completed_operations, true, 512, JSON_THROW_ON_ERROR);
            }
            foreach ($plan['schema_plan']['operations'] as $operation) {
                $hash = CoreSchemaPlan::fingerprint($operation);
                if (in_array($hash, $completed, true)) continue;
                $currentSchema = CoreSchemaPlan::build($reference, $actual);
                if ($currentSchema['blockers'] !== []) throw new RuntimeException('结构阶段出现未审核冲突');
                $remaining = array_column(array_map(static fn (array $op): array => ['hash' => CoreSchemaPlan::fingerprint($op), 'operation' => $op], $currentSchema['operations']), 'operation', 'hash');
                if (!isset($remaining[$hash])) {
                    if ($operation['kind'] === 'create_table' && $operation['table'] === 'core_database_adoption') continue;
                    throw new RuntimeException('结构阶段操作漂移，拒绝执行');
                }
                $checks = array_values(array_filter($currentSchema['preflight'], static fn (array $check): bool => $check['table'] === $operation['table'] && str_contains($operation['sql'], '`' . $check['name'] . '`')));
                if (CoreSchemaPlan::checkPreflight($connection, array_replace($currentSchema, ['preflight' => $checks])) !== []) throw new RuntimeException('接管数据预检未通过');
                $connection->table('core_database_adoption')->where('id', $recordId)->update(['evidence' => json_encode(['checkpoint' => $actual['digest'], 'pending' => $hash], JSON_THROW_ON_ERROR)]);
                $connection->statement($operation['sql']);
                if ($this->faultHook !== null) ($this->faultHook)('ddl_applied');
                $actual = $audit->inspect($connection);
                $completed[] = $hash;
                $connection->table('core_database_adoption')->where('id', $recordId)->update(['completed_operations' => json_encode($completed, JSON_THROW_ON_ERROR), 'evidence' => json_encode(['checkpoint' => $actual['digest'], 'seed_digest' => CoreSchemaPlan::fingerprint(CoreSeedPlan::readSnapshot($connection, $actual['schema'])), 'pending' => null], JSON_THROW_ON_ERROR), 'updated_at' => date('Y-m-d H:i:s')]);
                if ($this->faultHook !== null) ($this->faultHook)('ddl_checkpoint');
            }
            $currentSchema = CoreSchemaPlan::build($reference, $actual);
            if ($currentSchema['blockers'] !== [] || $currentSchema['operations'] !== []) throw new RuntimeException('结构阶段尚未收敛');
            return $connection->transaction(function () use ($connection, $reference, $plan, $actual, $audit, $recordId): array {
                $connection->table('core_database_adoption')->where('id', $recordId)->update(['stage' => 'metadata']);
                $rows = CoreSeedPlan::readSnapshot($connection, $actual['schema']);
                $seeds = CoreSeedPlan::build($reference['seed_rows'], $rows);
                CoreSeedPlan::apply($connection, $reference['seed_rows'], $seeds);
                $afterSeeds = $audit->inspect($connection);
                $rows = CoreSeedPlan::readSnapshot($connection, $afterSeeds['schema']);
                $menus = CoreMenuPlan::build($reference['seed_rows'], $rows);
                CoreMenuPlan::apply($connection, $reference['seed_rows'], $menus);
                if ($this->faultHook !== null) ($this->faultHook)('metadata_applied');
                $after = $audit->inspect($connection);
                $schema = CoreSchemaPlan::build($reference, $after);
                if ($schema['blockers'] !== [] || $schema['operations'] !== []) throw new RuntimeException('接管后结构仍未收敛');
                $rows = CoreSeedPlan::readSnapshot($connection, $after['schema']);
                if (CoreMenuPlan::diagnose($rows) !== []) throw new RuntimeException('接管后菜单契约未通过');
                foreach (['admin', 'auth_group', 'casbin_rule', 'admin_department', 'auth_group_department', 'auth_group_inherit', 'auth_group_field_permission'] as $protected) {
                    if (($actual['protected_row_hashes'][$protected] ?? null) !== ($after['protected_row_hashes'][$protected] ?? null)) throw new RuntimeException('接管改变受保护账号或授权：' . $protected);
                }
                $connection->table('core_database_adoption')->where('id', $recordId)->update(['stage' => 'complete', 'after_digest' => $after['digest'], 'evidence' => json_encode(['checkpoint' => $after['digest'], 'seed_digest' => CoreSchemaPlan::fingerprint($rows), 'pending' => null], JSON_THROW_ON_ERROR), 'updated_at' => date('Y-m-d H:i:s')]);
                return ['stage' => 'complete', 'plan_digest' => $plan['digest'], 'after_digest' => $after['digest']];
            });
        } finally {
            $statement = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$lock]);
        }
    }
}
