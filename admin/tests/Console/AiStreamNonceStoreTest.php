<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\repository\DatabaseAiConversationStore;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

/** SSE ticket nonce 只能消费一次；表结构与 MySQL 基线一致（无 updated_at）。 */
final class AiStreamNonceStoreTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        Db::schema()->dropIfExists('ai_stream_nonce');
        Db::schema()->create('ai_stream_nonce', static function (Blueprint $table): void {
            $table->id(); $table->char('nonce_hash', 64)->unique(); $table->dateTime('expires_at');
            $table->dateTime('consumed_at'); $table->dateTime('created_at');
        });
    }

    protected function tearDown(): void
    {
        Db::schema()->dropIfExists('ai_stream_nonce');
    }

    public function test_nonce_is_consumed_exactly_once_against_baseline_schema(): void
    {
        $store = new DatabaseAiConversationStore();
        self::assertTrue($store->consumeNonce('nonce-1', time() + 60), 'first use of a fresh SSE nonce must succeed');
        self::assertFalse($store->consumeNonce('nonce-1', time() + 60), 'replayed SSE nonce must be rejected');
        self::assertTrue($store->consumeNonce('nonce-2', time() + 60));
        $row = Db::table('ai_stream_nonce')->where('nonce_hash', hash('sha256', 'nonce-1'))->first();
        self::assertNotNull($row);
        self::assertNotNull($row->created_at);
    }
}
