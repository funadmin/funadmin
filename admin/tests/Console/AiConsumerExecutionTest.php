<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\contract\AiToolExecutor;
use app\admin\ai\job\AiAgentJob;
use app\admin\ai\model\AiTask;
use app\admin\ai\repository\DatabaseAiConversationStore;
use app\admin\ai\service\AiAgentOrchestrator;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

class AiConsumerExecutionTest extends TestCase
{
    private bool $ready = false;
    private const TABLES = ['ai_task', 'ai_conversation', 'ai_message', 'ai_task_event', 'ai_conversation_group'];

    protected function setUp(): void
    {
        if (getenv('CONSOLE_TEST_MYSQL') === '1') {
            self::assertSame('mysql', Db::connection()->getDriverName());
            self::assertMatchesRegularExpression('/^webman_console_test_[a-f0-9]{12}$/D', Db::connection()->getDatabaseName());
            self::assertMatchesRegularExpression('/^funadmin-console-test-[a-f0-9]{12}$/D', (string) Db::connection()->getConfig('host'));
        } else {
            self::assertSame('sqlite', Db::connection()->getDriverName());
            self::assertSame(':memory:', Db::connection()->getDatabaseName());
        }
        $this->ready = true;
        foreach (self::TABLES as $name) {
            Db::schema()->dropIfExists($name);
            Db::schema()->create($name, static function (Blueprint $table) use ($name): void {
                $table->id();
                $table->timestamps();
                $table->softDeletes();
                if ($name === 'ai_task') {
                    $table->unsignedBigInteger('conversation_id');
                    $table->string('operation_token');
                    $table->string('status')->default('pending');
                    $table->string('approval_mode')->default('ask');
                    $table->integer('max_rounds')->default(2);
                    $table->integer('total_token_budget')->default(100);
                    foreach (['input', 'output', 'usage', 'error'] as $field) $table->json($field)->nullable();
                    foreach (['started_at', 'heartbeat_at', 'completed_at'] as $field) $table->dateTime($field)->nullable();
                }
                if ($name === 'ai_conversation') {
                    $table->char('uuid', 36)->nullable();
                    $table->unsignedBigInteger('admin_id');
                    $table->string('title')->default('');
                    $table->unsignedBigInteger('group_id')->nullable();
                    $table->boolean('is_archived')->default(false);
                    $table->json('context')->nullable();
                    $table->boolean('is_unread')->default(false);
                }
                if ($name === 'ai_conversation_group') {
                    $table->unsignedBigInteger('admin_id');
                    $table->string('name');
                    $table->unique(['admin_id', 'name']);
                }
                if ($name === 'ai_message') {
                    $table->unsignedBigInteger('conversation_id');
                    $table->integer('sequence');
                    $table->string('role');
                    $table->json('content');
                    $table->json('metadata')->nullable();
                    $table->json('usage')->nullable();
                }
                if ($name === 'ai_task_event') {
                    $table->unsignedBigInteger('task_id');
                    $table->integer('sequence');
                    $table->string('type');
                    $table->json('payload');
                }
            });
        }
        Db::table('ai_conversation')->insert(['id' => 1, 'admin_id' => 7]);
        AiTask::create(['id' => 1, 'conversation_id' => 1, 'operation_token' => 'operation-one', 'input' => ['admin_id' => 7, 'messages' => [['role' => 'user', 'content' => 'hello']]]]);
    }

    protected function tearDown(): void
    {
        if ($this->ready) foreach (self::TABLES as $name) Db::schema()->dropIfExists($name);
    }

    protected function job(object $provider): AiAgentJob
    {
        $tools = new class implements AiToolExecutor {
            public function execute(array $call): array { throw new \RuntimeException('此测试不允许副作用工具'); }
        };
        return new AiAgentJob(new DatabaseAiConversationStore(), new AiAgentOrchestrator($provider, $tools));
    }

    public function test_conversation_group_changes_preserve_ownership_and_json_context(): void
    {
        $store = new DatabaseAiConversationStore();
        $group = $store->createConversationGroup(['admin_id' => 7, 'name' => '测试分组']);
        $conversation = $store->createConversation(['admin_id' => 7, 'title' => '测试会话', 'group_id' => $group['id'], 'context' => ['project' => 'test']]);
        self::assertSame(['project' => 'test'], $conversation['context']);
        self::assertNull($store->conversation($conversation['id'], 8));
        self::assertCount(1, $store->conversationPageRows(7, ['group_id' => $group['id']], null, 2));
        self::assertFalse($store->deleteConversationGroup($group['id'], 8));
        self::assertTrue($store->deleteConversationGroup($group['id'], 7));
        self::assertNull($store->conversation($conversation['id'], 7)['group_id']);
        self::assertTrue($store->conversation($conversation['id'], 7)['is_archived']);
        self::assertSame([], $store->conversationGroups(7));
    }

    public function test_webman_consumer_rejects_malformed_payload_before_job_creation(): void
    {
        $consumer = new \app\admin\ai\queue\AiAgentConsumer();
        self::assertInstanceOf(\Webman\RedisQueue\Consumer::class, $consumer);
        $this->expectExceptionMessage('AI 队列消息格式无效');
        $consumer->consume(['taskId' => 0]);
    }

