<?php

declare(strict_types=1);

namespace Tests;

use app\common\service\install\InstallSupport;
use app\common\service\database\MigrationService;
use app\admin\authentication\service\AdminSessionService;
use app\admin\authentication\service\AdminLoginPath;
use app\admin\authorization\service\AuthorizationService;
use app\install\controller\Index;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use support\Db;
use support\Request;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
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
        $parsedTemplate = \Dotenv\Dotenv::parse(InstallSupport::renderEnv(base_path() . '/.env.example', null, $db, false));
        self::assertSame('false', $parsedTemplate['COOKIE_SECURE']);
    }

    public function test_install_no_longer_writes_a_list_confirmation_secret(): void
    {
        [$db] = $this->inputs();
        $key = 'FORM_LIST_CONFIRMATION_SECRET';
        // 确认密钥由运行时自动生成并保存在数据库，模板与安装生成的 .env 均不含该键；已有 .env 中的旧值原样保留、不再读取。
        self::assertStringNotContainsString($key, (string) file_get_contents(base_path() . '/.env.example'));
        self::assertStringNotContainsString($key, InstallSupport::renderEnv(base_path() . '/.env.example', null, $db, false));
        self::assertStringNotContainsString($key, InstallSupport::renderEnv(base_path() . '/.env.example', "CUSTOM=value\n", $db, false));
        self::assertStringContainsString("{$key}=legacy", InstallSupport::renderEnv(base_path() . '/.env.example', "CUSTOM=value\n{$key}=legacy\n", $db, false));
    }

    public function test_installer_only_accepts_mysql_and_rejects_other_drivers_before_connecting(): void
    {
        [$db, $admin] = $this->inputs();
        $db['driver'] = 'unsupported';
        self::assertNotSame('', InstallSupport::validate($db, $admin));
        $this->expectException(\DomainException::class);
        InstallSupport::databaseConfig($db, config('database'));
    }

    public function test_environment_requires_mysql_without_a_database_selector(): void
    {
        $checks = array_column((new \ReflectionMethod(Index::class, 'buildEnvironmentChecks'))->invoke(new Index()), null, 'key');
        self::assertTrue($checks['pdo_mysql']['required']);
        self::assertSame(['pdo_mysql'], array_values(array_filter(array_keys($checks), static fn (string $key): bool => str_starts_with($key, 'pdo_'))));
        self::assertSame(['mysql', 'sqlite'], array_keys(config('database.connections')));
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

    public function test_install_input_rejects_reserved_admin_login_entry(): void
    {
        [$db, $admin] = $this->inputs();
        self::assertNotSame('', InstallSupport::validate($db, $admin, 'auth'));
        self::assertSame('', InstallSupport::validate($db, $admin, 'secure-login'));
        self::assertSame('secure-login', AdminLoginPath::normalize('secure-login'));
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
        $root = sys_get_temp_dir() . '/funadmin-install-' . bin2hex(random_bytes(8));
        mkdir($root . '/public', 0700, true);
        $db = $this->inputs()[0];
        $content = InstallSupport::renderEnv(base_path() . '/.env.example', null, $db, false);
        file_put_contents($root . '/.env', $content);
        file_put_contents($root . '/public/install.lock', json_encode(['env_hash' => hash('sha256', $content)]));
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $previous = $manager->getDefaultConnection();
        try {
            InstallSupport::activateInstalledConfiguration($root);
            $name = $manager->getDefaultConnection();
            self::assertSame('installed_' . hash('sha256', $content), $name);
            $connection = \Illuminate\Container\Container::getInstance()['config']['database.connections'][$name];
            self::assertSame('install_test', $connection['database']);
            self::assertSame('custom_', $connection['prefix']);
            self::assertSame('', $connection['password']);
            self::assertSame('mysql', \app\common\service\database\DatabaseConnections::settings($name)['driver']);
            self::assertSame(['mysql'], \app\common\service\database\DatabaseConnections::allowed());
            self::assertSame('install_test', Db::connection('mysql')->getConfig('database'));
            $definition = \app\common\crud\CrudDefinition::fromInput(['connection' => $name, 'table' => 'example']);
            self::assertSame('custom_example', $definition->get('table'));
            InstallSupport::activateInstalledConfiguration($root);
            self::assertSame($name, $manager->getDefaultConnection());
        } finally {
            $manager->setDefaultConnection($previous);
            unlink($root . '/public/install.lock');
            unlink($root . '/.env');
            rmdir($root . '/public');
            rmdir($root);
        }
    }

    public function test_installation_lock_prevents_database_access_and_is_released(): void
    {
        $root = sys_get_temp_dir() . '/funadmin-install-' . bin2hex(random_bytes(8));
        mkdir($root . '/public', 0700, true);
        mkdir($root . '/runtime', 0700);
        file_put_contents($root . '/public/install.lock', '');
        try {
            foreach (range(1, 2) as $_) {
                try {
                    InstallSupport::install(...[...$this->inputs(), false, $root]);
                    self::fail('已安装时必须拒绝');
                } catch (\DomainException $error) {
                    self::assertSame(409, $error->getCode());
                    self::assertStringContainsString('系统已安装', $error->getMessage());
                }
            }
            $mutex = fopen($root . '/runtime/install.mutex', 'c');
            self::assertTrue(flock($mutex, LOCK_EX | LOCK_NB));
            try {
                InstallSupport::install(...[...$this->inputs(), false, $root]);
                self::fail('并发安装必须拒绝');
            } catch (\DomainException $error) {
                self::assertStringContainsString('正在执行', $error->getMessage());
            } finally {
                flock($mutex, LOCK_UN);
                fclose($mutex);
            }
        } finally {
            unlink($root . '/public/install.lock');
            unlink($root . '/runtime/install.mutex');
            rmdir($root . '/public');
            rmdir($root . '/runtime');
            rmdir($root);
        }
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function test_public_root_serves_the_spa_document(): void
    {
        \Webman\Route::load([config_path()]);
        foreach (['/'] as $path) {
            $routes = array_values(array_filter(\Webman\Route::getRoutes(), static fn ($route): bool => $route->getPath() === $path));
            self::assertCount(1, $routes, '网站根目录必须有显式路由：' . $path);
            $response = ($routes[0]->getCallback())(new Request("GET $path HTTP/1.1\r\n\r\n"));
            self::assertSame(200, $response->getStatusCode());
            self::assertStringContainsString('no-cache', (string) $response);
        }
    }

    public function test_installer_redirects_to_the_frontend_at_the_public_root(): void
    {
        $response = (new Index())->index(new Request("GET /install/index HTTP/1.1\r\n\r\n"));
        $page = is_file(public_path('install.lock')) ? 'login' : 'install';
        self::assertSame('/#/admin/' . $page, $response->getHeader('Location'));
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
            self::assertStringNotContainsString('Max-Age=0', (string) $response);
            self::assertStringContainsString('Path=/install; HttpOnly; SameSite=Strict', (string) $response);
            self::assertContains('migrations', array_column($data['checks'], 'key'));
            $response = $controller->step3(new Request("POST /install/index/step3 HTTP/1.1\r\nHost: localhost\r\n\r\n"));
            self::assertSame(403, $response->getStatusCode());
            $token = $data['csrfToken'];
            $request = new Request("POST /install/index/step3 HTTP/1.1\r\nHost: localhost\r\nCookie: funadmin_install_csrf=$token\r\nX-CSRF-TOKEN: $token\r\nContent-Type: application/json\r\nContent-Length: 2\r\n\r\n{}");
            $response = $controller->step3($request);
            self::assertSame(422, $response->getStatusCode());
            self::assertStringNotContainsString('安全校验失败', $response->rawBody());
        }
    }
}
