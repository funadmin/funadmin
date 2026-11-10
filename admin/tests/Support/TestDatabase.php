<?php

declare(strict_types=1);

namespace Tests\Support;

use app\admin\authorization\service\AuthzCache;
use app\common\model\concern\TokenSoftDeletingScope;
use app\common\service\database\MigrationService;
use Illuminate\Database\Connection;
use PDO;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use FilesystemIterator;
use support\Db;

/**
 * 测试库的核心结构模板（SQLite）。
 *
 * migrate() 可直接替换测试里的 (new MigrationService())->run()：默认连接是空的 SQLite :memory: 库时，
 * 把一次性建好的模板文件（PHP 迁移链路在空库上的完整结果：结构、种子、migrations 登记、sqlite_sequence）原样复制进来，
 * 而不是每个测试（每个隔离进程）重跑迁移（约 250 ms）。其余情况（MySQL、库非空、事务中）照常执行 MigrationService::run()。
 *
 * 模板键包含 app/config/database 的 php/sql/json 文件内容、已加载的 database/auth 配置、
 * 以及 MigrationService::coreDirectory() 解析到的真实目录与外部 SQL 内容。每次复核，不信任继承的环境 key。
 * 模板缺失时，第一个需要它的测试在自己的空库上真实执行迁移，再 VACUUM INTO 写出模板（原子改名），因此模板与真实迁移逐字节同源。
 */
final class TestDatabase
{
    public const KEY_ENV = 'FUNADMIN_TEST_SCHEMA_KEY';
    private const PREFIX = 'funadmin-test-schema-';
    private const ALIAS = 'funadmin_template';

    /** @return list<string> 与 MigrationService::run() 相同：本次执行（此处为从模板恢复）的迁移文件名。 */
    public static function migrate(): array
    {
        $connection = Db::connection();
        if (!self::eligible($connection)) {
            return (new MigrationService())->run();
        }
        $template = self::templatePath($connection);
        if (!is_file($template)) {
            $lock = fopen($template . '.lock', 'c');
            if ($lock === false) throw new \RuntimeException('无法打开核心测试模板锁');
            try {
                if (!flock($lock, LOCK_EX)) throw new \RuntimeException('无法锁定核心测试模板');
                clearstatcache(true, $template);
                if (!is_file($template)) {
                    $executed = (new MigrationService())->run();
                    self::writeTemplate($connection, $template);
                    return $executed;
                }
            } finally {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
        self::restore($connection, $template);
        return array_map('strval', $connection->table('migrations')->orderBy('id')->pluck('name')->all());
    }

    /** 内容寻址；即使继承了环境 key，也重算以防配置或外部迁移来源改变。 */
    public static function key(): string
    {
        $root = dirname(__DIR__, 2);
        $core = MigrationService::coreDirectory();
        $hash = hash_init('sha256');
        // 环境里继承的 key 只能作为诊断输出，不能跳过输入校验；同一进程也可能更换权威 SQL 或加载后的配置。
        hash_update($hash, $core . "\0" . json_encode([
            'database' => config('database'), 'auth' => config('auth'),
        ], JSON_THROW_ON_ERROR));
        foreach ([$root . '/database', $root . '/app', $root . '/config', $core] as $directory) {
            $files = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!in_array($file->getExtension(), ['php', 'sql', 'json'], true)) continue;
                $fingerprint = hash_file('sha256', $file->getPathname());
                if ($fingerprint === false) throw new \RuntimeException('无法读取核心测试模板输入：' . $file->getPathname());
                $files[$file->getPathname()] = $fingerprint;
            }
            ksort($files);
            foreach ($files as $path => $fingerprint) hash_update($hash, $path . "\0" . $fingerprint . "\n");
        }
        $key = hash_final($hash);
        putenv(self::KEY_ENV . '=' . $key);
        $_SERVER[self::KEY_ENV] = $_ENV[self::KEY_ENV] = $key;
        return $key;
    }

    private static function eligible(Connection $connection): bool
    {
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:' || $connection->transactionLevel() !== 0) {
            return false;
        }
        return (int) $connection->selectOne("SELECT COUNT(*) AS total FROM sqlite_master WHERE name NOT LIKE 'sqlite_%'")->total === 0;
    }

    private static function templatePath(Connection $connection): string
    {
        $suffix = substr(hash('sha256', self::key() . "\0" . $connection->getTablePrefix() . "\0" . PHP_VERSION), 0, 24);
        return sys_get_temp_dir() . '/' . self::PREFIX . $suffix . '.sqlite';
    }

    private static function writeTemplate(Connection $connection, string $template): void
    {
        $temporary = $template . '.' . bin2hex(random_bytes(6)) . '.tmp';
        try {
            $connection->getPdo()->exec('VACUUM INTO ' . $connection->getPdo()->quote($temporary));
            rename($temporary, $template);
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
        // 清理一天前的旧键模板；正在运行的其它套件的模板是新的，不受影响。
        foreach (glob(sys_get_temp_dir() . '/' . self::PREFIX . '*.sqlite') ?: [] as $stale) {
            if ($stale !== $template && filemtime($stale) < time() - 86400) @unlink($stale);
        }
    }

    private static function restore(Connection $connection, string $template): void
    {
        $pdo = $connection->getPdo();
        $foreignKeys = (int) $pdo->query('PRAGMA foreign_keys')->fetchColumn();
        $attached = false;
        try {
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $pdo->exec('ATTACH DATABASE ' . $pdo->quote($template) . ' AS ' . self::ALIAS);
            $attached = true;
            $objects = $pdo->query('SELECT type, name, sql FROM ' . self::ALIAS . ".sqlite_master WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%' ORDER BY rowid")->fetchAll(PDO::FETCH_ASSOC);
            $pdo->beginTransaction();
            foreach ($objects as $object) {
                if ($object['type'] !== 'table') continue;
                $pdo->exec($object['sql']);
                $table = self::quote($object['name']);
                $columns = [];
                foreach ($pdo->query('PRAGMA ' . self::ALIAS . '.table_xinfo(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                    if ((int) $column['hidden'] === 0) $columns[] = self::quote((string) $column['name']);
                }
                $list = implode(', ', $columns);
                $pdo->exec("INSERT INTO main.{$table} ({$list}) SELECT {$list} FROM " . self::ALIAS . ".{$table}");
            }
            if ($pdo->query('SELECT 1 FROM ' . self::ALIAS . ".sqlite_master WHERE name = 'sqlite_sequence'")->fetchColumn()) {
                $pdo->exec('DELETE FROM main.sqlite_sequence');
                $pdo->exec('INSERT INTO main.sqlite_sequence (name, seq) SELECT name, seq FROM ' . self::ALIAS . '.sqlite_sequence');
            }
            foreach ($objects as $object) {
                if ($object['type'] !== 'table') $pdo->exec($object['sql']);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        } finally {
            try {
                if ($attached) $pdo->exec('DETACH DATABASE ' . self::ALIAS);
            } finally {
                $pdo->exec('PRAGMA foreign_keys = ' . ($foreignKeys === 1 ? 'ON' : 'OFF'));
            }
        }
        // 与真实迁移结束时相同的进程内状态：软删除令牌列缓存按新结构重读；授权版本（如有）换新，避免不同库共用同一令牌。
        TokenSoftDeletingScope::flushDeletedTokenCache();
        AuthzCache::bump($connection);
    }

    private static function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
