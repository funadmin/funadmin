<?php

namespace app\common\service\database;

use app\common\service\AbstractService;
use RuntimeException;
use support\Db;
use app\common\model\SystemMigration;
use app\common\model\PhpMigration;

/**
 * 执行只向前的 SQL migration；文件按名称排序且禁止破坏性语句。
 */
class MigrationService extends AbstractService
{
    public static function coreDirectory(?string $projectRoot = null): string
    {
        $configured = getenv('CORE_DATABASE_PATH');
        $database = $configured !== false && $configured !== ''
            ? $configured
            : ($projectRoot ?? base_path()) . '/database/core';
        $directory = realpath(rtrim($database, DIRECTORY_SEPARATOR) . '/migrations');
        if ($directory === false || !is_dir($directory) || !is_readable($directory)
            || !is_file($directory . '/001_baseline.sql') || !is_readable($directory . '/001_baseline.sql')) {
            throw new RuntimeException('权威核心 SQL 目录不存在或基线不可读：' . $database);
        }
        return $directory;
    }

    public function runCore(): array
    {
        return $this->runDirectory(self::coreDirectory());
    }

    public function run(?string $directory = null): array
    {
        $connection = Db::connection();
        if ($connection->getDriverName() === 'mysql') {
            return DatabaseLock::run($connection, 'php-migrations', fn (): array => $this->runPhpFiles($directory));
        }
        return $this->runPhpFiles($directory);
    }

