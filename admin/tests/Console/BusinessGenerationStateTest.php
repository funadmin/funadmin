<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\development\repository\DatabaseGenerationStateRepository;
use app\admin\development\model\BusinessModule;
use app\admin\development\model\CrudGeneration;
use app\admin\development\model\GeneratedFileBaseline;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class BusinessGenerationStateTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        BusinessGenerationFixture::create();
    }

    protected function tearDown(): void { BusinessGenerationFixture::drop(); }

    public function test_json_models_and_claim_binding_commit_evidence_and_baseline_round_trip(): void
    {
        [$module, $generation] = BusinessGenerationFixture::seed();
        self::assertIsArray(CrudGeneration::query()->findOrFail($generation)->definition);
        self::assertSame([], BusinessModule::query()->findOrFail($module)->metadata);
        $state = new DatabaseGenerationStateRepository();
        self::assertFalse($state->claimGeneration($module + 1, $generation, 'cli'));
        self::assertTrue($state->claimGeneration($module, $generation, 'cli'));
        self::assertFalse($state->claimGeneration($module, $generation, 'other'));
        $transaction = bin2hex(random_bytes(16));
        $state->bindGenerationTransaction($module, $generation, $transaction);
        self::assertSame($transaction, $state->generationForRecovery($generation)['transaction_id']);
        $digest = str_repeat('d', 64);
        self::assertFalse($state->isGenerationCommitted($module, $generation, $transaction, $digest));
        $record = ['business_module_id' => $module, 'generation_id' => $generation, 'relative_path' => 'app/generated.php', 'artifact_type' => 'model',
            'base_hash' => str_repeat('b', 64), 'base_storage_path' => 'blobs/bb/' . str_repeat('b', 64) . '.blob', 'target_hash' => str_repeat('b', 64),
            'template_version' => 'native', 'definition_hash' => str_repeat('a', 64), 'content_kind' => 'text', 'status' => 'active'];
        $state->commitGeneration($module, $generation, [$record], $transaction, $digest);
        self::assertTrue($state->isGenerationCommitted($module, $generation, $transaction, $digest));
        self::assertFalse($state->isGenerationCommitted($module + 1, $generation, $transaction, $digest));
        self::assertSame('completed', $state->completedResult($generation)['state']);
        self::assertSame('published', BusinessModule::query()->findOrFail($module)->lifecycle_status);
        self::assertCount(1, $state->loadBaselines($module));
        self::assertTrue($state->isBaselineBlobReferenced($record['base_storage_path']));
        GeneratedFileBaseline::query()->firstOrFail()->delete();
        self::assertTrue($state->isBaselineBlobReferenced($record['base_storage_path']));
        self::assertSame([], $state->loadBaselines($module));
    }

    public function test_commit_failure_rolls_back_baselines_and_generation_changes(): void
    {
        [$module, $generation] = BusinessGenerationFixture::seed();
        Db::table('crud_generation')->where('id', $generation)->update(['form_id' => 999]);
        $state = new DatabaseGenerationStateRepository();
        $rejected = false;
        try { $state->commitGeneration($module, $generation, [], bin2hex(random_bytes(16)), str_repeat('d', 64)); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('表单不匹配', $error->getMessage()); }
        self::assertTrue($rejected);
        self::assertSame('planned', Db::table('crud_generation')->value('status'));
        self::assertSame('draft', Db::table('business_module')->value('lifecycle_status'));
    }

    public function test_create_reuse_supersede_and_adoption_cas_do_not_cross_modules(): void
    {
        [$module, $generation] = BusinessGenerationFixture::seed();
        $state = new DatabaseGenerationStateRepository();
        $row = ['business_module_id' => $module, 'operation_key' => 'managed:' . bin2hex(random_bytes(32)), 'status' => 'planned',
            'definition_hash' => str_repeat('a', 64), 'definition' => ['routePath' => '/business/console-recovery']];
        $created = $state->createOrReuseGeneration($row, 'cli');
        self::assertSame($created['id'], $state->createOrReuseGeneration($row, 'cli')['id']);
        self::assertSame('superseded', Db::table('crud_generation')->where('id', $generation)->value('status'));
        self::assertSame($created['id'], (int) Db::table('crud_generation')->where('id', $generation)->value('superseded_by_id'));
        $state->markConflict($created['id'], 'CONFLICT', [], 'cli');
        $record = ['relative_path' => 'app/generated.php', 'artifact_type' => 'model', 'base_hash' => str_repeat('b', 64),
            'base_storage_path' => 'blobs/bb/' . str_repeat('b', 64) . '.blob', 'target_hash' => str_repeat('b', 64),
            'template_version' => 'native', 'definition_hash' => str_repeat('a', 64), 'content_kind' => 'text'];
        $expected = ['module' => BusinessModule::query()->findOrFail($module)->toArray(),
            'generation' => CrudGeneration::query()->findOrFail($created['id'])->toArray(), 'baseline' => null];
        $baseline = $state->adoptResolvedBaseline($module, $created['id'], $record, $expected);
        self::assertSame($module, $baseline['business_module_id']);
        self::assertCount(1, $state->loadBaselines($module));
        self::assertSame([], $state->loadBaselines($module + 1));
        $this->expectException(\app\admin\development\exception\BusinessOperationException::class);
        $state->adoptResolvedBaseline($module, $created['id'], $record, $expected);
    }

    public function test_commit_updates_bound_form_in_same_transaction(): void
    {
        [$module, $generation] = BusinessGenerationFixture::seed();
        $form = Db::table('form')->insertGetId([]);
        Db::table('business_module')->where('id', $module)->update(['form_id' => $form]);
        Db::table('crud_generation')->where('id', $generation)->update(['form_id' => $form]);
        Db::schema()->dropIfExists('form_schema_version');
        Db::schema()->create('form_schema_version', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('form_id'); $table->integer('version'); $table->string('schema_hash'); $table->json('schema_document'); $table->softDeletes();
        });
        try {
            Db::table('form_schema_version')->insert(['form_id' => $form, 'version' => 4, 'schema_hash' => str_repeat('b', 64), 'schema_document' => '{}']);
            $state = new DatabaseGenerationStateRepository();
            $state->commitGeneration($module, $generation, [], bin2hex(random_bytes(16)), str_repeat('d', 64));
            self::assertSame(4, (int) Db::table('business_module')->where('id', $module)->value('published_schema_version'));
        } finally {
            Db::schema()->dropIfExists('form_schema_version');
        }
        self::assertSame('generated', Db::table('form')->where('id', $form)->value('publish_mode'));
        self::assertSame($generation, (int) Db::table('form')->where('id', $form)->value('crud_generation_id'));
        self::assertSame('published', Db::table('form')->where('id', $form)->value('publish_status'));
    }

    public function test_recovery_cas_and_json_error_persistence(): void
    {
        [$module, $generation] = BusinessGenerationFixture::seed();
        $state = new DatabaseGenerationStateRepository();
        $state->markRecoveryRequired($generation, 'GENERATION_RECOVERY_REQUIRED', ['reason' => 'drift'], 'cli');
        self::assertSame(['reason' => 'drift'], CrudGeneration::query()->findOrFail($generation)->error);
        self::assertFalse($state->claimRecovery($generation, 'recovery_required', 'cli'));
        Db::table('crud_generation')->where('id', $generation)->update(['transaction_id' => bin2hex(random_bytes(16))]);
        self::assertTrue($state->claimRecovery($generation, 'recovery_required', 'cli'));
        self::assertFalse($state->claimRecovery($generation, 'recovery_required', 'other'));
        $state->markRecoveredCompleted($generation, 'cli');
        self::assertSame('recovered_completed', $state->generationForRecovery($generation)['recovery_status']);
    }
}