    public function test_consumer_persists_response_events_and_is_idempotent(): void
    {
        $provider = new class {
            public int $calls = 0;
            public function chat(array $messages, array $definitions, ?callable $event): array {
                $this->calls++;
                return ['content' => '测试回复', 'toolCalls' => [], 'usage' => ['totalTokens' => 5], 'model' => 'local-test'];
            }
        };
        $job = $this->job($provider);
        $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']);
        self::assertSame('succeeded', AiTask::findOrFail(1)->status);
        self::assertSame(5, AiTask::findOrFail(1)->usage['totalTokens']);
        self::assertSame(1, (int) Db::table('ai_conversation')->value('is_unread'));
        $store = new DatabaseAiConversationStore();
        $content = $store->messages(1)[0]['content'];
        self::assertCount(1, $content);
        self::assertSame('text', $content[0]['type']);
        self::assertSame('测试回复', $content[0]['text']);
        self::assertSame(['task.heartbeat', 'round.started', 'assistant.message', 'task.succeeded'], array_column($store->events(1, 0, 100), 'type'));
        $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']);
        self::assertSame(1, $provider->calls);
        self::assertSame(1, Db::table('ai_message')->count());
    }

    public function test_invalid_operation_token_cannot_claim_task(): void
    {
        $job = $this->job(new \stdClass());
        try {
            $job->consume(['taskId' => 1, 'operationToken' => 'wrong']);
            self::fail('错误 operation token 不得运行');
        } catch (\RuntimeException $error) {
            self::assertSame('AI task operation token 无效', $error->getMessage());
        }
        self::assertSame('pending', AiTask::findOrFail(1)->status);
        self::assertSame(0, Db::table('ai_task_event')->count());
    }

    public function test_provider_failure_marks_terminal_task_and_retry_does_not_repeat_side_effects(): void
    {
        $provider = new class {
            public int $calls = 0;
            public function chat(array $messages, array $definitions, ?callable $event): array { $this->calls++; throw new \RuntimeException('provider unavailable'); }
        };
        $job = $this->job($provider);
        try {
            $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']);
            self::fail('Provider 异常必须进入失败重试队列');
        } catch (\RuntimeException $error) { self::assertSame('provider unavailable', $error->getMessage()); }
        self::assertSame('failed', AiTask::findOrFail(1)->status);
        $job->consume(['taskId' => 1, 'operationToken' => 'operation-one']);
        self::assertSame(1, $provider->calls);
    }

    public function test_fallback_switch_and_final_model_are_recorded_in_events_and_message_metadata(): void
    {
        $cap = static fn (string $model): array => ['model' => $model, 'context_window' => 200000, 'max_output_tokens' => 1000];
        $handler = new \GuzzleHttp\Handler\MockHandler([
            new \GuzzleHttp\Psr7\Response(503, [], '{"error":{"message":"busy"}}'),
            new \GuzzleHttp\Psr7\Response(200, [], json_encode(['model' => 'backup-1', 'choices' => [['message' => ['content' => '备用回复'], 'finish_reason' => 'stop']], 'usage' => ['total_tokens' => 4]], JSON_THROW_ON_ERROR)),
        ]);
        $gateway = new \app\common\ai\provider\OpenAiCompatibleGateway(new \GuzzleHttp\Client(['handler' => \GuzzleHttp\HandlerStack::create($handler)]), [
            'protocol' => 'openai-chat', 'base_url' => 'https://8.8.8.8/v1', 'api_key' => 'key', 'model' => 'primary', 'max_retries' => 0,
            'fallback_enabled' => true, 'fallback_models' => ['backup-1'], 'max_output_tokens' => 100, 'model_capabilities' => [$cap('primary'), $cap('backup-1')],
        ]);
        $this->job($gateway)->consume(['taskId' => 1, 'operationToken' => 'operation-one']);
        self::assertSame('succeeded', AiTask::findOrFail(1)->status);
        $store = new DatabaseAiConversationStore();
        $events = $store->events(1, 0, 100);
        $fallback = array_values(array_filter($events, static fn (array $event): bool => $event['type'] === 'provider.fallback'));
        self::assertCount(1, $fallback);
        self::assertSame(['primary', 'backup-1', 'provider_unavailable', 503], [$fallback[0]['payload']['from_model'], $fallback[0]['payload']['to_model'], $fallback[0]['payload']['reason'], $fallback[0]['payload']['status']]);
        $completed = array_values(array_filter($events, static fn (array $event): bool => $event['type'] === 'provider.completed'));
        self::assertSame('backup-1', $completed[0]['payload']['model']);
        $metadata = $store->messages(1)[0]['metadata'];
        self::assertSame('backup-1', $metadata['model']);
        self::assertSame('backup-1', $metadata['requested_model']);
        // MySQL JSON 列会按自身规则重排对象键，只比较切换记录的顺序与内容，不比较键顺序。
        $fallbacks = array_map(static function (array $switch): array { ksort($switch); return $switch; }, $metadata['fallbacks']);
        self::assertSame([['from_model' => 'primary', 'reason' => 'provider_unavailable', 'status' => 503, 'to_model' => 'backup-1']], $fallbacks);
    }
}
