<?php

declare(strict_types=1);

namespace app\common\service\database;

use RuntimeException;

/** 汇总只读接管证据；接管快照与真实 migration 执行历史严格分开。 */
final class CoreAdoptionPlan
{
    public static function build(array $reference, array $actual, array $seedRows): array
    {
        if (!isset($reference['seed_rows'])) throw new RuntimeException('规范报告缺少回放种子');
        $schema = CoreSchemaPlan::build($reference, $actual);
        $history = [];
        foreach ($actual['migration_history']['system_migration'] ?? [] as $record) {
            if (($record['scope'] ?? '') !== 'core') continue;
            $file = $record['version'] . '.sql';
            if (!isset($reference['source_checksums'][$file])) throw new RuntimeException('核心历史版本缺失：' . $record['version']);
            if (!hash_equals($reference['source_checksums'][$file], (string) $record['checksum'])) throw new RuntimeException('核心历史 checksum 漂移');
            $history[$file] = $record['checksum'];
        }
        $adopted = [];
        $adoptionBlockers = [];
        foreach ($actual['core_database_adoption'] ?? [] as $record) {
            if (($record['database_name'] ?? '') !== $actual['database'] || ($record['table_prefix'] ?? '') !== $actual['prefix']
                || ($record['server_uuid'] ?? '') !== ($actual['server_identity']['uuid'] ?? '')) throw new RuntimeException('核心接管登记目标实例漂移');
            if (($record['stage'] ?? '') !== 'complete') {
                $adoptionBlockers[] = ['code' => 'CORE_ADOPTION_INCOMPLETE'];
                continue;
            }
            $sources = $record['source_checksums'] ?? [];
            if (is_string($sources)) $sources = json_decode($sources, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($sources)) throw new RuntimeException('核心接管来源无效');
            foreach ($sources as $file => $checksum) {
                if (!isset($reference['source_checksums'][$file]) || !hash_equals($reference['source_checksums'][$file], (string) $checksum)) throw new RuntimeException('核心接管来源 checksum 漂移');
                $adopted[$file] = $checksum;
            }
        }
        $mode = $adoptionBlockers !== [] ? 'blocked' : (isset(($history + $adopted)['001_baseline.sql']) ? 'incremental' : 'adopt');
        if ($mode === 'adopt' && $history !== []) throw new RuntimeException('核心历史不完整，不能自动接管');
        $seeds = CoreSeedPlan::build($reference['seed_rows'], $seedRows);
        $menus = CoreMenuPlan::build($reference['seed_rows'], $seedRows);
        $pending = array_map(static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), array_keys(array_diff_key($reference['source_checksums'], $history + $adopted)));
        $plan = [
            'version' => 1, 'mode' => $mode, 'database' => $actual['database'], 'prefix' => $actual['prefix'],
            'server_identity' => $actual['server_identity'] ?? [],
            'reference_digest' => CoreSchemaPlan::fingerprint($reference), 'target_digest' => CoreSchemaPlan::fingerprint($actual),
            'target_seed_digest' => CoreSchemaPlan::fingerprint($seedRows),
            'source_checksums' => $reference['source_checksums'],
            'adopted_sources' => $mode === 'adopt' ? $reference['source_checksums'] : $adopted,
            'executed_versions' => [], 'pending_versions' => $mode === 'incremental' ? $pending : [],
            'schema_plan' => $schema, 'seed_plan' => $seeds, 'menu_plan' => $menus,
            'blockers' => array_merge($adoptionBlockers, $schema['blockers'], $seeds['blockers'], $menus['blockers']),
            'impact' => ['ddl' => count($schema['operations']), 'seed_inserts' => count($seeds['inserts']), 'menu_updates' => count($menus['updates'])],
            'required_gates' => ['verified_backup', 'confirmation_digest', 'exclusive_database_lock', 'maintenance_window', 'source_revalidation', 'post_apply_verification'],
            'apply_allowed' => false,
        ];
        $plan['digest'] = CoreSchemaPlan::fingerprint($plan);
        return $plan;
    }

    public static function assertCurrent(array $plan, array $reference, array $actual, array $seedRows, string $confirmation): void
    {
        $current = self::build($reference, $actual, $seedRows);
        if (!hash_equals($current['digest'], $confirmation) || !hash_equals($current['digest'], (string) ($plan['digest'] ?? ''))
            || !hash_equals($current['digest'], CoreSchemaPlan::fingerprint($plan))) throw new RuntimeException('接管确认摘要、计划或目标数据漂移');
    }
}
