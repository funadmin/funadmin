<?php
/**
 * FunAdmin CLI 安装命令：php think install
 * 与 Web 安装向导共用 app\common\service\InstallSupport 内核，
 * 保证校验、配置渲染与迁移行为单一来源。
 */

namespace fun\curd;

use app\common\service\InstallSupport;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

class Install extends Command
{
    /** MySQL 最低版本 */
    private const MYSQL_MIN_VERSION = '5.7';

    protected function configure()
    {
        $config = config('database.connections')[config('database.default')] ?? [];
        $this->setName('install')
            ->addOption('hostname', 'm', Option::VALUE_OPTIONAL, 'mysql hostname', $config['hostname'] ?? '127.0.0.1')
            ->addOption('hostport', 'r', Option::VALUE_OPTIONAL, 'mysql hostport', $config['hostport'] ?? '3306')
            ->addOption('database', 'd', Option::VALUE_OPTIONAL, 'database name', $config['database'] ?? 'funadmin')
            ->addOption('prefix', 'x', Option::VALUE_OPTIONAL, 'table prefix', $config['prefix'] ?? 'fun_')
            ->addOption('username', 'u', Option::VALUE_OPTIONAL, 'mysql username', $config['username'] ?? 'root')
            ->addOption('password', 'p', Option::VALUE_OPTIONAL, 'mysql password', $config['password'] ?? '')
            ->addOption('force', 'f', Option::VALUE_OPTIONAL, 'force reinstall when install.lock exists', false)
            ->addOption('app_debug', 'debug', Option::VALUE_OPTIONAL, 'enable app debug after install', 1)
            ->setDescription('FunAdmin install command');
    }

    protected function execute(Input $input, Output $output)
    {
        set_time_limit(0);
        if (is_file($this->lockFile()) && !$input->getOption('force')) {
            $output->highlight('已经安装了,如需重新安装请输入 -f 1或 --force 1');
            return 0;
        }
        $appDebug = (bool) $input->getOption('app_debug');
        $this->detectEnvironment();
        $installInput = $this->collectInstallInput($input);
        $error = InstallSupport::validate($installInput['db'], $installInput['admin']);
        if ($error !== '') {
            $output->error($error);
            return 1;
        }
        try {
            $output->highlight('连接数据库...');
            InstallSupport::prepareDatabase($installInput['db'], self::MYSQL_MIN_VERSION);
            $output->highlight('修改数据配置中...');
            $this->applyDatabaseConfig($installInput['db']);
            $this->writeEnvConfig($installInput['db'], $appDebug);
            InstallSupport::installSchemaAndAdmin($this->sqlFileDir(), $installInput['admin']);
        } catch (\Throwable $e) {
            $output->error($e->getMessage());
            return 1;
        }
        if (!@touch($this->lockFile())) {
            $output->error('安装完成但无法创建 install.lock，请检查 public 目录权限');
            return 1;
        }
        $output->highlight('数据库安装完成...');
        $output->highlight('👉 恭喜您：系统已经安装完成... 后台入口固定为 /admin-web/');
        $output->highlight('👉 管理员账号: ' . $installInput['admin']['username'] . '，管理员密码: ' . $installInput['admin']['password']);
        return 0;
    }

    /**
     * 运行环境硬门槛：PHP 版本、关键扩展、目录权限与迁移文件。
     */
    private function detectEnvironment(): void
    {
        $this->output->info('environment begin to check...');
        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            $this->output->error('php version should >= 8.1.0');
            exit(1);
        }
        foreach (['pdo', 'pdo_mysql', 'mysqli', 'openssl', 'json'] as $extension) {
            if (!extension_loaded($extension)) {
                $this->output->error($extension . ' extension not install');
                exit(1);
            }
        }
        if (!function_exists('session_start') || !function_exists('curl_exec')) {
            $this->output->error('session/curl function not available');
            exit(1);
        }
        if (!is_writable(runtime_path())) {
            $this->output->error('runtime path is not writeable');
            exit(1);
        }
        $sqlFiles = glob($this->sqlFileDir() . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        if (!$sqlFiles) {
            $this->output->error('migration 文件不存在');
            exit(1);
        }
        foreach ($sqlFiles as $file) {
            if (!is_readable($file)) {
                $this->output->error("无法读取{$file}文件，请检查读权限");
                exit(1);
            }
        }
        $this->output->info('🎉 environment checking finished');
    }

