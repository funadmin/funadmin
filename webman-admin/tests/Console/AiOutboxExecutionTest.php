<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\model\AiOutbox;
use app\admin\ai\service\AiOutboxDispatcher;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class AiOutboxExecutionTest extends TestCase
{
    private bool $ready = false;

    protected function setUp(): void
    {
        self::assertSame('sqlite', Db::connection()->getDriverName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->ready = true;
        Db::schema()->dropIfExists('ai_outbox');
        Db::schema()->create('ai_outbox', static function (Blueprint $table): void {
            $table->id();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->integer('attempts')->default(0);
            $table->string('lease_owner')->nullable();
            foreach (['available_at', 'lease_expires_at', 'dispatched_at'] as $field) $table->dateTime($field)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if ($this->ready) Db::schema()->dropIfExists('ai_outbox');
    }

    private function pending(int $taskId): void
    {
        Db::table('ai_outbox')->insert(['payload' => json_encode(['taskId' => $taskId, 'operationToken' => 'operation-' . $taskId]), 'available_at' => '2000-01-01']);
    }

    public function test_command_returns_nonzero_when_outbox_storage_is_unavailable(): void
    {
        Db::schema()->drop('ai_outbox');
        $command = new \Symfony\Component\Console\Tester\CommandTester(new \app\admin\ai\command\AiOutboxDispatch());
        self::assertSame(1, $command->execute([]));
        self::assertStringNotContainsString('"dispatched":', $command->getDisplay());
    }

    public function test_database_dispatch_claims_each_row_once_and_preserves_json_payload(): void
    {
        $this->pending(7);
        $this->pending(8);
        $sent = [];
        $dispatcher = AiOutboxDispatcher::database(static function (array $payload) use (&$sent): bool { $sent[] = $payload; return true; });
        self::assertSame(2, $dispatcher->dispatch());
        self::assertSame([7, 8], array_column($sent, 'taskId'));
        self::assertSame(2, AiOutbox::where('status', 'dispatched')->count());
        self::assertSame(0, $dispatcher->dispatch());
        self::assertCount(2, $sent);
    }

    public function test_false_enqueue_result_reschedules_instead_of_marking_dispatched(): void
    {
        $this->pending(7);
        self::assertSame(0, AiOutboxDispatcher::database(static fn (array $payload): bool => false)->dispatch());
        $row = AiOutbox::firstOrFail();
        self::assertSame('pending', $row->status);
        self::assertSame(1, (int) $row->attempts);
        self::assertNull($row->lease_owner);
        self::assertNull($row->dispatched_at);
        self::assertGreaterThan(time(), strtotime($row->available_at));
        self::assertSame(0, AiOutboxDispatcher::database(static fn (): bool => true)->dispatch());
    }

    public function test_expired_processing_row_is_claimed_without_overwriting_other_rows(): void
    {
        $this->pending(7);
        $this->pending(8);
        AiOutbox::where('id', 1)->update(['status' => 'processing', 'lease_owner' => 'old', 'lease_expires_at' => '2000-01-01']);
        $sent = [];
        self::assertSame(2, AiOutboxDispatcher::database(static function (array $payload) use (&$sent): bool { $sent[] = $payload; return true; })->dispatch());
        self::assertSame([7, 8], array_column($sent, 'taskId'));
    }

    public function test_old_owner_cannot_mark_taken_over_row_as_dispatched(): void
    {
        $this->pending(7);
        $dispatcher = AiOutboxDispatcher::database(static function (array $payload): bool {
            AiOutbox::where('id', 1)->update(['lease_owner' => 'new-owner']);
            return true;
        });
        self::assertSame(0, $dispatcher->dispatch());
        self::assertSame('processing', AiOutbox::firstOrFail()->status);
        self::assertSame('new-owner', AiOutbox::firstOrFail()->lease_owner);
    }
}
