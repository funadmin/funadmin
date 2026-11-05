<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\AdminSessionService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

final class LegacyAuthCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        $schema = Db::schema();
        $schema->dropIfExists('login_attempts');
        $schema->dropIfExists('admin');
        $schema->create('admin', static function (Blueprint $table): void {
            $table->id();
            $table->string('username', 20)->unique();
            $table->unsignedBigInteger('dept_id')->default(0);
            $table->string('password', 200);
            $table->string('email', 60)->nullable();
            $table->string('real_name', 50)->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('last_login_ip', 45)->default('');
            $table->string('token', 100)->nullable();
            $table->boolean('status')->default(true);
            $table->string('avatar', 255)->default('');
            $table->timestamps();
            $table->softDeletes();
        });
        $schema->create('login_attempts', static function (Blueprint $table): void {
            $table->string('key', 64)->primary();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedBigInteger('window_start');
        });
        $migration = require base_path() . '/database/migrations/005_admin_auth_tokens.php';
        $migration();
    }

    protected function tearDown(): void
    {
        Db::schema()->dropIfExists('admin_auth_tokens');
        Db::schema()->dropIfExists('login_attempts');
        Db::schema()->dropIfExists('admin');
    }

    public function test_authentication_reads_and_revokes_tokens_on_existing_admin_table(): void
    {
        $service = new AdminSessionService();
        $admin = $service->createAdmin('legacy_admin', 'a-long-test-password');

        self::assertSame('legacy_admin', $admin['username']);
        self::assertSame(1, Db::table('admin')->count());

        $result = $service->login('legacy_admin', 'a-long-test-password', '127.0.0.1');
        self::assertSame($admin['id'], $service->authenticate($result['access_token'])['id']);
        self::assertNull(Db::table('admin')->value('token'));
        self::assertSame(
            hash('sha256', $result['access_token']),
            Db::table('admin_auth_tokens')->where('admin_id', $admin['id'])->where('purpose', 'api')->value('token_hash')
        );

        $service->logout($result['access_token']);
        self::assertNull(Db::table('admin')->value('token'));
        $this->expectException(DomainException::class);
        $service->authenticate($result['access_token']);
    }
}
