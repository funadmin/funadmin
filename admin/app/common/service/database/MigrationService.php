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
            if (Db::connection()->transactionLevel() > 0 && $this->hasDdl($this->statements($sql))) {
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
            if ($this->hasDdl($statements)) {
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

    /** 动态 SQL 字符串的递归深度上限；每层引号都要转义，真实迁移只有一层，超过即视为无法安全解析。 */
    private const DYNAMIC_SQL_MAX_DEPTH = 8;

    /**
     * 是否含 DDL：语句的第一个代码关键字（跳过注释；MySQL 可执行注释 /*!…*\/ 的内容按代码看待）是
     * CREATE/ALTER/DROP/TRUNCATE/RENAME，或以 PREPARE/EXECUTE 执行动态 SQL（按 information_schema 守卫的动态 DDL）。
     * DDL 会隐式提交，这类文件不能包在事务里，也禁止在外层事务中执行。分词与守卫、语句切分共用 SqlLexer。
     */
    private function hasDdl(array $statements): bool
    {
        foreach ($statements as $statement) {
            foreach (SqlLexer::items(SqlLexer::scan($statement)) as [$kind, $text]) {
                if ($kind !== SqlLexer::WORD) continue;
                if (in_array(strtoupper($text), ['CREATE', 'ALTER', 'DR' . 'OP', 'TR' . 'UNCATE', 'RE' . 'NAME', 'PREPARE', 'EXECUTE'], true)) return true;
                break;
            }
        }
        return false;
    }

    /**
     * 只向前守卫（词法安全网，不是 SQL 沙箱），基于 SqlLexer 的 MySQL 词法：
     * - 代码中（含可执行注释体）任何 DROP / TRUNCATE / RENAME 一律拒绝；字符串、标识符、注释中的这些词不算。
     * - 文件在代码中使用 PREPARE / EXECUTE 时字符串会被当作 SQL 执行：每个字符串常量按 MySQL 规则反转义后用同一词法器
     *   重新分词，其中只允许 `DROP INDEX <索引名>` 与 `DROP PRIMARY KEY`（重建索引、换主键，不丢数据），其余 DROP、
     *   TRUNCATE、RENAME 拒绝；其中的字符串常量递归同样处理；全部字符串按顺序拼接后再查一次，防止关键字被拆到相邻字符串。
     *   因此用到 PREPARE / EXECUTE 的文件里**每个**字符串常量（包括 COMMENT '…' 与 INSERT 的数据值）都按 SQL 分词与检查：
     *   含 $、落单的撇号或引号片段、DROP / TRUNCATE / RENAME 字样，以及任何非 ASCII 文字（如中文 COMMENT '用户名'、
     *   VALUES ('中文')，按非 ASCII 词拒绝，见 rejectSessionLexingChanges()）都会使整个文件被拒绝——
     *   迁移作者应把这类注释、数据写入放在不含 PREPARE 的单独文件里；不含 PREPARE 的文件中字符串内容不受这些限制。
     * - 会话词法状态：引用 sql_mode（ANSI_QUOTES / NO_BACKSLASH_ESCAPES 改变引号与转义）或改客户端字符集
     *   （SET NAMES、SET CHARACTER SET / CHARSET、character_set_*；GBK 等字符集的尾字节可以是 0x5C）都会让 MySQL
     *   看到与词法器不同的字符串边界，静态代码与各层动态字符串一律拒绝，见 rejectSessionLexingChanges()。
     * - 失败即拒绝：任何词法错误（未闭合的字符串或注释、边界有歧义的可执行注释、动态字符串不是完整 SQL 等）
     *   或任何内部异常都拒绝执行该文件。
     */
    private function assertForwardOnly(string $sql, string $file): void
    {
        try {
            $destructive = $this->forwardOnlyViolation($sql);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Migration 无法安全解析，已拒绝：' . $file . '（' . $exception->getMessage() . '）', 0, $exception);
        }
        if ($destructive) {
            throw new RuntimeException('Migration 包含破坏性语句：' . $file);
        }
    }

    /** 流式扫描（001 基线近 1 MB，不能把全部词法项留在内存里）；只有用到动态 SQL 时才第二遍收集字符串常量。 */
    private function forwardOnlyViolation(string $sql): bool
    {
        $dynamic = false;
        $items = (static function () use ($sql, &$dynamic): \Generator {
            foreach (self::rejectSessionLexingChanges(SqlLexer::items(SqlLexer::scan($sql))) as $item) {
                if (in_array(self::keyword($item), ['PREPARE', 'EXECUTE'], true)) $dynamic = true;
                yield $item;
            }
        })();
        if (self::destructiveIn($items, false)) return true;
        return $dynamic && self::dynamicViolation(SqlLexer::scan($sql), 1);
    }

    /**
     * 动态 SQL：逐个字符串常量（及其按顺序的拼接）反转义后重新分词，按白名单检查并递归其中的字符串常量。
     * @param iterable<SqlToken> $tokens
     */
    private static function dynamicViolation(iterable $tokens, int $depth): bool
    {
        $literals = [];
        foreach (SqlLexer::strings($tokens) as $string) $literals[] = SqlLexer::unescape($string);
        if ($literals === []) return false;
        if ($depth > self::DYNAMIC_SQL_MAX_DEPTH) throw new SqlLexerException('动态 SQL 字符串嵌套过深');
        if (count($literals) > 1) $literals[] = implode('', $literals);
        // 先查完能分词的字符串（破坏性语句给出更明确的拒绝原因），再对无法分词的字符串拒绝。
        $error = null;
        foreach ($literals as $literal) {
            try {
                $nested = SqlLexer::tokenize($literal);
            } catch (SqlLexerException $exception) {
                $error ??= $exception;
                continue;
            }
            if (self::destructiveIn(self::rejectSessionLexingChanges(SqlLexer::items($nested)), true)
                || self::dynamicViolation($nested, $depth + 1)) return true;
        }
        if ($error !== null) throw new SqlLexerException('动态 SQL 字符串无法分词：' . $error->getMessage(), 0, $error);
        return false;
    }

    /**
     * 原样转发词法项，遇到会改变 MySQL 会话词法状态的引用即抛出 SqlLexerException（静态代码与每层动态 SQL 共用）：
     * - 代码中（字符串常量之外）任何含非 ASCII 字节的词或反引号标识符：MySQL 按 utf8mb3_general_ci 折叠系统变量名，
     *   ſql_mode、sql_mòde、charactér_set_client 等与 sql_mode / character_set_client 等价，只做 ASCII 比较会被绕过。
     *   注意：用到 PREPARE / EXECUTE 的文件里每个字符串常量都会作为动态 SQL 再经过本函数，其中的非 ASCII 文字
     *   （中文 COMMENT、中文数据值）就成了非 ASCII 词而被拒绝；这类内容须放在不含 PREPARE 的文件里；
     * - 任何位置引用 sql_mode 或 character_set_*：裸词、反引号标识符、@@x / @@SESSION.x / @@GLOBAL.x 都拆成这些词法项；
     * - 任何位置的 NAMES（SET NAMES，包括 SET @a=1, NAMES gbk 这种列表续项）；
     * - CHARSET / CHARACTER SET，除非所在语句是 CREATE [TEMPORARY] TABLE 或 ALTER TABLE（列/表的字符集选项，
     *   001 基线大量使用）；SET CHARACTER SET、存储过程体里的 SET CHARSET 等都会被拒绝。
     * @param iterable<array{0: string, 1: string}> $items
     * @return \Generator<int, array{0: string, 1: string}>
     */
    private static function rejectSessionLexingChanges(iterable $items): \Generator
    {
        $words = [];       // 当前语句的前两个词，用来识别 CREATE/ALTER TABLE
        $previous = null;  // 紧邻的上一个词（识别 CHARACTER SET）
        foreach ($items as $item) {
            [$kind, $text] = $item;
            if ($kind === SqlLexer::SYMBOL && $text === ';') {
                $words = [];
                $previous = null;
                yield $item;
                continue;
            }
            if ($kind === SqlLexer::WORD || $kind === SqlLexer::IDENTIFIER) {
                // MySQL 按 utf8mb3_general_ci 折叠变量名（ſ → s、ò → o），ASCII 比较拦不住；代码里的词/标识符一律只许 ASCII。
                if (preg_match('/[\x80-\xFF]/', $text) === 1) {
                    throw new SqlLexerException('迁移代码中不允许非 ASCII 的词或标识符：' . $text);
                }
                $name = strtolower($text);
                if ($name === 'sql_mode' || str_starts_with($name, 'character_set_')) {
                    throw new SqlLexerException('迁移不允许引用 ' . $text . '（会改变 MySQL 的引号/字符集词法）');
                }
            }
            if ($kind !== SqlLexer::WORD) {
                $previous = null;
                yield $item;
                continue;
            }
            $word = strtoupper($text);
            $charset = $word === 'CHARSET' || ($word === 'SET' && $previous === 'CHARACTER');
            $tableOption = in_array($words[0] ?? '', ['CREATE', 'ALTER'], true) && in_array($words[1] ?? '', ['TABLE', 'TEMPORARY'], true);
            if ($word === 'NAMES' || ($charset && !$tableOption)) {
                throw new SqlLexerException('迁移不允许修改客户端字符集（' . ($word === 'NAMES' ? 'NAMES' : 'CHARACTER SET / CHARSET') . '）');
            }
            if (count($words) < 2) $words[] = $word;
            $previous = $word;
            yield $item;
        }
    }

    /**
     * 逐项扫描（不回看、不预读）：DROP 之后只接受 INDEX <名> 或 PRIMARY KEY（仅动态 SQL），其余一律视为破坏性。
     * @param iterable<array{0: string, 1: string}> $items
     * @param bool $dynamic 动态 SQL 内允许 DROP INDEX <名> 与 DROP PRIMARY KEY
     */
    private static function destructiveIn(iterable $items, bool $dynamic): bool
    {
        $expect = null; // DROP 之后期待的下一项：'INDEX_OR_PRIMARY'、'NAME'、'KEY'
        foreach ($items as $item) {
            $word = $item[0] === SqlLexer::WORD ? strtoupper($item[1]) : '';
            $keyword = self::keyword($item);
            if ($expect !== null) {
                $expect = match (true) {
                    $expect === 'INDEX_OR_PRIMARY' && $word === 'INDEX' => 'NAME',
                    $expect === 'INDEX_OR_PRIMARY' && $word === 'PRIMARY' => 'KEY',
                    $expect === 'NAME' && ($item[0] === SqlLexer::IDENTIFIER || ($word !== '' && $keyword === null)) => '',
                    $expect === 'KEY' && $word === 'KEY' => '',
                    default => false,
                };
                if ($expect === false) return true;
                if ($expect === '') $expect = null;
                continue;
            }
            if ($keyword === 'TR' . 'UNCATE' || $keyword === 'RE' . 'NAME') return true;
            if ($keyword === 'DR' . 'OP') {
                if (!$dynamic) return true;
                $expect = 'INDEX_OR_PRIMARY';
            }
        }
        return $expect !== null;
    }

    /**
     * 词法项若是守卫关注的关键字则返回其大写形式。数字开头的词（如 1e1DROP、可执行注释版本号后紧跟的 DROP）
     * 在 MySQL 中可能拆成数字 + 关键字，因此“以数字 + 关键字结尾”也算命中（宁可误拒）。
     * @param array{0: string, 1: string} $item
     */
    private static function keyword(array $item): ?string
    {
        if ($item[0] !== SqlLexer::WORD) return null;
        $word = strtoupper($item[1]);
        foreach (['DR' . 'OP', 'TR' . 'UNCATE', 'RE' . 'NAME', 'PREPARE', 'EXECUTE'] as $keyword) {
            if ($word === $keyword) return $keyword;
            $prefix = strlen($word) - strlen($keyword);
            if ($prefix > 0 && str_ends_with($word, $keyword) && ctype_digit($word[$prefix - 1])) return $keyword;
        }
        return null;
    }

    /**
     * 按 SqlLexer 的分隔符切分语句（字符串、标识符、注释中的分号不切），每条语句含结尾分号并去掉首尾空白；
     * 不含代码的片段（只有空白或注释）不执行。与历史切分结果保持一致：独占一行的 -- 注释连同其前的空白行一并去掉
     * （等价于旧实现的 /^\s*--.*$/m，但只作用于真正的注释，不再改动字符串内容）。
     */
    private function statements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $hasCode = false;
        foreach (SqlLexer::scan($sql) as $token) {
            if ($token->type === SqlToken::SEPARATOR) {
                if ($hasCode) $statements[] = trim($buffer . ';');
                $buffer = '';
                $hasCode = false;
                continue;
            }
            if ($token->type === SqlToken::DASH_COMMENT) {
                $lineStart = self::dashCommentLineStart($sql, $token->offset);
                if ($lineStart !== null) {
                    $buffer = substr($buffer, 0, strlen($buffer) - ($token->offset - $lineStart));
                    continue;
                }
            }
            $buffer .= $token->text;
            if (!$token->isComment() && ($token->type !== SqlToken::CODE || !ctype_space($token->text))) $hasCode = true;
        }
        if ($hasCode) $statements[] = trim($buffer);
        return $statements;
    }

    /**
     * -- 注释之前到上一个非空白字符之间若有换行（或已到文件开头），返回该空白段中第一个行首的位置，否则返回 null。
     * 这正是 /^\s*--.*$/m 的匹配起点：注释连同它前面的空白行一起删除，注释后的换行保留。
     */
    private static function dashCommentLineStart(string $sql, int $offset): ?int
    {
        $start = $offset;
        while ($start > 0 && strpos(" \t\n\v\f\r", $sql[$start - 1]) !== false) $start--;
        if ($start === 0) return 0;
        $newline = strpos(substr($sql, $start, $offset - $start), "\n");
        return $newline === false ? null : $start + $newline + 1;
    }
}
