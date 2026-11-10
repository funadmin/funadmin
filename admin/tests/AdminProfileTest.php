<?php

declare(strict_types=1);

namespace Tests;

use app\admin\authentication\controller\AdminProfile;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\BrowserAuthService;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

#[\PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses]
#[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
final class AdminProfileTest extends TestCase
{
    private array $session;
    private int $adminId;

    protected function setUp(): void
    {
        \Tests\Support\TestDatabase::migrate();
        foreach (['admin_auth_tokens', 'browser_sessions', 'login_attempts', 'admin'] as $table) Db::table($table)->delete();
        $admin = (new AdminSessionService())->createAdmin('profile_admin', 'profile-safe-password');
        $this->adminId = $admin['id'];
        $auth = new BrowserAuthService();
        $anonymous = $auth->bootstrap('');
        $this->session = $auth->login($anonymous['cookie'], $anonymous['csrfToken'], 'profile_admin', 'profile-safe-password', 'profile-test');
    }

    private function request(string $method, array $data = [], bool $csrf = true): Request
    {
        $body = json_encode($data);
        return new Request("$method /admin/profile HTTP/1.1\r\nHost: localhost\r\nCookie: " . BrowserAuthService::COOKIE . '=' . $this->session['cookie'] .
            ($csrf ? "\r\nX-CSRF-TOKEN: " . $this->session['csrfToken'] : '') . "\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);
    }

    public function test_profile_is_native_webman_and_requires_a_browser_identity(): void
    {
        self::assertFalse(is_subclass_of(AdminProfile::class, \app\common\controller\AdminController::class));
        $controller = new AdminProfile();
        self::assertSame(401, $controller->index(new Request("GET /admin/profile HTTP/1.1\r\n\r\n"))->getStatusCode());
        $response = $controller->index($this->request('GET'));
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->rawBody(), true)['data'];
        self::assertSame('profile_admin', $data['username']);
        self::assertArrayNotHasKey('password', $data);
        self::assertArrayNotHasKey('token', $data);
    }

    public function test_profile_update_is_whitelisted_validated_and_csrf_protected(): void
    {
        self::assertFalse(is_subclass_of(AdminProfile::class, \app\common\controller\AdminController::class));
        $controller = new AdminProfile();
        self::assertSame(403, $controller->update($this->request('PUT', ['nickname' => '测试'], false))->getStatusCode());
        self::assertSame(422, $controller->update($this->request('PUT', ['email' => str_repeat('x', 65) . '@test.com']))->getStatusCode());
        self::assertSame(422, $controller->update($this->request('PUT', ['nickname' => []]))->getStatusCode());
        self::assertSame(422, $controller->update($this->request('PUT', ['avatar' => 'javascript:alert(1)']))->getStatusCode());
        $response = $controller->update($this->request('PUT', ['nickname' => '测试管理员', 'email' => 'profile@example.com', 'status' => 0, 'id' => 100, 'password' => 'forbidden']));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('测试管理员', json_decode($response->rawBody(), true)['data']['nickname']);
        self::assertSame(1, (int) Db::table('admin')->where('id', $this->adminId)->value('status'));
        self::assertTrue(password_verify('profile-safe-password', Db::table('admin')->where('id', $this->adminId)->value('password')));
    }

    public function test_password_change_revokes_all_credentials_without_changing_other_accounts(): void
    {
        self::assertFalse(is_subclass_of(AdminProfile::class, \app\common\controller\AdminController::class));
        $service = new AdminSessionService();
        $api = $service->login('profile_admin', 'profile-safe-password', 'api-test')['access_token'];
        $service->createAdmin('another_admin', 'another-safe-password');
        $other = $service->login('another_admin', 'another-safe-password', 'other-test')['access_token'];
        $controller = new AdminProfile();
        self::assertSame(422, $controller->password($this->request('POST', ['oldPassword' => 'wrong', 'newPassword' => 'new-safe-password']))->getStatusCode());
        self::assertSame(422, $controller->password($this->request('POST', ['oldPassword' => 'profile-safe-password', 'newPassword' => str_repeat('x', 73)]))->getStatusCode());
        $response = $controller->password($this->request('POST', ['oldPassword' => 'profile-safe-password', 'newPassword' => 'new-safe-password']));
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Max-Age=0', (string) $response);
        self::assertSame(401, $controller->index($this->request('GET'))->getStatusCode());
        self::assertSame('another_admin', $service->authenticate($other)['username']);
        try {
            $service->authenticate($api);
            self::fail('旧 API Token 必须撤销');
        } catch (\DomainException $error) { self::assertSame(401, $error->getCode()); }
        self::assertNotEmpty($service->login('profile_admin', 'new-safe-password', 'new-test')['access_token']);
    }
}
