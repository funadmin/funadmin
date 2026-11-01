<?php

declare(strict_types=1);

namespace Tests\Console;

use app\admin\ai\repository\DatabaseAiProfileRepository;
use app\admin\ai\service\AiConfigurationProfileService;
use app\admin\ai\service\AiProfileSecret;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class AiProfileExecutionTest extends TestCase
{
    private bool $ready = false;

    protected function setUp(): void
    {
        self::assertSame('sqlite', Db::connection()->getDriverName());
        self::assertSame(':memory:', Db::connection()->getDatabaseName());
        $this->ready = true;
        Db::schema()->dropIfExists('ai_configuration_profile');
        Db::schema()->create('ai_configuration_profile', static function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('admin_id'); $table->string('name');
            $table->json('configuration'); $table->text('secret_ciphertext')->nullable();
            $table->boolean('is_default')->default(false); $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        if ($this->ready) Db::schema()->dropIfExists('ai_configuration_profile');
    }

    public function test_profile_snapshots_preserve_frozen_configuration_and_current_credentials(): void
    {
        $service = new AiConfigurationProfileService(new DatabaseAiProfileRepository(), new AiProfileSecret(\Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString()));
        $profile = $service->create(7, ['name' => '本地配置', 'provider' => 'local', 'protocol' => 'openai-chat', 'base_url' => 'https://example.test/v1', 'model' => 'local-model', 'api_key' => 'test-api-key']);
        self::assertTrue($profile['has_api_key']);
        self::assertArrayNotHasKey('secret_ciphertext', $profile);
        $snapshot = $service->snapshot(7, $profile['id']);
        self::assertArrayNotHasKey('api_key', $snapshot['configuration']);
        self::assertSame('test-api-key', $service->resolveSnapshot(7, $snapshot)['api_key']);
        $service->update(7, $profile['id'], ['model' => 'new-model', 'api_key' => 'replacement-key']);
        self::assertSame('local-model', $service->resolveSnapshot(7, $snapshot)['model']);
        self::assertSame('replacement-key', $service->resolveSnapshot(7, $snapshot)['api_key']);
        $this->expectExceptionMessage('档案不存在');
        $service->resolveSnapshot(8, $snapshot);
    }

    public function test_profile_cannot_reuse_credentials_for_changed_target(): void
    {
        $service = new AiConfigurationProfileService(new DatabaseAiProfileRepository(), new AiProfileSecret(\Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString()));
        $profile = $service->create(7, ['name' => '本地配置', 'provider' => 'local', 'protocol' => 'openai-chat', 'base_url' => 'https://example.test/v1', 'model' => 'local-model', 'api_key' => 'test-api-key']);
        $snapshot = $service->snapshot(7, $profile['id']);
        $service->update(7, $profile['id'], ['base_url' => 'https://other.example.test/v1', 'api_key' => 'other-key']);
        $this->expectExceptionMessage('档案可信目标已变更');
        $service->resolveSnapshot(7, $snapshot);
    }
}