    private function runPhpFiles(?string $directory): array
    {
        $schema = Db::schema();
        if (!$schema->hasTable('migrations')) {
            $schema->create('migrations', static function (\Illuminate\Database\Schema\Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('checksum', 64);
                $table->dateTime('created_at');
            });
        }
        $executed = [];
        $files = glob(($directory ?? base_path() . '/database/migrations') . '/*.php') ?: [];
        if (!$files) throw new RuntimeException('数据库迁移文件不存在');
        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            $record = PhpMigration::query()->where('name', $name)->first();
            if ($record) {
                if (!hash_equals((string) $record->checksum, $checksum)) {
                    throw new RuntimeException('已执行的迁移文件被修改：' . $name);
                }
                continue;
            }
            $migration = require $file;
            if (!is_callable($migration)) {
                throw new RuntimeException('迁移文件必须返回可调用闭包：' . $name . '（实际类型：' . get_debug_type($migration) . '）');
            }
            $migration();
            PhpMigration::query()->insert(['name' => $name, 'checksum' => $checksum, 'created_at' => date('Y-m-d H:i:s')]);
            $executed[] = $name;
        }
        return $executed;
    }

    public function runDirectory(string $directory, string $scope = 'core'): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException('Migration 目录不存在：' . $directory);
        }
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        if (str_starts_with($scope, 'plugin:')) {
            if (!$this->repositoryExists()) {
                throw new RuntimeException('安装插件前必须先完成核心 migration');
            }
            // 空骨架合法，但必须在执行任何 SQL 前确认已登记文件没有丢失。
            $versions = array_map(static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), $files);
            $missing = array_diff(SystemMigration::where('scope', $scope)->pluck('version')->all(), $versions);
            if ($missing !== []) {
                throw new RuntimeException('已登记的 migration 文件缺失：' . $scope . '/' . implode('、', $missing));
            }
        } elseif (!$files) {
            throw new RuntimeException('Migration 目录没有 SQL 文件：' . $directory);
        }
        $this->assertVersionSequence($files, $scope);
        usort($files, fn (string $left, string $right): int => strcmp(
            $this->migrationSortKey($left),
            $this->migrationSortKey($right)
        ));

        return $this->runFiles($files, $scope, true);
    }

    /**
     * 仅执行目录内的完整版本名（不含 .sql），不扫描或补跑其他 pending。
     * 拒绝路径片段及符号链接；执行过程与全目录入口共享。
     */
    public function runVersion(string $directory, string $version, string $scope = 'core'): array
    {
        if (preg_match('/\A[0-9]+_[A-Za-z0-9_]+\z/', $version) !== 1) {
            throw new RuntimeException('Migration 版本不合法：' . $version);
        }
        $resolvedDirectory = realpath($directory);
        if ($resolvedDirectory === false || !is_dir($resolvedDirectory)) {
            throw new RuntimeException('Migration 目录不存在：' . $directory);
        }
        $file = $resolvedDirectory . DIRECTORY_SEPARATOR . $version . '.sql';
        if (!file_exists($file) && !is_link($file)) {
            throw new RuntimeException('Migration 版本不存在：' . $version);
        }
        if (is_link($file) || !is_file($file) || !is_readable($file)
            || dirname((string) realpath($file)) !== $resolvedDirectory) {
            throw new RuntimeException('Migration 必须是目录内的普通文件：' . $version);
        }
        return $this->runFiles([$file], $scope);
    }

    /** 历史安装 SQL 使用模板前缀；新生成制品已绑定物理表，禁止二次改写。 */
    public static function rewritePrefix(string $sql, string|array $templatePrefix, string $prefix): string
    {
        if (str_starts_with($sql, "-- funadmin-physical-table\n") || $templatePrefix === '') return $sql;
        $aliases = array_values(array_filter((array) $templatePrefix, static fn (string $alias): bool => $alias !== ''));
        return $aliases === [] ? $sql : strtr($sql, array_fill_keys($aliases, $prefix));
    }

    /** 两种入口共用 checksum、预检、SQL 解析、事务和登记逻辑。 */
    private function runFiles(array $files, string $scope, bool $completeDirectory = false): array
    {
        $connection = Db::connection();
        if ($connection->getDriverName() !== 'mysql') throw new RuntimeException('SQL migration 仅支持 MySQL');
        $lock = 'migration:' . substr(hash('sha256', $connection->getDatabaseName()), 0, 40);
        if ((int) $connection->selectOne('SELECT GET_LOCK(?, 0) acquired', [$lock])->acquired !== 1) {
            throw new RuntimeException('数据库迁移正在执行');
        }
        try {
            return $this->runLockedFiles($files, $scope, $completeDirectory);
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) released', [$lock]);
        }
    }

    private function runLockedFiles(array $files, string $scope, bool $completeDirectory): array
    {
        $validatedFiles = [];
        $repositoryReady = $this->repositoryExists();
        if ($completeDirectory && $repositoryReady) {
            $versions = array_map(static fn (string $file): string => pathinfo($file, PATHINFO_FILENAME), $files);
            $missing = array_diff(SystemMigration::where('scope', $scope)->pluck('version')->all(), $versions);
            if ($missing !== []) throw new RuntimeException('已登记的 migration 文件缺失：' . $scope . '/' . implode('、', $missing));
        }
        foreach ($files as $file) {
            if (is_link($file) || !is_file($file) || !is_readable($file)) throw new RuntimeException('Migration 必须是普通文件：' . $file);
            $sql = file_get_contents($file);
            if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('无法读取 migration：' . $file);
            DdlEncodingGuard::assertUtf8($sql);
            $this->assertForwardOnly($sql, $file);
            $checksum = hash('sha256', $sql);
            $version = pathinfo($file, PATHINFO_FILENAME);
            if ($repositoryReady) {
                $record = SystemMigration::query()->where('scope', $scope)->where('version', $version)->first();
                if ($record !== null && !hash_equals((string) $record->checksum, $checksum)) {
                    throw new RuntimeException("已执行的 migration 内容发生变化：{$scope}/{$version}");
                }
            }
            if (Db::connection()->transactionLevel() > 0 && preg_match('/\b(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\b/i', $sql)) {
                throw new RuntimeException('SQL DDL migration 禁止在外层事务中执行');
            }
            $validatedFiles[$file] = ['sql' => $sql, 'checksum' => $checksum];
        }
        $executed = [];
        foreach ($files as $file) {
            $version = pathinfo($file, PATHINFO_FILENAME);
            $checksum = $validatedFiles[$file]['checksum'];
            $repositoryReady = $this->repositoryExists();
            if ($repositoryReady) {
                $record = SystemMigration::where('scope', $scope)->where('version', $version)->first();
                if ($record) {
                    if (!hash_equals((string) $record->checksum, $checksum)) {
                        throw new RuntimeException("已执行的 migration 内容发生变化：{$scope}/{$version}");
                    }
                    continue;
                }
            } elseif ($scope !== 'core') {
                throw new RuntimeException('安装插件前必须先完成核心 migration');
            }

            if ($scope === 'core' && $version === '001_baseline' && Db::connection()->select('SHOW TABLES') !== []) {
                throw new RuntimeException('001_baseline 仅允许空库执行；存量库必须先进行差异接管');
            }
            $sql = $validatedFiles[$file]['sql'];
            if (!hash_equals($checksum, (string) hash_file('sha256', $file))) throw new RuntimeException('Migration 文件在预检后发生变化：' . $file);
            DdlEncodingGuard::check(Db::connection(), $sql);
            $this->assertForwardOnly($sql, $file);
            $sql = self::rewritePrefix($sql, config('funadmin.mysqlPrefix', 'fun_'), Db::connection()->getTablePrefix());
            $statements = $this->statements($sql);
            if (!$statements) {
                throw new RuntimeException('Migration 没有可执行 SQL：' . $file);
            }
            $apply = function () use ($statements, $scope, $version, $checksum) {
                foreach ($statements as $statement) {
                    Db::statement($statement);
                }
                if (!$this->repositoryExists()) {
                    throw new RuntimeException('Migration 未创建 system_migration 表');
                }
                SystemMigration::create([
                    'scope' => $scope,
                    'version' => $version,
                    'checksum' => $checksum,
                    'executed_at' => time(),
                ]);
            };
            // PREPARE/EXECUTE 用于按 information_schema 守卫的动态 DDL；DDL 会隐式提交，不能包在事务里。
            $hasDdl = array_filter($statements, static fn (string $statement): bool => preg_match('/^\s*(?:CREATE|ALTER|DROP|TRUNCATE|RENAME|PREPARE|EXECUTE)\b/i', $statement) === 1) !== [];
            if ($hasDdl) {
                $apply();
            } else {
                Db::transaction($apply);
            }
            $executed[] = $version;
        }
        return $executed;
    }

    /**
     * 所有 migration 都要求数字版本唯一。
     */
    private function assertVersionSequence(array $files, string $scope): void
    {
        $versions = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!preg_match('/^(\d+)_/', $name, $matches)) {
                throw new RuntimeException('Migration 文件缺少数字版本：' . $file);
            }
            $versions[$matches[1]][] = $name;
        }
        foreach ($versions as $number => $names) {
            if (count($names) === 1) {
                continue;
            }
            sort($names, SORT_STRING);
            throw new RuntimeException("Migration 数字版本重复：{$number}（" . implode('、', $names) . '）');
        }
    }

    private function migrationSortKey(string $file): string
    {
        $name = pathinfo($file, PATHINFO_FILENAME);
        preg_match('/^(\d+)_/', $name, $matches);
        return str_pad((string) ((int) ($matches[1] ?? 0)), 20, '0', STR_PAD_LEFT) . '_' . $name;
    }

    public function latestAppliedVersion(string $scope): string
    {
        if (!$this->repositoryExists()) {
            return '';
        }
        $versions = SystemMigration::where('scope', $scope)->pluck('version')->all();
        if (!$versions) {
            return '';
        }
        usort($versions, 'strnatcmp');
        return (string) end($versions);
    }

    private function repositoryExists(): bool
    {
        return Db::schema()->hasTable('system_migration');
    }

    private function assertForwardOnly(string $sql, string $file): void
    {
        $withoutComments = preg_replace('#/\*.*?\*/|--[^\r\n]*#s', '', $sql);
        // 剔除字符串常量，避免 INSERT 数据中的 rename/drop 等词被误判为 DDL。
        $withoutStrings = preg_replace("/'(?:[^'\\\\]|\\\\.)*'|\"(?:[^\"\\\\]|\\\\.)*\"/s", "''", (string) $withoutComments);
        if (preg_match('/\b(?:DR' . 'OP|TR' . 'UNCATE|RE' . 'NAME)\b/i', (string) $withoutStrings)) {
            throw new RuntimeException('Migration 包含破坏性语句：' . $file);
        }
    }

    private function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $statements = [];
        $buffer = '';
        $quoted = false;
        $quote = '';
        $escaped = false;
        $length = strlen($sql);
        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $buffer .= $char;
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($char === '\\' && $quoted) {
                $escaped = true;
                continue;
            }
            if (($char === "'" || $char === '"') && (!$quoted || $quote === $char)) {
                $quoted = !$quoted;
                $quote = $quoted ? $char : '';
                continue;
            }
            if ($char === ';' && !$quoted) {
                if (trim($buffer) !== ';') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }
}
