<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\infrastructure\ContainerAiToolExecutor;
use app\admin\ai\job\AiAgentJob;
use app\admin\ai\repository\DatabaseAiConversationStore;
use app\admin\ai\repository\DatabaseAiSecurityStore;
use app\admin\ai\service\AgentSandboxManager;
use app\admin\ai\service\AgentToolRegistry;
use app\admin\ai\service\AiAgentOrchestrator;
use app\admin\ai\service\AiApprovalService;
use app\admin\ai\service\AiAuditService;
use app\admin\ai\service\ApprovalPolicyEngine;
use Illuminate\Database\Schema\Blueprint;
use support\Db;

class AiSandboxConsumerExecutionTest extends AiConsumerExecutionTest
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/funadmin-ai-runtime-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/project', 0700, true);
        file_put_contents($this->root . '/project/example.txt', 'before');
        file_put_contents($this->root . '/project/.env', 'must-not-export');
        Db::schema()->table('ai_task', static function (Blueprint $table): void {
            foreach (['container_task_id', 'sandbox_volume', 'workspace_path', 'sandbox_status'] as $name) $table->string($name)->nullable();
            $table->boolean('sandbox_retained')->default(false); $table->unsignedBigInteger('change_set_id')->nullable(); $table->dateTime('cleanup_at')->nullable();
        });
        Db::schema()->create('ai_change_set', static function (Blueprint $table): void {
            $table->id();
            foreach (['conversation_id', 'task_id', 'created_by'] as $name) $table->unsignedBigInteger($name);
            foreach (['idempotency_key', 'digest', 'base_digest', 'patch_path', 'patch_sha256', 'status'] as $name) $table->string($name);
            foreach (['base_file_hashes', 'manifest', 'summary'] as $name) $table->json($name);
            $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('ai_approval', static function (Blueprint $table): void {
            $table->id();
            foreach (['conversation_id', 'task_id', 'tool_call_id', 'requested_by'] as $name) $table->unsignedBigInteger($name);
            $table->unsignedBigInteger('decided_by')->nullable();
            foreach (['scope', 'risk_reason', 'nonce', 'digest', 'operation', 'mode_snapshot', 'status'] as $name) $table->string($name);
            foreach (['impact', 'request', 'decision'] as $name) $table->json($name)->nullable();
            $table->integer('cas_version')->default(0); $table->dateTime('expires_at'); $table->dateTime('decided_at')->nullable(); $table->timestamps(); $table->softDeletes();
        });
        Db::schema()->create('ai_tool_call', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('conversation_id'); $table->unsignedBigInteger('task_id'); $table->unsignedBigInteger('message_id')->nullable(); $table->unsignedBigInteger('approval_id')->nullable();
            foreach (['idempotency_key', 'tool_name', 'operation', 'risk_level', 'status', 'approval_decision'] as $name) $table->string($name);
            foreach (['redacted_arguments', 'side_effects', 'error'] as $name) $table->json($name)->nullable();
            foreach (['stdout_summary', 'stdout_hash', 'stdout_path', 'stderr_summary', 'stderr_hash', 'stderr_path'] as $name) $table->text($name)->nullable();
            $table->integer('exit_code')->nullable(); $table->integer('duration_ms')->nullable(); $table->dateTime('started_at')->nullable(); $table->dateTime('completed_at')->nullable();
            $table->unique(['conversation_id', 'idempotency_key']); $table->timestamps(); $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        foreach (['ai_change_set', 'ai_tool_call', 'ai_approval'] as $table) Db::schema()->dropIfExists($table);
        parent::tearDown();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($this->root);
    }

    public function test_cleanup_failure_keeps_exported_artifact_and_marks_recovery_checkpoint(): void
    {
        $runner = new SandboxProcessFixture($this->root);
        $runner->failCleanup = true;
        $sandbox = new AgentSandboxManager($runner, $this->root . '/project', $this->root . '/private', ['image' => 'test@sha256:' . str_repeat('a', 64)]);
        $provider = new class {
            public function chat(array $messages, array $tools, ?callable $event): array {
                return ['content' => 'done', 'usage' => ['totalTokens' => 1], 'toolCalls' => [], 'model' => 'local-test'];
            }
        };
        $tools = new class implements \app\admin\ai\contract\AiToolExecutor { public function execute(array $call): array { return []; } };
        $job = new AiAgentJob(new DatabaseAiConversationStore(), new AiAgentOrchestrator($provider, $tools), $sandbox);
        $error = null;
        try { $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']); } catch (\RuntimeException $exception) { $error = $exception; }
        self::assertNotNull($error);
        self::assertSame('failed', Db::table('ai_task')->value('sandbox_status'));
        self::assertSame(1, (int) Db::table('ai_task')->value('sandbox_retained'));
        self::assertSame(1, Db::table('ai_change_set')->count());
        self::assertNotNull(Db::table('ai_task')->value('change_set_id'));
        self::assertSame(1, Db::table('ai_task_event')->where('type', 'ai.sandbox.cleanup_failed')->count());
    }

    public function test_failed_artifact_export_is_not_reported_as_success_and_keeps_recovery_checkpoint(): void
    {
        $runner = new SandboxProcessFixture($this->root);
        $runner->failExport = true;
        $sandbox = new AgentSandboxManager($runner, $this->root . '/project', $this->root . '/private', ['image' => 'test@sha256:' . str_repeat('a', 64)]);
        $provider = new class {
            public function chat(array $messages, array $tools, ?callable $event): array {
                return ['content' => 'done', 'usage' => ['totalTokens' => 1], 'toolCalls' => [], 'model' => 'local-test'];
            }
        };
        $tools = new class implements \app\admin\ai\contract\AiToolExecutor { public function execute(array $call): array { return []; } };
        $job = new AiAgentJob(new DatabaseAiConversationStore(), new AiAgentOrchestrator($provider, $tools), $sandbox);
        $rejected = false;
        try { $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']); }
        catch (\RuntimeException $error) { $rejected = true; self::assertStringContainsString('导出', $error->getMessage()); }
        self::assertTrue($rejected);
        self::assertSame('failed', Db::table('ai_task')->value('status'));
        self::assertSame(1, (int) Db::table('ai_task')->value('sandbox_retained'));
        self::assertSame('failed', Db::table('ai_task')->value('sandbox_status'));
        self::assertSame(0, Db::table('ai_change_set')->count());
        self::assertCount(1, glob($this->root . '/private/sandboxes/*'));
    }

    public function test_approval_pause_resume_consumes_once_and_exports_only_after_completion(): void
    {
        $runner = new SandboxProcessFixture($this->root);
        $sandbox = new AgentSandboxManager($runner, $this->root . '/project', $this->root . '/private', ['image' => 'test@sha256:' . str_repeat('a', 64)]);
        $security = new DatabaseAiSecurityStore();
        $approvals = new AiApprovalService($security);
        $tools = new ContainerAiToolExecutor(new AgentToolRegistry(['write']), new ApprovalPolicyEngine(), $approvals, new AiAuditService($this->root . '/logs'), $security, $sandbox);
        $provider = new class {
            public int $calls = 0;
            public function chat(array $messages, array $definitions, ?callable $event): array {
                return ['content' => 'approval test', 'usage' => ['totalTokens' => 2], 'model' => 'local-test', 'toolCalls' => ++$this->calls === 1
                    ? [['id' => 'approve-write', 'name' => 'write', 'arguments' => ['path' => 'example.txt', 'content' => 'approved-write']]] : []];
            }
        };
        Db::table('ai_task')->where('id', 1)->update(['approval_mode' => 'request_approval']);
        $job = new AiAgentJob(new DatabaseAiConversationStore(), new AiAgentOrchestrator($provider, $tools), $sandbox, $security);
        $payload = ['taskId' => 1, 'operationToken' => 'operation-one'];
        $job->consume($payload);
        self::assertSame('paused', Db::table('ai_task')->value('status'));
        self::assertSame(0, Db::table('ai_change_set')->count());
        self::assertSame('before', file_get_contents($runner->tree . '/example.txt'));
        $approval = $approvals->pending(7)[0];
        $approvals->decide($approval['id'], 7, 'approve', 'once', $approval['nonce'], $approval['digest'], $approval['cas_version'], 'request_approval');
        Db::table('ai_task')->where('id', 1)->update(['status' => 'resume_pending']);
        $job->consume($payload);
        self::assertSame('succeeded', Db::table('ai_task')->value('status'));
        self::assertSame('cleaned', Db::table('ai_task')->value('sandbox_status'));
        self::assertSame('consumed', Db::table('ai_approval')->value('status'));
        self::assertSame('approved-write', file_get_contents($runner->tree . '/example.txt'));
        self::assertSame(1, Db::table('ai_tool_call')->count());
        self::assertSame(1, Db::table('ai_change_set')->count());
        $job->consume($payload);
        self::assertSame(2, $provider->calls);
    }

    public function test_consumer_tools_export_changeset_and_cleanup_use_real_services_and_database(): void
    {
        $runner = new SandboxProcessFixture($this->root);
        $sandbox = new AgentSandboxManager($runner, $this->root . '/project', $this->root . '/private', ['image' => 'test@sha256:' . str_repeat('a', 64)]);
        $security = new DatabaseAiSecurityStore();
        $tools = new ContainerAiToolExecutor(new AgentToolRegistry(['read', 'write']), new ApprovalPolicyEngine(), new AiApprovalService($security), new AiAuditService($this->root . '/logs'), $security, $sandbox);
        $provider = new class {
            public int $calls = 0;
            public function chat(array $messages, array $tools, ?callable $event): array {
                $this->calls++;
                return ['content' => 'local provider', 'usage' => ['totalTokens' => 2], 'model' => 'local-test', 'toolCalls' => $this->calls === 1 ? [
                    ['id' => 'read-one', 'name' => 'read', 'arguments' => ['path' => 'example.txt']],
                    ['id' => 'write-one', 'name' => 'write', 'arguments' => ['path' => 'example.txt', 'content' => 'after']],
                ] : []];
            }
        };
        Db::table('ai_task')->where('id', 1)->update(['approval_mode' => 'agent_approval']);
        $job = new AiAgentJob(new DatabaseAiConversationStore(), new AiAgentOrchestrator($provider, $tools), $sandbox, $security);
        $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']);
        self::assertSame('succeeded', Db::table('ai_task')->value('status'));
        self::assertSame('cleaned', Db::table('ai_task')->value('sandbox_status'));
        self::assertSame(2, Db::table('ai_tool_call')->where('status', 'succeeded')->count());
        $changeSet = \app\admin\ai\model\AiChangeSet::query()->firstOrFail();
        self::assertIsArray($changeSet->manifest);
        self::assertSame(hash('sha256', 'before'), $changeSet->base_file_hashes['example.txt']['sha256']);
        self::assertFileExists($changeSet->manifest['bundle_path']);
        self::assertStringNotContainsString('must-not-export', file_get_contents($changeSet->manifest['bundle_path']));
        self::assertSame('before', file_get_contents($this->root . '/project/example.txt'));
        self::assertCount(1, glob($this->root . '/private/exports/*/manifest.json'));
        self::assertSame([], glob($this->root . '/private/sandboxes/*'));
        $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']);
        self::assertSame(2, $provider->calls);
        self::assertSame(1, Db::table('ai_change_set')->count());
    }
}
