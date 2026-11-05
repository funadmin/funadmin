<?php

declare(strict_types=1);

namespace app\common\service\install;

use app\common\service\database\MigrationService;
use DomainException;
use RuntimeException;
use support\Db;

/** Web 与 CLI 共用的 Webman 安装内核。 */
final class InstallSupport
{
    public static function validate(array $db, array $admin): string
    {
        foreach (array_merge($db, $admin) as $value) {
            if (!is_string($value) || str_contains($value, "\0") || preg_match('/[\r\n]/', $value)) {
                return '安装配置必须为字符串，且不能包含换行或空字节';
            }
        }
        $driver = $db['driver'] ?? 'mysql';
        if ($driver !== 'mysql') return '数据库类型仅支持 MySQL';
        if (($admin['password'] ?? '') !== ($admin['repassword'] ?? '')) return '两次输入密码不一致！';
        $length = strlen($admin['password'] ?? '');
        if ($length < 12 || $length > 72) return '管理员密码需为12～72字节';
        if (!preg_match('/[a-z]/i', $admin['password']) || !preg_match('/\d/', $admin['password'])) return '管理员密码必须同时包含字母和数字';
        if (!preg_match('/\A[A-Za-z0-9_]{1,64}\z/', $db['database'] ?? '')) return '数据库名只能包含字母、数字和下划线，最长64位';
        if (($db['prefix'] ?? '') !== '' && !preg_match('/\A[A-Za-z][A-Za-z0-9_]{0,29}_\z/', $db['prefix'])) return '数据表前缀必须以字母开头、下划线结尾，最长32位';
        if (!preg_match('/\A(?:[A-Za-z0-9_.-]+|[0-9a-fA-F:]+)\z/', $db['host'] ?? '')) return '数据库主机格式不正确';
        if (!ctype_digit($db['port'] ?? '') || (int) $db['port'] < 1 || (int) $db['port'] > 65535) return '数据库端口必须在1～65535之间';
        if (($db['username'] ?? '') === '') return '数据库用户名不能为空';
        if (!preg_match('/\A[A-Za-z0-9_]{3,12}\z/', $admin['username'] ?? '')) return '管理员账号需为3～12位字母、数字或下划线';
        if (strlen($admin['email'] ?? '') > 60 || !filter_var($admin['email'] ?? '', FILTER_VALIDATE_EMAIL)) return '管理员邮箱格式不正确或超过60个字符';
        return '';
    }

    public static function normalizeDatabaseInput(array $db): array
    {
        foreach ($db as $value) {
            if (!is_string($value)) throw new DomainException('数据库配置必须为字符串', 422);
        }
        $db['driver'] ??= 'mysql';
        $db['prefix'] ??= '';
        if (preg_match('/^([^:]+):(\d+)$/', $db['host'] ?? '', $matches)) {
            $db['host'] = $matches[1];
            $db['port'] = $matches[2];
        }
        return $db;
    }

    public static function paths(string $rootPath): array
    {
        $root = rtrim($rootPath, DIRECTORY_SEPARATOR);
        return [
            'lock' => $root . '/public/install.lock',
            'env' => $root . '/.env',
            'env_template' => $root . '/.env.example',
            'migrations' => rtrim(getenv('CORE_DATABASE_PATH') ?: dirname($root) . '/database', DIRECTORY_SEPARATOR) . '/migrations',
            'php_fixtures' => $root . '/database/migrations',
            'runtime' => $root . '/runtime',
            'public' => $root . '/public',
        ];
    }

