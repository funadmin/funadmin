<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\AdminSessionService;
use DomainException;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;

/** 管理员令牌只存放在 admin_auth_tokens（哈希），并绑定签发时的密码：改密或登出后失效。 */
final class AdminSessionTokenTest extends TestCase
{
    protected function setUp(): void
    {
        $schema = Db::schema();
        $schema->dropIfExists('login_attempts');
        $schema->dropIfExists('admin_auth_tokens');
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
        $schema->create('admin_auth_tokens', static function (Blueprint $table): void {
            $table->string('token_hash', 64)->primary();
            $table->unsignedBigInteger('admin_id')->index();
            $table->string('purpose', 16);
            $table->string('credential_version', 64);
            $table->dateTime('expires_at')->index();
            $table->dateTime('created_at');
        });
    }

    protected function tearDown(): void
    {
        Db::schema()->dropIfExists('admin_auth_tokens');
        Db::schema()->dropIfExists('login_attempts');
        Db::schema()->dropIfExists('admin');
    }

    public function test_tokens_are_stored_hashed_and_revoked_on_logout(): void
    {
        $service = new AdminSessionService();
        $admin = $service->createAdmin('session_admin', 'a-long-test-password');
        $result = $service->login('session_admin', 'a-long-test-password', '127.0.0.1');

        self::assertSame($admin['id'], $service->authenticate($result['access_token'])['id']);
        self::assertArrayNotHasKey('token', $result['admin']);
        self::assertSame(
            hash('sha256', $result['access_token']),
            Db::table('admin_auth_tokens')->where('admin_id', $admin['id'])->where('purpose', 'api')->value('token_hash')
        );

        $service->logout($result['access_token']);
        $this->expectException(DomainException::class);
        $service->authenticate($result['access_token']);
    }

    public function test_password_change_invalidates_issued_tokens(): void
    {
        $service = new AdminSessionService();
        $admin = $service->createAdmin('session_admin', 'a-long-test-password');
        $token = $service->login('session_admin', 'a-long-test-password', '127.0.0.1')['access_token'];

        Db::table('admin')->where('id', $admin['id'])->update(['password' => password_hash('another-long-password', PASSWORD_DEFAULT)]);
        $this->expectException(DomainException::class);
        $service->authenticate($token);
    }
}
