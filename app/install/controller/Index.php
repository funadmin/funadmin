<?php

namespace app\install\controller;

use app\common\traits\Jump;
use app\BaseController;
use app\common\service\InstallSupport;

/**
 * 安装向导后端接口（Vue 安装页 /admin-web/#/install 调用）。
 * step2 环境检测 / step3 执行安装 / step4 读取并清理安装结果。
 */
class Index extends BaseController
{
    use Jump;

    /**
     * 入口：按安装状态引导到 Vue 安装页或登录页。
     */
    public function index()
    {
        $route = file_exists($this->lockFile()) ? 'login' : 'install';
        return redirect('/admin-web/#/' . $route);
    }

    /**
     * 返回 Vue 安装向导所需的运行环境检查结果。
     * 已安装时只返回安装状态，不暴露服务器环境详情。
     */
    public function step2()
    {
        if (file_exists($this->lockFile())) {
            $this->result(['installed' => true], 200, '系统已安装', 'json');
        }
        $this->result([
            'installed' => false,
            'siteName' => 'FunAdmin',
            'siteVersion' => config('app.version'),
            'checks' => $this->buildEnvironmentChecks(),
        ], 200, '环境检测完成', 'json');
    }

    /**
     * 执行安装：校验 → 建库 → 写配置 → 迁移与初始管理员 → 落锁。
     */
    public function step3()
    {
        if (!request()->isPost()) {
            $this->error('请求方法不正确');
        }
        if (file_exists($this->lockFile())) {
            $this->error('当前版本已经安装了，如果需要重新安装请先删除install.lock');
        }
        set_time_limit(0);
        $input = $this->collectInstallInput();
        $this->assertEnvironmentReady();
        try {
            $result = InstallSupport::install(
                $input['db'],
                $input['admin'],
                $input['debug'],
                root_path()
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
        }
        session('admin_install', $result);
        $this->result($result, 200, '安装成功', 'json');
    }

    /**
     * 读取安装结果；POST 时清理安装会话，避免凭据残留。
     */
    public function step4()
    {
        $admin = session('admin_install');
        if (!$admin) {
            $this->error('安装信息不存在');
        }
        if (request()->isPost()) {
            session('admin_install', null);
        }
        $this->result($admin, 200, '安装完成', 'json');
    }

    /**
     * 组装环境检测项：运行依赖、上传限制与安装目录权限。
     */
    private function buildEnvironmentChecks(): array
    {
        $uploadLimit = $this->sizeToBytes((string) ini_get('upload_max_filesize'));
        return [
            $this->environmentCheck('os', '操作系统', '不限', php_uname('s'), true, true),
            $this->environmentCheck('php', 'PHP 版本', '>= 8.1.0', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>='), true),
            $this->environmentCheck('php_int', 'PHP 架构', '64 位', PHP_INT_SIZE === 8 ? '64 位' : '32 位', PHP_INT_SIZE === 8, true),
            $this->environmentCheck('json', 'JSON 扩展', '支持', $this->supportLabel(extension_loaded('json')), extension_loaded('json'), true),
            $this->environmentCheck('session', 'Session', '支持', $this->supportLabel(function_exists('session_start')), function_exists('session_start'), true),
            $this->environmentCheck('pdo', 'PDO 扩展', '支持', $this->supportLabel(extension_loaded('PDO')), extension_loaded('PDO'), true),
            $this->environmentCheck('pdo_mysql', 'PDO MySQL 驱动', '支持', $this->supportLabel(extension_loaded('pdo_mysql')), extension_loaded('pdo_mysql'), true),
            $this->environmentCheck('mysqli', 'MySQLi 扩展', '支持', $this->supportLabel(extension_loaded('mysqli')), extension_loaded('mysqli'), true),
            $this->environmentCheck('openssl', 'OpenSSL 扩展', '支持', $this->supportLabel(extension_loaded('openssl')), extension_loaded('openssl'), true),
            $this->environmentCheck('curl', 'CURL 扩展', '支持', $this->supportLabel(function_exists('curl_exec')), function_exists('curl_exec'), true),
            $this->environmentCheck('fileinfo', 'Fileinfo 扩展', '支持', $this->supportLabel(extension_loaded('fileinfo')), extension_loaded('fileinfo'), true),
            $this->environmentCheck('image', '图像扩展', 'GD 或 Imagick', $this->imageDriverLabel(), function_exists('gd_info') || class_exists('Imagick'), true),
            $this->environmentCheck('freetype', 'FreeType', '支持', $this->supportLabel(function_exists('imageftbbox')), function_exists('imageftbbox'), false),
            $this->environmentCheck('zip', 'ZIP 扩展', '支持', $this->supportLabel(extension_loaded('zip')), extension_loaded('zip'), false),
            $this->environmentCheck('upload', '文件上传限制', '>= 10 MB', (string) ini_get('upload_max_filesize'), $uploadLimit >= 10 * 1024 * 1024, false),
            $this->environmentCheck('runtime', 'runtime 目录', '可写', $this->writableLabel(runtime_path()), $this->isWritable(runtime_path()), true),
            $this->environmentCheck('public', 'public 目录', '可写', $this->writableLabel(public_path()), $this->isWritable(public_path()), true),
            $this->environmentCheck('env', '.env 配置', '可写', $this->writableLabel($this->envFile()), $this->isWritable($this->envFile()), true),
            $this->environmentCheck('migrations', '数据库迁移', '目录可读且包含 SQL', $this->migrationStatus(), $this->hasMigrations(), true),
        ];
    }

    /**
     * 收集并归一化表单输入：主机头携带端口时自动拆分。
     */
    private function collectInstallInput(): array
    {
        $db = [
            'host' => request()->post('hostname') ?: '127.0.0.1',
            'port' => request()->post('port') ?: '3306',
            'username' => request()->post('username') ?: 'root',
            'password' => (string) (request()->post('password') ?: 'root'),
            'database' => request()->post('database') ?: 'funadmin',
            'prefix' => (string) request()->post('prefix'),
        ];
        $db = InstallSupport::normalizeDatabaseInput($db);
        $admin = [
            'username' => request()->post('adminUserName') ?: 'admin',
            'password' => request()->post('adminPassword') ?: '123456',
            'repassword' => request()->post('rePassword') ?: '123456',
            'email' => request()->post('email') ?: 'admin@admin.com',
        ];
        return ['db' => $db, 'admin' => $admin, 'debug' => (bool) request()->post('app_debug')];
    }

    /**
     * 运行环境硬门槛：PHP 版本、数据库扩展与迁移文件。
     */
    private function assertEnvironmentReady(): void
    {
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->error('当前 PHP 版本 ' . PHP_VERSION . ' 过低，请使用 PHP 8.1.0 以上版本');
        }
        if (!extension_loaded('PDO') || !extension_loaded('mysqli')) {
            $this->error('当前未开启 PDO 或 MySQLi，无法进行安装');
        }
        if (!$this->hasMigrations()) {
            $this->error('数据库迁移文件不存在，无法进行安装');
        }
    }

    private function environmentCheck(string $key, string $label, string $requiredValue, string $currentValue, bool $passed, bool $required): array
    {
        return compact('key', 'label', 'requiredValue', 'currentValue', 'passed', 'required');
    }

    /** 安装锁文件，存在即视为已安装 */
    private function lockFile(): string
    {
        return InstallSupport::paths(root_path())['lock'];
    }

    /** 安装时回写的 .env 文件 */
    private function envFile(): string
    {
        return InstallSupport::paths(root_path())['env'];
    }

    private function isWritable(string $file): bool
    {
        return file_exists($file) ? is_writable($file) : is_writable(dirname($file));
    }

    private function writableLabel(string $file): string
    {
        return $this->isWritable($file) ? '可写' : '不可写';
    }

    private function supportLabel(bool $supported): string
    {
        return $supported ? '支持' : '未安装';
    }

    private function imageDriverLabel(): string
    {
        if (class_exists('Imagick')) {
            return 'Imagick';
        }
        return function_exists('gd_info') ? 'GD' : '未安装';
    }

    private function sizeToBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $size = (float) $value;
        return match ($unit) {
            'g' => (int) ($size * 1024 * 1024 * 1024),
            'm' => (int) ($size * 1024 * 1024),
            'k' => (int) ($size * 1024),
            default => (int) $size,
        };
    }

    private function migrationFiles(): array
    {
        $sqlFileDir = InstallSupport::paths(root_path())['migrations'];
        if (!is_dir($sqlFileDir) || !is_readable($sqlFileDir)) {
            return [];
        }
        return glob($sqlFileDir . DIRECTORY_SEPARATOR . '*.sql') ?: [];
    }

    private function hasMigrations(): bool
    {
        $files = $this->migrationFiles();
        foreach ($files as $file) {
            if (!is_readable($file)) {
                return false;
            }
        }
        return count($files) > 0;
    }

    private function migrationStatus(): string
    {
        $sqlFileDir = InstallSupport::paths(root_path())['migrations'];
        if (!is_dir($sqlFileDir)) {
            return '目录不存在';
        }
        if (!is_readable($sqlFileDir)) {
            return '目录不可读';
        }
        $files = $this->migrationFiles();
        foreach ($files as $file) {
            if (!is_readable($file)) {
                return basename($file) . ' 不可读';
            }
        }
        return $files ? count($files) . ' 个迁移文件' : '无迁移文件';
    }
}
