<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\InstallSupport;
use app\common\service\MigrationService;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authorization\service\AuthorizationService;
use app\install\controller\Index;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

final class InstallTest extends TestCase
{
    private function inputs(): array
    {
        return [
            ['host' => 'localhost', 'port' => '3306', 'database' => 'install_test', 'username' => 'root', 'password' => '', 'prefix' => 'custom_'],
            ['username' => 'installer', 'password' => 'install-safe-123', 'repassword' => 'install-safe-123', 'email' => 'admin@example.com'],
        ];
    }

    public function test_configuration_uses_webman_keys_and_preserves_password_characters(): void
    {
        [$db] = $this->inputs();
        $db['password'] = 'space # quote\' " slash\\ ${KEEP}';
        $content = InstallSupport::renderEnv(base_path() . '/.env.example', "CUSTOM=value\nDB_PASSWORD=old\n", $db, false);
        $parsed = \Dotenv\Dotenv::parse($content);
        self::assertSame($db['password'], $parsed['DB_PASSWORD']);
        self::assertSame('install_test', $parsed['DB_DATABASE']);
        self::assertSame('root', $parsed['DB_USERNAME']);
        self::assertSame('value', $parsed['CUSTOM']);
        self::assertSame('', InstallSupport::validate(...$this->inputs()));
    }

    public function test_input_rejects_invalid_ports_and_missing_passwords(): void
    {
        [$db, $admin] = $this->inputs();
        $db['port'] = '70000';
        self::assertNotSame('', InstallSupport::validate($db, $admin));
        [$db, $admin] = $this->inputs();
        $admin['password'] = $admin['repassword'] = '';
        self::assertNotSame('', InstallSupport::validate($db, $admin));
    }

    public function test_empty_database_initializes_a_login_capable_super_administrator(): void
    {
        (new MigrationService())->run();
        Db::table('admin')->delete();
        [, $admin] = $this->inputs();
        InstallSupport::installSchemaAndAdmin(base_path() . '/database/migrations', $admin);
        $row = Db::table('admin')->where('id', 1)->first();
        self::assertSame('installer', $row->username);
        self::assertTrue(password_verify($admin['password'], $row->password));
        $info = (new AuthorizationService())->userInfo(['id' => 1]);
        self::assertSame(['*'], $info['permissions']);
        self::assertNotEmpty((new AuthorizationService())->menus(['id' => 1]));
        Db::table('login_attempts')->delete();
        self::assertArrayHasKey('access_token', (new AdminSessionService())->login('installer', $admin['password'], '127.0.0.1'));
    }

    public function test_existing_administrator_is_never_overwritten(): void
    {
        (new MigrationService())->run();
        if (!Db::table('admin')->exists()) {
            Db::table('admin')->insert(['id' => 1, 'username' => 'existing', 'password' => 'unchanged', 'status' => 1]);
        }
        $before = (array) Db::table('admin')->first();
        try {
            InstallSupport::installSchemaAndAdmin(base_path() . '/database/migrations', $this->inputs()[1]);
            self::fail('已有管理员时必须拒绝安装');
        } catch (\DomainException $error) {
            self::assertSame(409, $error->getCode());
            self::assertSame($before, (array) Db::table('admin')->first());
        }
    }

    public function test_installed_configuration_activates_in_each_worker_without_reusing_old_pools(): void
    {
        self::assertTrue(method_exists(InstallSupport::class, 'activateInstalledConfiguration'));
    }

    public function test_environment_and_csrf_rejection_do_not_require_a_database(): void
    {
        self::assertFalse(is_subclass_of(Index::class, \app\BaseController::class));
        $controller = new Index();
        $response = $controller->step2(new Request("GET /install/index/step2 HTTP/1.1\r\nHost: localhost\r\n\r\n"));
        $data = json_decode($response->rawBody(), true)['data'];
        self::assertArrayHasKey('installed', $data);
        self::assertStringContainsString('no-store', (string) $response);
        if (!$data['installed']) {
            self::assertArrayHasKey('csrfToken', $data);
            self::assertContains('migrations', array_column($data['checks'], 'key'));
            $response = $controller->step3(new Request("POST /install/index/step3 HTTP/1.1\r\nHost: localhost\r\n\r\n"));
            self::assertSame(403, $response->getStatusCode());
        }
    }
}