    /**
     * 交互收集安装输入：.env 已有值优先作为默认，选项次之。
     */
    private function collectInstallInput(Input $input): array
    {
        $base = [
            'host' => (string) $input->getOption('hostname'),
            'port' => (string) $input->getOption('hostport'),
            'database' => (string) $input->getOption('database'),
            'prefix' => (string) $input->getOption('prefix'),
            'username' => (string) $input->getOption('username'),
            'password' => (string) $input->getOption('password'),
        ];
        if (is_file($this->envFile())) {
            $env = parse_ini_file($this->envFile(), false, INI_SCANNER_RAW) ?: [];
            $base = [
                'host' => (string) ($env['DB_HOST'] ?? $base['host']),
                'port' => (string) ($env['DB_PORT'] ?? $base['port']),
                'database' => (string) ($env['DB_NAME'] ?? $base['database']),
                'prefix' => (string) ($env['DB_PREFIX'] ?? $base['prefix']),
                'username' => (string) ($env['DB_USER'] ?? $base['username']),
                'password' => (string) ($env['DB_PASS'] ?? $base['password']),
            ];
        }
        $ask = fn (string $question, string $default): string => (string) ($this->output->ask($input, $question, $default) ?: $default);
        $db = [
            'host' => $ask('👉 Set mysql hostname default(' . $base['host'] . ')', $base['host']),
            'port' => $ask('👉 Set mysql hostport default(' . $base['port'] . ')', $base['port']),
            'database' => $ask('👉 Set mysql database default(' . $base['database'] . ')', $base['database']),
            'prefix' => $ask('👉 Set mysql table prefix default(' . $base['prefix'] . ')', $base['prefix']),
            'username' => $ask('👉 Set mysql username default(' . $base['username'] . ')', $base['username']),
            'password' => $ask('👉 Set mysql password', $base['password']),
        ];
        $hostData = explode(':', $db['host']);
        if (count($hostData) > 1) {
            $db['host'] = $hostData[0];
            $db['port'] = $hostData[1];
        }
        $admin = [
            'username' => $ask('👉 Set admin username default(admin)', 'admin'),
            'password' => $ask('👉 Set admin password default(admin123456)', 'admin123456'),
            'email' => $ask('👉 Set admin email default(admin@admin.com)', 'admin@admin.com'),
        ];
        $admin['repassword'] = $ask('👉 Repeat admin password', $admin['password']);
        return ['db' => $db, 'admin' => $admin];
    }

    /**
     * 将安装参数应用到当前进程，供随后的迁移使用，不写入受版本控制的配置文件。
     */
    private function applyDatabaseConfig(array $db): void
    {
        $config = config('database');
        $config['default'] = 'mysql';
        $config['connections']['mysql'] = [
            'type' => 'mysql',
            'hostname' => $db['host'],
            'database' => $db['database'],
            'username' => $db['username'],
            'password' => $db['password'],
            'hostport' => $db['port'],
            'params' => [],
            'charset' => 'utf8mb4',
            'prefix' => $db['prefix'],
            'fields_cache' => false,
        ];
        config($config, 'database');
    }

    /**
     * 回写 .env：已存在时合并更新保留 JWT 等自定义键，全新安装才按模板写入。
     */
    private function writeEnvConfig(array $db, bool $debug): void
    {
        $existing = is_file($this->envFile()) ? (string) file_get_contents($this->envFile()) : null;
        $putEnv = InstallSupport::renderEnv($this->envTemplate(), $existing, $db, $debug);
        if (!@file_put_contents($this->envFile(), $putEnv)) {
            throw new \RuntimeException('安装失败、请确定 .env 是否有写入权限');
        }
    }

    /** 安装锁文件，存在即视为已安装 */
    private function lockFile(): string
    {
        return public_path() . 'install.lock';
    }

    /** 安装时回写的 .env 文件 */
    private function envFile(): string
    {
        return root_path() . '.env';
    }

    /** .env 基础模板 */
    private function envTemplate(): string
    {
        return root_path() . '.env.example';
    }

    /** 迁移 SQL 目录：安装期间按文件名顺序执行，仅向前 */
    private function sqlFileDir(): string
    {
        return root_path() . 'database' . DIRECTORY_SEPARATOR . 'migrations';
    }
}
