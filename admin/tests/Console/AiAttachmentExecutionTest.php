<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\repository\DatabaseAiAttachmentRepository;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class AiAttachmentExecutionTest extends TestCase
{
    private bool $ready = false;

    protected function setUp(): void
    {
        self::assertSame('sqlite', Db::connection()->getDriverName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->ready = true;
        foreach (['ai_conversation', 'ai_attachment'] as $name) {
            Db::schema()->dropIfExists($name);
            Db::schema()->create($name, static function (Blueprint $table) use ($name): void {
                $table->id(); $table->unsignedBigInteger('admin_id'); $table->timestamps(); $table->softDeletes();
                if ($name === 'ai_attachment') {
                    $table->unsignedBigInteger('conversation_id'); $table->unsignedBigInteger('message_id')->nullable();
                    $table->string('storage_path')->unique(); $table->dateTime('expires_at');
                }
            });
        }
        Db::table('ai_conversation')->insert(['id' => 1, 'admin_id' => 7]);
    }

    protected function tearDown(): void
    {
        if ($this->ready) foreach (['ai_attachment', 'ai_conversation'] as $name) Db::schema()->dropIfExists($name);
    }

    public function test_attachment_ownership_binding_and_expiry_remain_transactional(): void
    {
        $repository = new DatabaseAiAttachmentRepository();
        $row = $repository->create(['conversation_id' => 1, 'admin_id' => 7, 'message_id' => null, 'storage_path' => 'attachment-one.txt', 'expires_at' => '2099-01-01']);
        self::assertSame($row['id'], $repository->owned(1, 7, $row['id'], time())['id']);
        try { $repository->owned(1, 8, $row['id'], time()); self::fail('跨管理员访问应拒绝'); }
        catch (\RuntimeException $error) { self::assertSame(404, $error->getCode()); }
        $repository->bind($row['id'], 9);
        self::assertFalse($repository->deleteDraft($row['id']));
        $removed = false;
        self::assertFalse($repository->removeExpiredFile('attachment-one.txt', strtotime('2100-01-01'), static function () use (&$removed): bool { $removed = true; return true; }));
        self::assertFalse($removed);
        self::assertSame(9, (int) Db::table('ai_attachment')->value('message_id'));
    }

    public function test_deleted_draft_is_unreadable_and_only_expired_unbound_files_can_be_removed(): void
    {
        $repository = new DatabaseAiAttachmentRepository();
        $row = $repository->create(['conversation_id' => 1, 'admin_id' => 7, 'message_id' => null, 'storage_path' => 'attachment-two.txt', 'expires_at' => '2000-01-01']);
        self::assertTrue($repository->deleteDraft($row['id']));
        self::assertTrue($repository->removeExpiredFile('attachment-two.txt', time(), static fn (): bool => true));
        $this->expectExceptionMessage('资源不存在');
        $repository->owned(1, 7, $row['id'], time());
    }
}
