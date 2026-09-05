<?php

namespace app\common\service;

use app\backend\model\Admin;
use RuntimeException;
use think\facade\Db;

/**
 * 安装器共享内核：Web 安装向导与 CLI install 命令共用，
 * 保证校验规则、配置渲染与迁移行为单一来源，避免两处实现漂移。
 */
final class InstallSupport
{
    /**
     * 校验数据库与管理员表单，返回第一条错误信息；空字符串表示通过。
     */
    public static function validate(array $db, array $admin): string
    {
        if ($admin['password'] != $admin['repassword']) {
            return '两次输入密码不一致！';
        }
        if (!preg_match('/^[0-9a-z_$]{6,16}$/i', $admin['password'])) {
            return '密码必须6-16位,不能有中文和空格';
        }
        if (!preg_match('/[a-z]/i', $admin['password']) || !preg_match('/\d/', $admin['password'])) {
            return '管理员密码必须同时包含字母和数字';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $db['database'])) {
            return '数据库名只能包含字母、数字和下划线';
        }
        if ($db['prefix'] !== '' && !preg_match('/^[a-zA-Z][a-zA-Z0-9_]*_$/', $db['prefix'])) {
            return '数据表前缀必须以字母开头、下划线结尾';
        }
        if (strlen($admin['email']) > 60) {
            return '管理员邮箱不能超过60个字符';
        }
        if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
            return '管理员邮箱格式不正确';
        }
        foreach ($db as $value) {
            if (str_contains((string) $value, "\0") || preg_match('/\R/', (string) $value)) {
                return '数据库配置不能包含换行或空字节';
            }
        }
        if (!preg_match("/^\w+$/", $admin['username'])) {
            return '用户名只能输入字母、数字、下划线！';
        }
        if (strlen($admin['username']) < 3 || strlen($admin['username']) > 12) {
            return '用户名请输入3~12位字符！';
        }
        return '';
    }

    /**
     * 连接 MySQL、校验版本并创建/选中目标数据库；失败抛出 RuntimeException。
     */
    public static function prepareDatabase(array $db, string $minMysqlVersion): void
    {
        try {
            $link = new \mysqli($db['host'], $db['username'], $db['password'], '', (int) $db['port']);
        } catch (\mysqli_sql_exception $exception) {
            throw new RuntimeException('数据库连接失败：' . $exception->getMessage());
        }
        $link->query("SET NAMES 'utf8mb4'");
        if (version_compare($link->server_info, $minMysqlVersion, '<')) {
            throw new RuntimeException("MySQL数据库版本不能低于{$minMysqlVersion},请将您的MySQL升级到{$minMysqlVersion}及以上");
        }
        try {
            $createSql = 'CREATE DATABASE IF NOT EXISTS `' . $db['database'] . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;';
            if (!$link->query($createSql)) {
                throw new RuntimeException('创建数据库失败');
            }
        } catch (\mysqli_sql_exception $exception) {
            throw new RuntimeException($exception->getMessage());
        }
        $link->select_db($db['database']);
    }

    /**
     * 渲染 .env 内容：已有文件时合并更新，全新安装以标准示例为基础生成。
     */
    public static function renderEnv(string $templatePath, ?string $existing, array $db, bool $debug): string
    {
        $template = $existing ?? (string) file_get_contents($templatePath);
        return self::mergeEnv($template, self::buildEnvUpdates($db, $debug));
    }

    /**
     * 执行核心迁移并写入初始管理员账号；失败抛出异常由调用方转译。
     */
    public static function installSchemaAndAdmin(string $sqlFileDir, array $admin): void
    {
        Db::connect()->execute('SELECT 1');
        MigrationService::instance()->runDirectory($sqlFileDir, 'core');
        Admin::where('id', 1)->update([
            'email' => $admin['email'],
            'username' => $admin['username'],
            'password' => password($admin['password']),
        ]);
    }

    /**
     * 归一化数据库参数，支持 hostname:port 的简写。
     */
    public static function normalizeDatabaseInput(array $db): array
    {
        $db = array_map(static fn (mixed $value): string => (string) $value, $db);
        if (preg_match('/^([^:]+):(\d+)$/', $db['host'] ?? '', $matches)) {
            $db['host'] = $matches[1];
            $db['port'] = $matches[2];
        }
        return $db;
    }

    /**
     * 安装器统一路径，Web 与 CLI 不再各自维护。
     */
    public static function paths(string $rootPath): array
    {
        $root = rtrim($rootPath, DIRECTORY_SEPARATOR);
        return [
            'lock' => $root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'install.lock',
            'env' => $root . DIRECTORY_SEPARATOR . '.env',
            'env_template' => $root . DIRECTORY_SEPARATOR . '.env.example',
            'migrations' => $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations',
            'runtime' => $root . DIRECTORY_SEPARATOR . 'runtime',
            'public' => $root . DIRECTORY_SEPARATOR . 'public',
        ];
    }

    /**
     * 构建当前进程使用的数据库连接配置，并保留项目已有扩展项。
     */
    public static function databaseConfig(array $db, array $config): array
    {
        $connection = $config['connections']['mysql'] ?? [];
        $config['default'] = 'mysql';
        $config['connections']['mysql'] = array_merge($connection, [
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
        ]);
        return $config;
    }

    /**
     * 执行完整安装流水线，入口层只负责采集参数和展示结果。
     */
    public static function install(
        array $db,
        array $admin,
        bool $debug,
        string $rootPath,
        string $minMysqlVersion = '5.7',
        ?callable $progress = null
    ): array {
        $db = self::normalizeDatabaseInput($db);
        $error = self::validate($db, $admin);
        if ($error !== '') {
            throw new RuntimeException($error);
        }
        $paths = self::paths($rootPath);
        $progress?->__invoke('database');
        self::prepareDatabase($db, $minMysqlVersion);
        config(self::databaseConfig($db, config('database')), 'database');
        // 切换配置后必须强制重建连接实例，否则模型与迁移仍复用进程早期建立的旧连接
        Db::connect('mysql', true);
        $progress?->__invoke('configuration');
        self::writeEnvironment($paths['env'], $paths['env_template'], $db, $debug);
        self::installSchemaAndAdmin($paths['migrations'], $admin);
        if (!@touch($paths['lock'])) {
            throw new RuntimeException('安装完成但无法创建 install.lock，请检查 public 目录权限');
        }
        $progress?->__invoke('complete');
        return [
            'username' => $admin['username'],
            'password' => $admin['password'],
            'backend' => '/admin-web/#/login',
        ];
    }

    /**
     * 合并写入安装环境配置。
     */
    public static function writeEnvironment(string $envFile, string $templatePath, array $db, bool $debug): void
    {
        $existing = is_file($envFile) ? (string) file_get_contents($envFile) : null;
        $content = self::renderEnv($templatePath, $existing, $db, $debug);
        if (!@file_put_contents($envFile, $content)) {
            throw new RuntimeException('安装失败、请确定 .env 是否有写入权限');
        }
    }

    /**
     * 构建 .env 中由安装器负责的键值集合。
     */
    public static function buildEnvUpdates(array $db, bool $debug): array
    {
        return [
            'APP_DEBUG' => $debug ? 'true' : 'false',
            'DB_DRIVER' => 'mysql',
            'DB_TYPE' => 'mysql',
            'DB_HOST' => $db['host'],
            'DB_NAME' => $db['database'],
            'DB_USER' => $db['username'],
            'DB_PASS' => $db['password'],
            'DB_PORT' => $db['port'],
            'DB_CHARSET' => 'utf8mb4',
            'DB_PREFIX' => $db['prefix'],
        ];
    }

    /**
     * 合并更新 .env：仅替换已有键的值，缺失键追加，其余行原样保留。
     */
    private static function mergeEnv(string $content, array $updates): string
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $found = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*([A-Z][A-Z0-9_]*)\s*=/', $line, $m) && array_key_exists($m[1], $updates)) {
                $lines[$i] = $m[1] . ' = ' . self::encodeEnvValue((string) $updates[$m[1]]);
                $found[$m[1]] = true;
            }
        }
        foreach ($updates as $key => $value) {
            if (!isset($found[$key])) {
                $lines[] = $key . ' = ' . self::encodeEnvValue((string) $value);
            }
        }
        return implode("\n", $lines);
    }

    /**
     * 将环境变量值编码为 parse_ini_file 可无损读取的双引号字符串。
     */
    private static function encodeEnvValue(string $value): string
    {
        return '"' . $value . '"';
    }
}