    public static function prepareDatabase(array $db, string $minMysqlVersion): void
    {
        if (($db['driver'] ?? 'mysql') !== 'mysql') throw new DomainException('数据库类型仅支持 MySQL', 422);
        try {
            $pdo = new \PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['username'], $db['password'], [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]);
            $version = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            if (version_compare($version, $minMysqlVersion, '<')) throw new DomainException('MySQL 版本不能低于' . $minMysqlVersion, 422);
            // 已存在的库不要求 CREATE DATABASE 权限。
            try {
                $pdo->exec('USE `' . $db['database'] . '`');
            } catch (\PDOException $error) {
                if (($error->errorInfo[1] ?? null) !== 1049) throw $error;
                $pdo->exec('CREATE DATABASE `' . $db['database'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
        } catch (\PDOException) {
            throw new DomainException('数据库连接或建库失败，请检查主机、端口、账号、密码及数据库权限', 422);
        }
    }

    public static function databaseConfig(array $db, array $config): array
    {
        $driver = $db['driver'] ?? 'mysql';
        if ($driver !== 'mysql') throw new DomainException('数据库类型仅支持 MySQL', 422);
        $config['default'] = $driver;
        $config['connections'][$driver] = array_merge($config['connections'][$driver] ?? [], [
            'driver' => $driver, 'host' => $db['host'], 'port' => (int) $db['port'],
            'database' => $db['database'], 'username' => $db['username'], 'password' => $db['password'],
            'prefix' => $db['prefix'],
        ], ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']);
        return $config;
    }

    public static function installSchemaAndAdmin(string $directory, array $admin): void
    {
        self::assertNoAdministrator();
        if (Db::connection()->getDriverName() === 'mysql') {
            if (realpath($directory) !== MigrationService::coreDirectory() || !is_file($directory . '/001_baseline.sql')) {
                throw new RuntimeException('MySQL 安装必须使用权威核心 SQL，禁止回退 PHP 骨架');
            }
            self::installCoreSchemaAndAdmin($directory, $admin);
            return;
        }
        (new MigrationService())->run($directory);
        Db::transaction(static function () use ($admin): void {
            self::assertNoAdministrator();
            $now = date('Y-m-d H:i:s');
            Db::table('admin')->insert([
                'id' => 1, 'username' => $admin['username'], 'email' => $admin['email'],
                'password' => password_hash($admin['password'], PASSWORD_DEFAULT), 'status' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            Db::table('auth_group')->insertOrIgnore([
                'id' => 1, 'name' => '超级管理员', 'code' => 'super_admin', 'level' => 1,
                'data_scope' => 'all', 'status' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $values = ['g', 'admin:1', 'role:1', 'default'];
            Db::table('casbin_rule')->insertOrIgnore([
                'ptype' => 'g', 'v0' => 'admin:1', 'v1' => 'role:1', 'v2' => 'default',
                'rule_hash' => hash('sha256', implode("\x1f", $values)),
            ]);
        });
    }

    private static function installCoreSchemaAndAdmin(string $directory, array $admin): void
    {
        if (Db::connection()->select('SHOW TABLES') !== []) {
            throw new DomainException('根基线安装要求空库；存量库必须进行差异接管', 409);
        }
        (new MigrationService())->runDirectory($directory);
        Db::transaction(static function () use ($admin): void {
            $seed = Db::table('admin')->lockForUpdate()->first();
            if (Db::table('admin')->count() !== 1 || !$seed || (int) $seed->id !== 1 || $seed->username !== 'admin') {
                throw new RuntimeException('核心基线管理员种子不符合安装契约');
            }
            Db::table('admin')->where('id', 1)->update([
                'username' => $admin['username'], 'email' => $admin['email'],
                'password' => password_hash($admin['password'], PASSWORD_DEFAULT),
                'status' => 1, 'updated_at' => date('Y-m-d H:i:s'),
            ]);
        });
    }

    private static function assertNoAdministrator(): void
    {
        if (Db::schema()->hasTable('admin') && Db::table('admin')->exists()) {
            throw new DomainException('目标数据库已有管理员，禁止通过安装流程覆盖；请使用已有账号登录', 409);
        }
    }

    public static function install(array $db, array $admin, bool $debug, string $rootPath, string $minMysqlVersion = '5.7', ?callable $progress = null): array
    {
        $db = self::normalizeDatabaseInput($db);
        if ($error = self::validate($db, $admin)) throw new DomainException($error, 422);
        $paths = self::paths($rootPath);
        $mutex = fopen($paths['runtime'] . '/install.mutex', 'c');
        if (!$mutex) throw new RuntimeException('无法创建安装互斥锁，请检查 runtime 权限');
        if (!flock($mutex, LOCK_EX | LOCK_NB)) {
            fclose($mutex);
            throw new DomainException('其他安装任务正在执行，请稍后重试', 409);
        }
        class_exists(Db::class);
        $manager = \Illuminate\Database\Eloquent\Model::getConnectionResolver();
        $previous = $manager->getDefaultConnection();
        try {
            clearstatcache(true, $paths['lock']);
            if (is_file($paths['lock'])) throw new DomainException('系统已安装，禁止重复安装', 409);
            $content = self::renderEnv($paths['env_template'], is_file($paths['env']) ? (string) file_get_contents($paths['env']) : null, $db, $debug);
            if (!is_writable($paths['public']) || !is_writable(is_file($paths['env']) ? $paths['env'] : $rootPath)) throw new RuntimeException('安装目录或 .env 不可写');
            $progress?->__invoke('database');
            self::prepareDatabase($db, $minMysqlVersion);
            $connection = self::databaseConfig($db, config('database'))['connections'][$db['driver']];
            $name = 'installer_' . bin2hex(random_bytes(8));
            self::registerConnection($name, $connection);
            $manager->setDefaultConnection($name);
            self::assertNoAdministrator();
            $progress?->__invoke('schema');
            self::installSchemaAndAdmin(MigrationService::coreDirectory($rootPath), $admin);
            $progress?->__invoke('configuration');
            self::writeFile($paths['env'], $content, 0600);
            self::writeFile($paths['lock'], json_encode(['installed_at' => date(DATE_ATOM), 'env_hash' => hash('sha256', $content)], JSON_THROW_ON_ERROR), 0644);
            $progress?->__invoke('complete');
            return ['username' => $admin['username'], 'backend' => '/#/admin/login'];
        } finally {
            $manager->setDefaultConnection($previous);
            flock($mutex, LOCK_UN);
            fclose($mutex);
        }
    }

    /** 各 worker 首次看到安装完成标记时使用全新命名连接，避免复用旧连接池。 */
    public static function activateInstalledConfiguration(?string $rootPath = null): void
    {
        static $activated = [];
        $paths = self::paths($rootPath ?? base_path());
        clearstatcache(true, $paths['lock']);
        if (!is_file($paths['lock']) || !is_file($paths['env'])) return;
        $marker = json_decode((string) file_get_contents($paths['lock']), true);
        $hash = $marker['env_hash'] ?? '';
        if (!is_string($hash) || !preg_match('/\A[a-f0-9]{64}\z/', $hash) || isset($activated[$hash])) return;
        $content = (string) file_get_contents($paths['env']);
        if (!hash_equals($hash, hash('sha256', $content))) return;
        $env = \Dotenv\Dotenv::parse($content);
        $db = [
            'host' => $env['DB_HOST'], 'port' => $env['DB_PORT'], 'database' => $env['DB_DATABASE'],
            'username' => $env['DB_USERNAME'], 'password' => $env['DB_PASSWORD'], 'prefix' => $env['DB_PREFIX'],
            'driver' => $env['DB_CONNECTION'] ?? 'mysql',
        ];
        $name = 'installed_' . $hash;
        class_exists(Db::class);
        if ($db['driver'] !== 'mysql') throw new RuntimeException('已安装的数据库类型不受支持');
        self::registerConnection($name, self::databaseConfig($db, config('database'))['connections'][$db['driver']]);
        $alias = $db['driver'];
        self::registerConnection($alias, self::databaseConfig($db, config('database'))['connections'][$alias]);
        \Illuminate\Database\Eloquent\Model::getConnectionResolver()->purge($alias);
        \Illuminate\Database\Eloquent\Model::getConnectionResolver()->setDefaultConnection($name);
        $activated[$hash] = true;
    }

    private static function registerConnection(string $name, array $connection): void
    {
        $config = \Illuminate\Container\Container::getInstance()['config'];
        $connections = $config['database.connections'];
        $connections[$name] = array_replace($connection, ['name' => $name]);
        $config['database.connections'] = $connections;
    }

    private static function writeFile(string $path, string $content, int $mode): void
    {
        $temporary = tempnam(dirname($path), '.install-');
        if ($temporary === false) throw new RuntimeException('无法创建安装配置临时文件');
        try {
            if (!chmod($temporary, $mode) || file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $path)) {
                throw new RuntimeException('无法保存安装配置，请检查目录权限');
            }
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }

    public static function renderEnv(string $templatePath, ?string $existing, array $db, bool $debug): string
    {
        $template = $existing ?? (string) file_get_contents($templatePath);
        return self::mergeEnv($template, self::buildEnvUpdates($db, $debug));
    }

    public static function writeEnvironment(string $envFile, string $templatePath, array $db, bool $debug): void
    {
        self::writeFile($envFile, self::renderEnv($templatePath, is_file($envFile) ? (string) file_get_contents($envFile) : null, $db, $debug), 0600);
    }

    public static function buildEnvUpdates(array $db, bool $debug): array
    {
        return [
            'APP_DEBUG' => $debug ? 'true' : 'false', 'DB_CONNECTION' => $db['driver'] ?? 'mysql',
            'DB_HOST' => $db['host'], 'DB_PORT' => $db['port'], 'DB_DATABASE' => $db['database'],
            'DB_USERNAME' => $db['username'], 'DB_PASSWORD' => $db['password'], 'DB_PREFIX' => $db['prefix'],
        ];
    }

    private static function mergeEnv(string $content, array $updates): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $found = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/', $line, $m) && array_key_exists($m[1], $updates)) {
                $lines[$i] = $m[1] . '=' . self::encodeEnvValue((string) $updates[$m[1]]);
                $found[$m[1]] = true;
            }
        }
        foreach ($updates as $key => $value) {
            if (!isset($found[$key])) $lines[] = $key . '=' . self::encodeEnvValue((string) $value);
        }
        return implode("\n", $lines);
    }

    private static function encodeEnvValue(string $value): string
    {
        return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']) . '"';
    }
}
