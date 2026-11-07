<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
use app\admin\authentication\controller\AdminAuth;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class AdminAuthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $schema = Db::schema();
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'admin'] as $table) {
            $schema->dropIfExists($table);
        }
        $schema->create('admin', static function (Blueprint $table): void {
            $table->id();
            $table->string('username', 20)->unique();
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
        $schema->create('browser_sessions', static function (Blueprint $table): void {
            $table->string('session_hash', 64)->primary();
            $table->string('csrf_token', 64);
            $table->dateTime('expires_at');
        });
        if (!Db::schema()->hasTable('admin_auth_tokens')) Db::schema()->create('admin_auth_tokens', static function (Blueprint $table): void {
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
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'admin'] as $table) {
            Db::schema()->dropIfExists($table);
        }
    }

    public function test_tp8_mirrored_admin_controller_handles_webman_login_request(): void
    {
        (new AdminSessionService())->createAdmin('web_admin', 'a-long-test-password');
        $session = (new BrowserAuthService())->bootstrap('');
        $body = json_encode(['username' => 'web_admin', 'password' => 'a-long-test-password']);
        $request = new Request("POST /admin/auth/login HTTP/1.1\r\nHost: localhost\r\nCookie: " . BrowserAuthService::COOKIE . '=' . $session['cookie'] . "\r\nX-CSRF-TOKEN: " . $session['csrfToken'] . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);

        $response = (new AdminAuth())->login($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['code' => 200, 'msg' => '登录成功', 'data' => ['authenticated' => true]], json_decode($response->rawBody(), true));
    }
}
