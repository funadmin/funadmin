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
            $table->id(); $table->integer('task_id'); $table->integer('sequence'); $table->string('type'); $table->json('payload'); $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        if ($this->ready) foreach (['ai_task', 'ai_task_event'] as $name) Db::schema()->dropIfExists($name);
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
}
