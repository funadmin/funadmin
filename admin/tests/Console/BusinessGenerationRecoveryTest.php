<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\command\BusinessGenerationRecover;
use app\admin\development\exception\GenerationInterruptionException;
use app\admin\development\repository\DatabaseGenerationStateRepository;
use app\admin\development\repository\GeneratedFileBaselineRepository;
use app\admin\development\service\GenerationResourceTransaction;
use app\admin\development\service\GenerationTransactionService;
use app\common\crud\ConfirmationToken;
use app\common\crud\CrudDefinition;
use app\common\crud\ThreeWayMergePlanner;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class BusinessGenerationRecoveryTest extends TestCase
{
    private string $root;
    private array $ids;

    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        BusinessGenerationFixture::create();
        $this->ids = BusinessGenerationFixture::seed();
        $this->root = sys_get_temp_dir() . '/funadmin-business-recover-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0700, true);
        file_put_contents($this->root . '/example.php', "<?php // before\n");
    }

    protected function tearDown(): void
    {
        while (Db::connection()->transactionLevel() > 0) Db::connection()->rollBack();
        BusinessGenerationFixture::drop();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    private function bundle(): array
    {
        $remote = "<?php // after\n";
        $plan = (new ThreeWayMergePlanner($this->root))->plan([['path' => 'example.php', 'artifactType' => 'model', 'baseContent' => "<?php // before\n", 'remoteContent' => $remote]]);
        $plan['definitionHash'] = str_repeat('a', 64);
        return ['plan' => $plan, 'remoteContents' => ['example.php' => $remote], 'mergedTextContents' => ['example.php' => $remote],
            'definitionHash' => str_repeat('a', 64), 'schemaHash' => str_repeat('b', 64), 'registryHash' => str_repeat('c', 64),
            'templateVersion' => 'native', 'migrationHash' => str_repeat('d', 64), 'resourcesHash' => hash('sha256', CrudDefinition::canonicalJson([])), 'resources' => []];
    }

    private function interrupt(string $checkpoint): string
    {
        $state = new DatabaseGenerationStateRepository();
        $resources = new GenerationResourceTransaction();
        $bundle = $this->bundle();
        $tokens = new ConfirmationToken($this->root);
        $service = new GenerationTransactionService($this->root, $tokens, new GeneratedFileBaselineRepository($this->root, $state), $state, $resources,
            static fn (): array => $bundle, static function (string $point) use ($checkpoint): void {
                if ($point === $checkpoint) throw new GenerationInterruptionException('模拟进程中断');
            });
        $token = $tokens->issue(GenerationTransactionService::bundleDigest($bundle));
        $interrupted = false;
        try { $service->execute(...[...$this->ids, $bundle, $token, fn (): bool => $state->claimGeneration(...[...$this->ids, 'cli'])]); }
        catch (GenerationInterruptionException) { $interrupted = true; }
        $resources->rollback();
        self::assertTrue($interrupted);
        self::assertSame("<?php // after\n", file_get_contents($this->root . '/example.php'));
        $file = glob($this->root . '/runtime/private/business-development/wal/*.json')[0];
        self::assertSame(0600, fileperms($file) & 0777);
        return basename($file, '.json');
    }

    private function command(): CommandTester
    {
        self::assertSame(BusinessGenerationRecover::class, (new \ReflectionMethod(BusinessGenerationRecover::class, '__construct'))->getDeclaringClass()->getName());
        return new CommandTester(new BusinessGenerationRecover($this->root));
    }

    public function test_cli_inspection_and_rollback_of_uncommitted_files_are_idempotent(): void
    {
        $id = $this->interrupt('after_file_rename');
        $command = $this->command();
        self::assertSame(1, $command->execute([]));
        self::assertSame(0, $command->execute(['transaction' => $id, '--inspect' => true]), $command->getDisplay());
        self::assertSame("<?php // after\n", file_get_contents($this->root . '/example.php'));
        $lock = fopen($this->root . '/runtime/cache/business-development-write.lock', 'c+');
        self::assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        self::assertSame(1, $command->execute(['transaction' => $id]));
        self::assertSame("<?php // after\n", file_get_contents($this->root . '/example.php'));
        flock($lock, LOCK_UN); fclose($lock);
        self::assertSame(0, $command->execute(['transaction' => $id]), $command->getDisplay());
        self::assertSame('rolled_back', json_decode($command->getDisplay(), true)['state']);
        self::assertSame('failed', Db::table('crud_generation')->value('status'));
        self::assertSame('rolled_back', Db::table('crud_generation')->value('recovery_status'));
        self::assertSame("<?php // before\n", file_get_contents($this->root . '/example.php'));
        self::assertSame(0, $command->execute(['transaction' => $id]));
        self::assertSame(1, $command->execute(['transaction' => '../escape']));
    }

    public function test_committed_database_and_baselines_finalize_without_rolling_back_files(): void
    {
        $id = $this->interrupt('after_resource_commit');
        self::assertSame('completed', Db::table('crud_generation')->value('status'));
        $command = $this->command();
        self::assertSame(0, $command->execute(['transaction' => $id]), $command->getDisplay());
        self::assertSame('completed', json_decode($command->getDisplay(), true)['state']);
        self::assertSame("<?php // after\n", file_get_contents($this->root . '/example.php'));
        $baselines = new GeneratedFileBaselineRepository($this->root, new DatabaseGenerationStateRepository());
        $record = $baselines->baselines($this->ids[0])[0];
        self::assertSame("<?php // after\n", $baselines->load($record['base_storage_path'], $record['base_hash']));
        self::assertSame(0, $command->execute(['--all-stale' => true]));
        self::assertSame([], json_decode($command->getDisplay(), true));
    }

    public function test_drift_requires_manual_recovery_without_overwriting_human_edit(): void
    {
        $id = $this->interrupt('after_file_rename');
        file_put_contents($this->root . '/example.php', 'human edit');
        $command = $this->command();
        self::assertSame(1, $command->execute(['transaction' => $id]));
        self::assertStringContainsString('人工恢复', $command->getDisplay());
        self::assertSame('human edit', file_get_contents($this->root . '/example.php'));
        self::assertSame('recovery_required', Db::table('crud_generation')->value('recovery_status'));
        self::assertSame(1, $command->execute(['transaction' => $id]));
        self::assertSame('recovery_required', GenerationTransactionService::production($this->root)->inspect($id)['state']);
    }

    public function test_mismatched_database_transaction_binding_rejects_recovery_before_file_changes(): void
    {
        $id = $this->interrupt('after_file_rename');
        Db::table('crud_generation')->where('id', $this->ids[1])->update(['transaction_id' => bin2hex(random_bytes(16))]);
        $command = $this->command();
        self::assertSame(1, $command->execute(['transaction' => $id]));
        self::assertStringContainsString('GENERATION_BINDING_CONFLICT', $command->getDisplay());
        self::assertSame("<?php // after\n", file_get_contents($this->root . '/example.php'));
    }

    public static function uncommittedCheckpoints(): array
    {
        return array_map(static fn (string $point): array => [$point], ['after_safe_commit', 'after_files_written', 'after_resources', 'after_blob_materialized', 'before_db_commit']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('uncommittedCheckpoints')]
    public function test_uncommitted_checkpoint_recovery_removes_orphan_blobs_and_restores_file(string $point): void
    {
        $id = $this->interrupt($point);
        $command = $this->command();
        self::assertSame(0, $command->execute(['transaction' => $id]), $command->getDisplay());
        self::assertSame('rolled_back', json_decode($command->getDisplay(), true)['state']);
        self::assertSame("<?php // before\n", file_get_contents($this->root . '/example.php'));
        self::assertSame([], glob($this->root . '/runtime/private/business-development/blobs/*/*.blob'));
        self::assertSame(0, Db::table('generated_file_baseline')->count());
    }

    public function test_committed_blob_drift_requires_manual_recovery_and_preserves_generated_file(): void
    {
        $id = $this->interrupt('after_resource_commit');
        $record = Db::table('generated_file_baseline')->first();
        file_put_contents($this->root . '/runtime/private/business-development/' . $record->base_storage_path, 'tampered');
        $command = $this->command();
        self::assertSame(1, $command->execute(['transaction' => $id]));
        self::assertStringContainsString('人工恢复', $command->getDisplay());
        self::assertSame("<?php // after\n", file_get_contents($this->root . '/example.php'));
        self::assertSame('recovery_required', Db::table('crud_generation')->value('recovery_status'));
    }

    public function test_api_recovery_preserves_committed_evidence_after_claim_changes_status(): void
    {
        $id = $this->interrupt('after_resource_commit');
        $state = new DatabaseGenerationStateRepository();
        $state->markFailed($this->ids[1], 'INTERRUPTED', ['reason' => 'interrupted'], 'cli');
        $service = GenerationTransactionService::production($this->root);
        $result = $service->recoverGeneration($this->ids[1], 'none', 'api');
        self::assertSame('completed', $result['state']);
        self::assertSame("<?php // after\n", file_get_contents($this->root . '/example.php'));
        self::assertSame('recovered_completed', $state->generationForRecovery($this->ids[1])['recovery_status']);
        self::assertTrue($state->isGenerationCommitted($this->ids[0], $this->ids[1], $id, $service->inspect($id)['plan_digest']));
    }

    public function test_production_factory_uses_webman_project_root(): void
    {
        $service = GenerationTransactionService::production();
        self::assertSame(base_path(), (new \ReflectionProperty($service, 'projectRoot'))->getValue($service));
    }
}
