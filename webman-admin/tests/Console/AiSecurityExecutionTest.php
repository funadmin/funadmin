<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\repository\DatabaseAiSecurityStore;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class AiSecurityExecutionTest extends TestCase
{
    private bool $ready = false;

    protected function setUp(): void
    {
        self::assertSame('sqlite', Db::connection()->getDriverName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->ready = true;
        foreach (['ai_approval', 'ai_tool_call'] as $name) {
            Db::schema()->dropIfExists($name);
            Db::schema()->create($name, static function (Blueprint $table): void {
                $table->id(); $table->unsignedBigInteger('conversation_id')->default(1); $table->unsignedBigInteger('task_id')->default(1);
                $table->integer('requested_by')->default(7); $table->integer('decided_by')->default(7); $table->integer('tool_call_id')->default(1);
                $table->string('operation')->default('file.write'); $table->string('mode_snapshot')->default('ask'); $table->string('scope')->default('once');
                $table->string('status')->default('pending'); $table->integer('cas_version')->default(1); $table->string('idempotency_key')->nullable()->unique();
                $table->dateTime('expires_at')->nullable();
                foreach (['request', 'decision', 'impact', 'redacted_arguments', 'result', 'error', 'side_effects'] as $column) $table->json($column)->nullable();
                $table->timestamps(); $table->softDeletes();
            });
        }
    }

    protected function tearDown(): void
    {
        if ($this->ready) foreach (['ai_approval', 'ai_tool_call'] as $name) Db::schema()->dropIfExists($name);
    }

    public function test_approval_cas_and_once_consumption_remain_admin_scoped(): void
    {
        $store = new DatabaseAiSecurityStore();
        $record = $store->createApproval(['requested_by' => 7, 'request' => ['path' => 'safe.php'], 'expires_at' => '2099-01-01']);
        self::assertSame(['path' => 'safe.php'], $store->approval($record['id'], 7)['request']);
        self::assertNull($store->approval($record['id'], 8));
        self::assertTrue($store->casApproval($record['id'], 1, 'pending', ['status' => 'approved']));
        self::assertFalse($store->casApproval($record['id'], 1, 'pending', ['status' => 'approved']));
        self::assertNull($store->consumeOnceApproval(1, 1, 8, 'file.write', 'ask'));
        self::assertSame('consumed', $store->consumeOnceApproval(1, 1, 7, 'file.write', 'ask')['status']);
        self::assertNull($store->consumeOnceApproval(1, 1, 7, 'file.write', 'ask'));
    }

    public function test_tool_call_idempotency_keeps_one_json_record(): void
    {
        $store = new DatabaseAiSecurityStore();
        $data = ['conversation_id' => 1, 'task_id' => 1, 'idempotency_key' => 'tool-one', 'redacted_arguments' => ['path' => 'safe.php'], 'status' => 'awaiting_approval'];
        $record = $store->createToolCall($data);
        self::assertSame($record['id'], $store->createToolCall($data)['id']);
        self::assertSame(['path' => 'safe.php'], $store->toolCall($record['id'], 1, 1)['redacted_arguments']);
        self::assertNull($store->toolCall($record['id'], 2, 1));
        self::assertSame($record['id'], $store->awaitingToolCall(1, 1)['id']);
        $store->updateToolCall($record['id'], ['status' => 'completed', 'result' => ['ok' => true]]);
        self::assertSame(['ok' => true], $store->toolCalls(1)[0]['result']);
        self::assertNull($store->awaitingToolCall(1, 1));
    }
}
