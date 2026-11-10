<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\command\AiSandboxCleanup;
use app\admin\ai\contract\DockerProcessRunner;
use app\admin\ai\infrastructure\ProcessResult;
use app\admin\ai\model\AiTask;
use app\admin\ai\service\AgentSandboxManager;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use Symfony\Component\Console\Tester\CommandTester;

final class AiSandboxCleanupTest extends TestCase
{
    private bool $ready = false;

    protected function setUp(): void
    {
        self::assertSame('sqlite', Db::connection()->getDriverName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->ready = true;
        Db::schema()->dropIfExists('ai_task');
        Db::schema()->dropIfExists('ai_task_event');
        Db::schema()->create('ai_task', static function (Blueprint $table): void {
            $table->id(); $table->integer('conversation_id')->default(1); $table->string('status')->default('succeeded');
            $table->string('sandbox_status')->default('running'); $table->boolean('sandbox_retained')->default(true);
            $table->string('container_task_id'); $table->string('workspace_path'); $table->string('sandbox_volume')->default('');
            $table->string('cleanup_lease_owner')->nullable();
            foreach (['completed_at', 'heartbeat_at', 'cleanup_lease_expires_at', 'cleanup_at'] as $field) $table->dateTime($field)->nullable();
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('ai_task_event', static function (Blueprint $table): void {
            $table->id(); $table->integer('task_id'); $table->integer('sequence'); $table->string('type'); $table->json('payload'); $table->timestamp('created_at')->nullable(); $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        if ($this->ready) foreach (['ai_task', 'ai_task_event', 'ai_conversation'] as $name) Db::schema()->dropIfExists($name);
    }

    public function test_cleanup_failure_returns_nonzero_and_preserves_foreign_container(): void
    {
        Db::schema()->table('ai_task', static function (Blueprint $table): void { $table->string('recovery_status')->nullable(); });
        Db::table('ai_task')->insert(['container_task_id' => str_repeat('a', 64), 'workspace_path' => sys_get_temp_dir() . '/foreign-workspace', 'completed_at' => '2000-01-01', 'heartbeat_at' => '2000-01-01']);
        $runner = new class implements DockerProcessRunner {
            public array $calls = [];
            public function run(array $argv, int $timeoutSeconds): ProcessResult {
                $this->calls[] = $argv;
                return new ProcessResult(0, json_encode(['com.funadmin.ai-agent' => 'true', 'com.funadmin.ai-task' => '999', 'com.funadmin.ai-session' => '1']), '');
            }
        };
        $tester = new CommandTester(new AiSandboxCleanup(new AgentSandboxManager($runner, base_path(), sys_get_temp_dir() . '/nonexistent-private', [])));
        self::assertSame(1, $tester->execute([]), $tester->getDisplay());
        self::assertSame(['cleaned' => 0, 'candidates' => 1, 'failed' => 1], json_decode($tester->getDisplay(), true));
        self::assertCount(1, $runner->calls);
        $task = AiTask::firstOrFail();
        self::assertSame('failed', $task->sandbox_status);
        self::assertSame(1, (int) $task->sandbox_retained);
        self::assertSame('recovery_required', $task->recovery_status);
        self::assertNull($task->cleanup_lease_owner);
        self::assertSame('ai.sandbox.cleanup_failed', Db::table('ai_task_event')->value('type'));
    }

    public function test_cleanup_command_only_claims_expired_terminal_sandboxes(): void
    {
        foreach (['succeeded', 'paused', 'running', 'failed'] as $index => $status) Db::table('ai_task')->insert([
            'id' => $index + 1, 'status' => $status, 'container_task_id' => str_repeat((string) ($index + 1), 64),
            'workspace_path' => sys_get_temp_dir() . '/nonexistent-console-sandbox-' . $index,
            'completed_at' => $index === 3 ? '2099-01-01' : '2000-01-01',
            'heartbeat_at' => '2000-01-01',
        ]);
        $runner = new class implements DockerProcessRunner {
            public array $calls = [];
            public function run(array $argv, int $timeoutSeconds): ProcessResult {
                $this->calls[] = $argv;
                return new ProcessResult(1, '', 'Error: No such object');
            }
        };
        $manager = new AgentSandboxManager($runner, base_path(), sys_get_temp_dir() . '/nonexistent-console-private', []);
        $tester = new CommandTester(new AiSandboxCleanup($manager));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(['cleaned' => 1, 'candidates' => 1], json_decode($tester->getDisplay(), true));
        self::assertSame('cleaned', AiTask::findOrFail(1)->sandbox_status);
        self::assertSame('running', AiTask::findOrFail(2)->sandbox_status);
        self::assertNull(AiTask::findOrFail(1)->cleanup_lease_owner);
        self::assertCount(1, $runner->calls);
        self::assertSame('ai.sandbox.cleanup', Db::table('ai_task_event')->value('type'));
        self::assertSame(0, $tester->execute([]));
        self::assertCount(1, $runner->calls);
    }

    public function test_cleanup_claim_reclaims_expired_lease_but_not_live_lease(): void
    {
        $base = ['container_task_id' => str_repeat('c', 64), 'workspace_path' => '/tmp/none', 'completed_at' => '2000-01-01'];
        foreach ([
            ['id' => 1] + $base,
            ['id' => 2, 'sandbox_status' => 'cleaning', 'cleanup_lease_owner' => 'crashed-worker', 'cleanup_lease_expires_at' => date('Y-m-d H:i:s', time() - 60)] + $base,
            ['id' => 3, 'sandbox_status' => 'cleaning', 'cleanup_lease_owner' => 'live-worker', 'cleanup_lease_expires_at' => date('Y-m-d H:i:s', time() + 600)] + $base,
        ] as $row) Db::table('ai_task')->insert($row);
        $expiresAt = time() + 300;
        self::assertTrue(AiTask::claimSandboxCleanup(1, 'me', $expiresAt), 'unleased sandbox must be claimable');
        self::assertTrue(AiTask::claimSandboxCleanup(2, 'me', $expiresAt), 'expired cleanup lease must be reclaimed');
        self::assertFalse(AiTask::claimSandboxCleanup(3, 'me', $expiresAt), 'live cleanup lease must not be stolen');
        self::assertSame('me', AiTask::findOrFail(2)->cleanup_lease_owner);
        self::assertSame('live-worker', AiTask::findOrFail(3)->cleanup_lease_owner);
        self::assertTrue(AiTask::finishSandboxCleanup(2, ['sandbox_status' => 'cleaned'], 'me'));
        self::assertFalse(AiTask::finishSandboxCleanup(3, ['sandbox_status' => 'cleaned'], 'me'));
        self::assertNull(AiTask::findOrFail(2)->cleanup_lease_owner);
    }

    public function test_admin_scoped_cleanup_candidates_only_include_own_expired_terminal_tasks(): void
    {
        Db::schema()->create('ai_conversation', static function (Blueprint $table): void {
            $table->id(); $table->integer('admin_id'); $table->string('uuid')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Db::table('ai_conversation')->insert([['id' => 1, 'admin_id' => 7], ['id' => 2, 'admin_id' => 8]]);
        $base = ['container_task_id' => str_repeat('d', 64), 'workspace_path' => '/tmp/none', 'completed_at' => '2000-01-01'];
        foreach ([
            ['id' => 1, 'conversation_id' => 1] + $base,
            ['id' => 2, 'conversation_id' => 2] + $base,
            ['id' => 3, 'conversation_id' => 1, 'status' => 'running'] + $base,
            ['id' => 4, 'conversation_id' => 1, 'completed_at' => '2099-01-01'] + $base,
        ] as $row) Db::table('ai_task')->insert($row);
        $cutoff = date('Y-m-d H:i:s');
        self::assertSame([1], array_column(AiTask::sandboxCleanupCandidates($cutoff, 7), 'id'));
        self::assertSame([1, 2], array_column(AiTask::sandboxCleanupCandidates($cutoff), 'id'));
    }
}
