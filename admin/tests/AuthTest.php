<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\service\AdminSessionService as AuthService;
use app\common\service\database\MigrationService;
use DomainException;
use PHPUnit\Framework\TestCase;
use support\Db;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        self::assertTrue(class_exists(AuthService::class), '认证服务尚未实现');
        (new MigrationService())->run();
        Db::table('admin_auth_tokens')->delete();
        Db::table('login_attempts')->delete();
        Db::table('admin')->delete();
    }

    public function test_login_stores_only_token_hash_and_logout_revokes_it(): void
    {
        $service = new AuthService();
        $admin = $service->createAdmin('operator', 'a-long-test-password');
        $result = $service->login('operator', 'a-long-test-password', '127.0.0.1');
        self::assertSame($admin['id'], $service->authenticate($result['access_token'])['id']);
        self::assertSame(hash('sha256', $result['access_token']), Db::table('admin_auth_tokens')->value('token_hash'));
        self::assertArrayNotHasKey('password', $admin);
        $service->logout($result['access_token']);
        $this->expectException(DomainException::class);
        $service->authenticate($result['access_token']);
    }

    public function test_wrong_password_creates_no_token(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        try {
            $service->login('operator', 'incorrect', '127.0.0.2');
            self::fail('错误密码不应登录成功');
        } catch (DomainException $error) {
            self::assertSame(401, $error->getCode());
        }
        self::assertSame(0, Db::table('admin_auth_tokens')->count());
    }

    public function test_expired_token_and_disabled_admin_are_rejected(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        $token = $service->login('operator', 'a-long-test-password', '127.0.0.3')['access_token'];
        Db::table('admin_auth_tokens')->update(['expires_at' => '2000-01-01 00:00:00']);
        try {
            $service->authenticate($token);
            self::fail('过期令牌不应认证成功');
        } catch (DomainException $error) {
            self::assertSame(401, $error->getCode());
        }
        $token = $service->login('operator', 'a-long-test-password', '127.0.0.3')['access_token'];
        Db::table('admin')->update(['status' => false]);
        $this->expectException(DomainException::class);
        $service->authenticate($token);
    }

    public function test_login_attempts_are_shared_and_limited(): void
    {
        $service = new AuthService();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $service->login('missing', 'wrong', '127.0.0.4');
            } catch (DomainException $error) {
                self::assertSame(401, $error->getCode());
            }
        }
        try {
            (new AuthService())->login('another', 'wrong', '127.0.0.4');
            self::fail('同一 IP 必须跨账号限流');
        } catch (DomainException $error) {
            self::assertSame(429, $error->getCode());
        }
    }

    public function test_successful_logins_are_not_counted_against_the_limit(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        for ($login = 0; $login < 8; $login++) {
            self::assertNotEmpty($service->login('operator', 'a-long-test-password', '127.0.0.8')['access_token']);
        }
    }

    public function test_successful_login_clears_earlier_failures(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            try { $service->login('operator', 'wrong', '127.0.0.9'); } catch (DomainException) {}
        }
        $service->login('operator', 'a-long-test-password', '127.0.0.9');
        for ($attempt = 0; $attempt < 4; $attempt++) {
            try { $service->login('operator', 'wrong', '127.0.0.9'); } catch (DomainException $error) { self::assertSame(401, $error->getCode()); }
        }
        self::assertNotEmpty($service->login('operator', 'a-long-test-password', '127.0.0.9')['access_token']);
    }

    public function test_correct_password_is_still_refused_once_locked(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try { $service->login('operator', 'wrong', '127.0.0.10'); } catch (DomainException) {}
        }
        try {
            $service->login('operator', 'a-long-test-password', '127.0.0.10');
            self::fail('锁定期间正确密码也不能登录，否则可被用来验证猜中的密码');
        } catch (DomainException $error) {
            self::assertSame(429, $error->getCode());
        }
    }

    public function test_duplicate_admin_does_not_reset_password(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        try {
            $service->createAdmin('operator', 'different-test-password');
            self::fail('重复管理员不能覆盖现有密码');
        } catch (DomainException $error) {
            self::assertSame(409, $error->getCode());
        }
        self::assertNotEmpty($service->login('operator', 'a-long-test-password', '127.0.0.5')['access_token']);
    }

    public function test_passwords_over_bcrypt_limit_are_rejected(): void
    {
        $this->expectException(DomainException::class);
        (new AuthService())->createAdmin('operator', str_repeat('a', 73));
    }

    public function test_rate_limit_window_expires(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        Db::table('login_attempts')->insert([
            'key' => hash('sha256', '127.0.0.7'), 'attempts' => 5, 'window_start' => time() - 901,
        ]);
        self::assertNotEmpty($service->login('operator', 'a-long-test-password', '127.0.0.7')['access_token']);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        (new AuthService())->authenticate('invalid');
    }

    public function test_browser_and_api_credentials_are_isolated_and_expire_independently(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        $browser = new \app\admin\authentication\service\BrowserAuthService();
        $anonymous = $browser->bootstrap('');
        $session = $browser->login($anonymous['cookie'], $anonymous['csrfToken'], 'operator', 'a-long-test-password', 'browser');
        $api = $service->login('operator', 'a-long-test-password', 'api')['access_token'];
        self::assertSame('operator', $browser->user($session['cookie'])['username']);
        self::assertSame('operator', $service->authenticate($api)['username']);
        try {
            $service->authenticate($session['cookie']);
            self::fail('浏览器凭据不能用于 Bearer API');
        } catch (DomainException $error) {
            self::assertSame(401, $error->getCode());
        }
        $service->logout($api);
        self::assertSame('operator', $browser->user($session['cookie'])['username']);
    }

    public function test_password_change_outside_authentication_invalidates_old_credentials(): void
    {
        $service = new AuthService();
        $service->createAdmin('operator', 'a-long-test-password');
        $token = $service->login('operator', 'a-long-test-password', 'password-change')['access_token'];
        Db::table('admin')->update(['password' => password_hash('a-different-test-password', PASSWORD_DEFAULT)]);
        $this->expectExceptionCode(401);
        $service->authenticate($token);
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->expectException(DomainException::class);
        (new AuthService())->createAdmin('operator', 'short');
    }
}
