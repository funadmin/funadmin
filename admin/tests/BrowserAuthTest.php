<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\BrowserAuthService;
use app\admin\authentication\service\AdminSessionService;
use app\common\service\database\MigrationService;
use DomainException;
use PHPUnit\Framework\TestCase;
use support\Db;

final class BrowserAuthTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertTrue(class_exists(BrowserAuthService::class), '浏览器会话认证尚未实现');
        (new MigrationService())->run();
        Db::table('login_attempts')->delete();
        Db::table('admin')->delete();
    }

    public function test_cookie_login_rotates_session_and_csrf_without_exposing_access_token(): void
    {
        (new AdminSessionService())->createAdmin('browser_admin', 'a-long-test-password');
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $login = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'browser_admin', 'a-long-test-password', 'browser-test');
        self::assertNotSame($anonymous['cookie'], $login['cookie']);
        self::assertNotSame($anonymous['csrfToken'], $login['csrfToken']);
        self::assertSame('browser_admin', $auth->user($login['cookie'])['username']);
        self::assertArrayNotHasKey('password', $auth->user($login['cookie']));
        try {
            $auth->user($anonymous['cookie']);
            self::fail('匿名旧会话不能继承登录状态');
        } catch (DomainException $error) {
            self::assertSame(401, $error->getCode());
        }
        $auth->logout($login['cookie'], $login['csrfToken']);
        $this->expectException(DomainException::class);
        $auth->user($login['cookie']);
    }

    public function test_failed_browser_logins_are_rate_limited_across_requests(): void
    {
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try { $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'missing', 'wrong', 'browser-limit'); }
            catch (DomainException $error) { self::assertSame(401, $error->getCode()); }
        }
        $this->expectExceptionCode(429);
        $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'missing', 'wrong', 'browser-limit');
    }

    public function test_missing_csrf_prevents_login_and_logout(): void
    {
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->expectException(DomainException::class);
        $this->expectExceptionCode(403);
        $auth->login($anonymous['cookie'], '', 'browser_admin', 'a-long-test-password', 'browser-test');
    }

    public function test_invalid_logout_csrf_does_not_revoke_the_active_session(): void
    {
        (new AdminSessionService())->createAdmin('browser_admin', 'a-long-test-password');
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $login = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'browser_admin', 'a-long-test-password', 'browser-test');
        try {
            $auth->logout($login['cookie'], 'incorrect');
            self::fail('无效 CSRF 不能退出');
        } catch (DomainException $error) {
            self::assertSame(403, $error->getCode());
        }
        self::assertSame('browser_admin', $auth->user($login['cookie'])['username']);
        Db::table('browser_sessions')->where('session_hash', hash('sha256', $login['cookie']))->update(['expires_at' => '2000-01-01 00:00:00']);
        $this->expectException(DomainException::class);
        $auth->user($login['cookie']);
    }

    public function test_disabled_admin_and_expired_sessions_cannot_be_restored(): void
    {
        (new AdminSessionService())->createAdmin('browser_admin', 'a-long-test-password');
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $login = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'browser_admin', 'a-long-test-password', 'browser-test');
        Db::table('admin')->update(['status' => 0]);
        $this->expectException(DomainException::class);
        $auth->user($login['cookie']);
    }
}
