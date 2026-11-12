<?php

declare(strict_types=1);

namespace app\install\controller;

use OpenApi\Annotations as OA;

use app\admin\authentication\service\AdminLoginPath;
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

    /**
     * 安装入口：已安装时跳转后台登录页，否则跳转前端安装向导。
     *
     * @OA\Get(
     *     path="/install/index",
     *     operationId="install_controller_Index_index_get",
     *     tags={"安装向导"},
     *     summary="安装入口：已安装时跳转后台登录页，否则跳转前端安装向导。",
     *     security={},
     *     @OA\Response(response=302, description="跳转到后台登录页或安装向导页面。", @OA\Header(header="Location", description="跳转地址。", @OA\Schema(type="string")))
     * )
     */
    #[Get('')]
    public function index(Request $request): Response
    {
        return redirect($this->installed() ? $this->adminLoginUrl() : '/#/admin/install');
    }

    /**
     * 环境检测：返回检测结果和安装 CSRF 令牌，并设置安装 Cookie；已安装时只返回后台地址。
     *
     * @OA\Get(
     *     path="/install/index/step2",
     *     operationId="install_controller_Index_step2_get",
     *     tags={"安装向导"},
     *     summary="环境检测：返回检测结果和安装 CSRF 令牌。",
     *     description="安装协议：code 与 HTTP 状态一致，成功为 200，响应体不含 time。响应设置 funadmin_install_csrf Cookie（Path=/install），后续步骤需在 X-CSRF-TOKEN 请求头回传同一令牌。",
     *     security={},
     *     @OA\Response(
     *         response=200,
     *         description="检测结果；data.installed=true 时只含 backend。",
     *         @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope"),
     *         @OA\Header(header="Set-Cookie", description="funadmin_install_csrf 安装令牌 Cookie。", @OA\Schema(type="string"))
     *     ),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Get('/step2')]
    public function step2(Request $request): Response
    {
        if ($this->installed()) return $this->result(['installed' => true, 'backend' => $this->adminLoginUrl()]);
        $token = $request->cookie(self::COOKIE, '');
        if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/', $token)) $token = bin2hex(random_bytes(32));
        return $this->result([
            'installed' => false, 'siteName' => 'FunAdmin', 'siteVersion' => 'Webman',
            'csrfToken' => $token, 'checks' => $this->buildEnvironmentChecks(),
        ], '环境检测完成')->cookie(self::COOKIE, $token, null, '/install', '', $this->secure(), true, 'Strict');
    }

    /**
     * 执行安装：校验安装令牌与环境要求后写入数据库、管理员和配置。
     *
     * @OA\Post(
     *     path="/install/index/step3",
     *     operationId="install_controller_Index_step3_post",
     *     tags={"安装向导"},
     *     summary="执行安装：写入数据库、管理员账号和配置。",
     *     description="安装协议：code 与 HTTP 状态一致。需携带 funadmin_install_csrf Cookie 并在 X-CSRF-TOKEN 请求头回传同一令牌；已安装返回 409，令牌不符返回 403，参数或环境不满足返回 422。",
     *     security={},
     *     @OA\Parameter(name="X-CSRF-TOKEN", in="header", required=true, description="step2 返回的安装令牌。", @OA\Schema(type="string", pattern="^[a-f0-9]{64}$")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"hostname", "port", "database", "username", "adminUserName", "adminPassword", "rePassword"},
     *                 @OA\Property(property="driver", type="string", enum={"mysql"}, default="mysql"),
     *                 @OA\Property(property="hostname", type="string"),
     *                 @OA\Property(property="port", type="string"),
     *                 @OA\Property(property="database", type="string"),
     *                 @OA\Property(property="prefix", type="string"),
     *                 @OA\Property(property="username", type="string"),
     *                 @OA\Property(property="password", type="string", writeOnly=true),
     *                 @OA\Property(property="adminUserName", type="string"),
     *                 @OA\Property(property="adminPassword", type="string", writeOnly=true),
     *                 @OA\Property(property="rePassword", type="string", writeOnly=true),
     *                 @OA\Property(property="email", type="string"),
     *                 @OA\Property(property="adminLoginPath", type="string", description="后台登录入口，留空使用默认值。"),
     *                 @OA\Property(property="app_debug", type="boolean", default=false)
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="安装成功。", @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
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
            $result = InstallSupport::install($input['db'], $input['admin'], $input['debug'], base_path(), '5.7', null, $input['adminLoginPath']);
            return $this->result($result, '安装成功');
        } catch (DomainException $error) {
            return $this->result(null, $error->getMessage(), $error->getCode());
        } catch (\Throwable) {
            return $this->result(null, '安装失败，请检查数据库权限、迁移文件及安装目录写入权限', 422);
        }
    }

    /**
     * 完成安装：确认已安装后返回后台地址并清除安装 Cookie。
     *
     * @OA\Post(
     *     path="/install/index/step4",
     *     operationId="install_controller_Index_step4_post",
     *     tags={"安装向导"},
     *     summary="完成安装：返回后台地址并清除安装 Cookie。",
     *     description="安装协议：code 与 HTTP 状态一致。令牌不符返回 403，尚未安装返回 409。",
     *     security={},
     *     @OA\Parameter(name="X-CSRF-TOKEN", in="header", required=true, description="step2 返回的安装令牌。", @OA\Schema(type="string", pattern="^[a-f0-9]{64}$")),
     *     @OA\Response(response=200, description="安装完成。", @OA\JsonContent(ref="#/components/schemas/BrowserAuthEnvelope")),
     *     @OA\Response(response="default", ref="#/components/responses/BrowserAuthError")
     * )
     */
    #[Post('/step4')]
    public function step4(Request $request): Response
    {
        try {
            $this->assertCsrf($request);
            if (!$this->installed()) throw new DomainException('系统尚未完成安装', 409);
            return $this->result(['installed' => true, 'backend' => $this->adminLoginUrl()], '安装完成')
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
        foreach (['hostname', 'port', 'database', 'prefix', 'username', 'password', 'adminUserName', 'adminPassword', 'rePassword', 'email', 'adminLoginPath'] as $key) {
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
            'adminLoginPath' => $values['adminLoginPath'] !== '' ? $values['adminLoginPath'] : AdminLoginPath::DEFAULT,
        ];
    }

    private function adminLoginUrl(): string
    {
        $entry = AdminLoginPath::current();
        return '/#/admin/' . ($entry === AdminLoginPath::DEFAULT ? '' : $entry . '/') . 'login';
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
            $this->environmentCheck('migrations', '数据库迁移', '核心 SQL（admin/database/core）可读', $files ? count($files) . ' 个迁移文件' : '无迁移文件', $migrations, true),
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
