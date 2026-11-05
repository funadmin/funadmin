<?php

declare(strict_types=1);

namespace Tests\Console;

use app\common\service\database\CoreAdoptionPlan;
use app\common\service\database\CoreSchemaPlan;
use PHPUnit\Framework\TestCase;

final class CoreAdoptionPlanTest extends TestCase
{
    private function reports(): array
    {
        $reference = ['audit_version' => 4, 'database' => 'reference', 'prefix' => 'fun_', 'source_checksums' => ['001_baseline.sql' => str_repeat('a', 64)], 'schema' => [], 'seed_rows' => []];
        $target = ['audit_version' => 4, 'database' => 'target', 'prefix' => 'custom_', 'source_checksums' => $reference['source_checksums'], 'schema' => [], 'server_identity' => ['uuid' => 'fixture-instance'], 'protected_row_hashes' => [], 'migration_history' => []];
        return [$reference, $target, []];
    }

    public function test_adoption_binds_all_evidence_and_is_not_a_fake_migration_history(): void
    {
        self::assertTrue(class_exists(CoreAdoptionPlan::class), '缺少独立存量接管预检');
        [$reference, $target, $seeds] = $this->reports();
        $plan = CoreAdoptionPlan::build($reference, $target, $seeds);
        self::assertSame('adopt', $plan['mode']);
        self::assertSame('target', $plan['database']);
        self::assertSame('custom_', $plan['prefix']);
        self::assertSame('fixture-instance', $plan['server_identity']['uuid']);
        self::assertSame($reference['source_checksums'], $plan['adopted_sources']);
        self::assertSame([], $plan['executed_versions'], '接管不能把基线登记成实际执行');
        self::assertFalse($plan['apply_allowed']);
        self::assertSame(CoreSchemaPlan::fingerprint($plan), $plan['digest']);
        self::assertContains('verified_backup', $plan['required_gates']);
        self::assertContains('exclusive_database_lock', $plan['required_gates']);
    }

    public function test_confirmation_backup_source_target_and_seed_drift_are_rejected(): void
    {
        [$reference, $target, $seeds] = $this->reports();
        $plan = CoreAdoptionPlan::build($reference, $target, $seeds);
        CoreAdoptionPlan::assertCurrent($plan, $reference, $target, $seeds, $plan['digest']);
        foreach (['confirmation', 'database', 'server', 'seed', 'tamper'] as $case) {
            $actual = $target; $rows = $seeds; $candidate = $plan; $confirmation = $plan['digest'];
            if ($case === 'confirmation') $confirmation = str_repeat('0', 64);
            if ($case === 'database') $actual['database'] = 'other';
            if ($case === 'server') $actual['server_identity']['uuid'] = 'other';
            if ($case === 'seed') $rows['dict_type'] = [['id' => 1, 'code' => 'custom']];
            if ($case === 'tamper') $candidate['executed_versions'] = ['001_baseline'];
            try {
                CoreAdoptionPlan::assertCurrent($candidate, $reference, $actual, $rows, $confirmation);
                self::fail('接管证据异常未拒绝：' . $case);
            } catch (\RuntimeException $error) { self::assertStringContainsString('漂移', $error->getMessage()); }
        }
    }

    public function test_private_backup_is_exclusive_checksummed_and_bound_to_plan_and_target(): void
    {
        $root = sys_get_temp_dir() . '/core-backup-' . bin2hex(random_bytes(8));
        mkdir($root, 0700);
        $backup = new \app\common\service\database\CoreAdoptionBackup($root);
        $evidence = ['plan_digest' => str_repeat('a', 64), 'database' => 'fixture', 'prefix' => 'fun_', 'server_identity' => ['uuid' => 'fixture-server'], 'target_digest' => str_repeat('b', 64), 'schema' => [], 'metadata' => ['admin_menu' => [['id' => 1, 'name' => '保留菜单']]]];
        try {
            $receipt = $backup->save($evidence);
            self::assertSame(0600, fileperms($receipt['path']) & 0777);
            self::assertSame($evidence, $backup->verify($receipt, $evidence['plan_digest'], $evidence['target_digest']));
            $changed = $receipt;
            $changed['checksum'] = str_repeat('0', 64);
            foreach ([$changed, array_replace($receipt, ['path' => $root . '/../outside.json'])] as $invalid) {
                try { $backup->verify($invalid, $evidence['plan_digest'], $evidence['target_digest']); self::fail('非法备份必须拒绝'); }
                catch (\RuntimeException $error) { self::assertStringContainsString('备份', $error->getMessage()); }
            }
            file_put_contents($receipt['path'], '{}');
            $this->expectException(\RuntimeException::class);
            $backup->verify($receipt, $evidence['plan_digest'], $evidence['target_digest']);
        } finally {
            foreach (glob($root . '/*') as $file) unlink($file);
            rmdir($root);
        }
    }

    public function test_completed_adoption_is_incremental_and_only_new_core_sources_are_pending(): void
    {
        [$reference, $target, $seeds] = $this->reports();
        $reference['source_checksums']['002_runtime.sql'] = str_repeat('b', 64);
        $target['source_checksums'] = $reference['source_checksums'];
        $target['core_database_adoption'] = [[
            'stage' => 'complete',
            'database_name' => 'target',
            'table_prefix' => 'custom_',
            'server_uuid' => 'fixture-instance',
            'source_checksums' => ['001_baseline.sql' => str_repeat('a', 64)],
        ]];

        $plan = CoreAdoptionPlan::build($reference, $target, $seeds);

        self::assertSame('incremental', $plan['mode']);
        self::assertSame(['001_baseline.sql' => str_repeat('a', 64)], $plan['adopted_sources']);
        self::assertSame(['002_runtime'], $plan['pending_versions']);
    }

    public function test_incomplete_adoption_blocks_new_plan_instead_of_restarting_from_baseline(): void
    {
        [$reference, $target, $seeds] = $this->reports();
        $target['core_database_adoption'] = [[
            'stage' => 'structure',
            'database_name' => 'target',
            'table_prefix' => 'custom_',
            'server_uuid' => 'fixture-instance',
            'source_checksums' => $reference['source_checksums'],
        ]];

        $plan = CoreAdoptionPlan::build($reference, $target, $seeds);

        self::assertSame('blocked', $plan['mode']);
        self::assertSame([], $plan['pending_versions']);
        self::assertSame('CORE_ADOPTION_INCOMPLETE', $plan['blockers'][0]['code']);
    }

    public function test_existing_core_history_uses_incremental_mode_and_missing_or_changed_versions_block(): void
    {
        [$reference, $target, $seeds] = $this->reports();
        $target['migration_history']['system_migration'] = [['scope' => 'core', 'version' => '001_baseline', 'checksum' => str_repeat('a', 64)]];
        $plan = CoreAdoptionPlan::build($reference, $target, $seeds);
        self::assertSame('incremental', $plan['mode']);
        self::assertSame([], $plan['pending_versions']);
        $target['migration_history']['system_migration'][] = ['scope' => 'core', 'version' => '999_missing', 'checksum' => str_repeat('b', 64)];
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('历史版本缺失');
        CoreAdoptionPlan::build($reference, $target, $seeds);
    }
}
