<?php

declare(strict_types=1);

namespace app\install\controller;

use app\common\service\install\InstallSupport;
use DomainException;
use support\Request;
use support\Response;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;

/** 不依赖数据库和后台认证的安装向导。 */
#[RouteGroup('/install/index')]
final class Index
{
    private const COOKIE = 'funadmin_install_csrf';

    #[Get('')]
    public function index(Request $request): Response
    {
        return redirect('/#/admin/' . ($this->installed() ? 'login' : 'install'));
    }

    #[Get('/step2')]
    public function step2(Request $request): Response
    {
        if ($this->installed()) return $this->result(['installed' => true]);
        $token = $request->cookie(self::COOKIE, '');
        if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) $token = bin2hex(random_bytes(32));
        return $this->result([
            'installed' => false, 'siteName' => 'FunAdmin', 'siteVersion' => 'Webman',
            'csrfToken' => $token, 'checks' => $this->buildEnvironmentChecks(),
        ], '环境检测完成')->cookie(self::COOKIE, $token, null, '/install', '', $this->secure(), true, 'Strict');
    }

    #[Post('/step3')]
    public function step3(Request $request): Response
    {
        try {
            if ($this->installed()) throw new DomainException('系统已安装，禁止重复安装', 409);
            $this->assertCsrf($request);
            $input = $this->collectInstallInput($request);
            foreach ($this->buildEnvironmentChecks() as $check) {
                if ($check['required'] && !$check['passed']) throw new DomainException($check['label'] . '不满足安装要求', 422);
            }
            $result = InstallSupport::install($input['db'], $input['admin'], $input['debug'], base_path());
            return $this->result($result, '安装成功');
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        } catch (\Throwable) {
            return $this->result(null, '安装失败，请检查数据库权限、迁移文件及安装目录写入权限', 422);
        }
    }

    #[Post('/step4')]
    public function step4(Request $request): Response
    {
        try {
            $this->assertCsrf($request);
            if (!$this->installed()) throw new DomainException('系统尚未完成安装', 409);
            return $this->result(['installed' => true, 'backend' => '/#/admin/login'], '安装完成')
                ->cookie(self::COOKIE, '', 0, '/install', '', $this->secure(), true, 'Strict');
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        }
    }

    private function assertCsrf(Request $request): void
    {
        $cookie = $request->cookie(self::COOKIE, '');
        $header = $request->header('x-csrf-token', '');
        if (!is_string($cookie) || !is_string($header) || !preg_match('/\A[a-f0-9]{64}\z/', $cookie) || !hash_equals($cookie, $header)) {
            throw new DomainException('安装安全校验失败，请刷新安装页面后重试', 403);
        }
    }

    private function secure(): bool
    {
        return filter_var(getenv('COOKIE_SECURE') ?: 'false', FILTER_VALIDATE_BOOL);
    }

    private function installed(): bool
    {
        $lock = InstallSupport::paths(base_path())['lock'];
        clearstatcache(true, $lock);
        return is_file($lock);
    }

    private function result(mixed $data, string $msg = '成功', int $code = 200): Response
    {
        return json(compact('code', 'msg', 'data'))->withStatus($code)
            ->withHeaders(['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function collectInstallInput(Request $request): array
    {
        $values = [];
        foreach (['hostname', 'port', 'database', 'prefix', 'username', 'password', 'adminUserName', 'adminPassword', 'rePassword', 'email'] as $key) {
            $value = $request->post($key, '');
            if (!is_string($value)) throw new DomainException('安装配置必须为字符串', 422);
            $values[$key] = $value;
        }
        $debug = filter_var($request->post('app_debug', false), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($debug === null) throw new DomainException('开发模式参数不正确', 422);
        $driver = $request->post('driver', 'mysql');
        if (!is_string($driver) || $driver !== 'mysql') throw new DomainException('数据库类型仅支持 MySQL', 422);
        return [
            'db' => ['driver' => $driver, 'host' => $values['hostname'], 'port' => $values['port'], 'database' => $values['database'], 'prefix' => $values['prefix'], 'username' => $values['username'], 'password' => $values['password']],
            'admin' => ['username' => $values['adminUserName'], 'password' => $values['adminPassword'], 'repassword' => $values['rePassword'], 'email' => $values['email']],
            'debug' => $debug,
        ];
    }

    private function buildEnvironmentChecks(): array
    {
        $paths = InstallSupport::paths(base_path());
        $directory = $paths['migrations'];
        $files = glob($directory . '/*.sql') ?: [];
        $migrations = $files !== [] && is_readable($directory . '/001_baseline.sql');
        foreach ($files as $file) {
            if (!is_readable($file)) $migrations = false;
        }
        $upload = (string) ini_get('upload_max_filesize');
        $bytes = (float) $upload * match (strtolower(substr($upload, -1))) { 'g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024, default => 1 };
        return [
            $this->environmentCheck('os', '操作系统', '不限', PHP_OS_FAMILY, true, true),
            $this->environmentCheck('php', 'PHP 版本', '>= 8.5.0', PHP_VERSION, version_compare(PHP_VERSION, '8.5.0', '>='), true),
            $this->environmentCheck('php_int', 'PHP 架构', '64 位', (string) (PHP_INT_SIZE * 8) . ' 位', PHP_INT_SIZE === 8, true),
            $this->extensionCheck('json', 'JSON', true),
            $this->extensionCheck('pdo', 'PDO', true),
            $this->extensionCheck('pdo_mysql', 'PDO MySQL', true),
            $this->extensionCheck('pcntl', 'PCNTL', PHP_OS_FAMILY !== 'Windows'),
            $this->extensionCheck('mbstring', 'Mbstring', true),
            $this->extensionCheck('openssl', 'OpenSSL', true),
            $this->extensionCheck('curl', 'CURL', false),
            $this->extensionCheck('fileinfo', 'Fileinfo', false),
            $this->environmentCheck('image', '图像扩展', 'GD 或 Imagick（可选）', extension_loaded('gd') ? 'GD' : (extension_loaded('imagick') ? 'Imagick' : '未安装'), extension_loaded('gd') || extension_loaded('imagick'), false),
            $this->extensionCheck('zip', 'ZIP', false),
            $this->environmentCheck('upload', '文件上传限制', '>= 10 MB（可选）', $upload, $bytes >= 10 * 1024 * 1024, false),
            $this->environmentCheck('runtime', 'runtime 目录', '可写', $this->writableLabel($paths['runtime']), is_writable($paths['runtime']), true),
            $this->environmentCheck('public', 'public 目录', '可写', $this->writableLabel($paths['public']), is_writable($paths['public']), true),
            $this->environmentCheck('env', '.env 配置目录', '可原子写入', $this->writableLabel(base_path()), is_writable(base_path()) && (!is_file($paths['env']) || is_writable($paths['env'])), true),
            $this->environmentCheck('migrations', '数据库迁移', '根目录核心 SQL 可读', $files ? count($files) . ' 个迁移文件' : '无迁移文件', $migrations, true),
        ];
    }

    private function extensionCheck(string $key, string $label, bool $required): array
    {
        $passed = extension_loaded($key);
        return $this->environmentCheck($key, $label . ' 扩展', $required ? '支持' : '支持（可选）', $passed ? '支持' : '未安装', $passed, $required);
    }

    private function environmentCheck(string $key, string $label, string $requiredValue, string $currentValue, bool $passed, bool $required): array
    {
        return compact('key', 'label', 'requiredValue', 'currentValue', 'passed', 'required');
    }

    private function writableLabel(string $path): string
    {
        return is_writable($path) ? '可写' : '不可写';
    }
}
