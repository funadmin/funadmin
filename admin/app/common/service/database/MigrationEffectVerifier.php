<?php

declare(strict_types=1);

namespace app\common\service\database;

use app\common\crud\SchemaInspector;
use Illuminate\Database\Connection;

/**
 * 把 migration 语句解析为“结构新增”效果，并按当前库结构逐项核对（migration 接管用）。
 *
 * 只支持能从结构证明已生效的语句：
 * - CREATE TABLE [IF NOT EXISTS] t (…)：表存在；其中每个列、PRIMARY KEY、[UNIQUE] KEY/INDEX 也逐项核对；
 * - ALTER TABLE t ADD [COLUMN] c <类型> …：列存在、类型兼容、（声明了时）可空性一致；
 * - ALTER TABLE t ADD [UNIQUE] INDEX|KEY [名称] (列…)、CREATE [UNIQUE] INDEX 名称 ON t (列…)：存在列序完全相同、唯一性相同的索引。
 * 其余语句（数据变更、MODIFY/CHANGE、外键、CHECK、生成列、动态 SQL、表选项等）一律 unsupported，调用方据此拒绝。
 *
 * 结构读取经 SchemaInspector（Laravel Schema Builder 的跨驱动内省）。类型比较：MySQL 上比较规范化后的完整列类型
 * （COLUMN_TYPE，整数显示宽度忽略）；其他驱动（测试用的 SQLite 只保存声明的类型名）按类型族比较。
 */
final class MigrationEffectVerifier
{
    public const PRESENT = 'present';
    public const MISSING = 'missing';
    public const UNSUPPORTED = 'unsupported';

    private const CONSTRAINT_WORDS = ['CONSTRAINT', 'FOREIGN', 'FULLTEXT', 'SPATIAL', 'CHECK', 'PERIOD', 'SYSTEM'];
    private const TYPE_FAMILIES = [
        'integer' => ['tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'bool', 'boolean', 'serial'],
        'string' => ['char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext', 'json', 'enum', 'set', 'clob', 'nchar', 'nvarchar', 'character', 'uuid'],
        'decimal' => ['decimal', 'dec', 'numeric', 'fixed', 'float', 'double', 'real'],
        'temporal' => ['date', 'datetime', 'timestamp', 'time', 'year'],
        'binary' => ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit'],
    ];

    private readonly SchemaInspector $inspector;
    /** @var array<string, array|null> */
    private array $tables = [];

    public function __construct(private readonly Connection $connection)
    {
        $this->inspector = new SchemaInspector(connection: $connection);
    }

    /**
     * @param list<string> $statements 已改写为物理表名的语句
     * @return list<array{statement: int, kind: string, table: string, target: string, status: string, detail: string}>
     */
    public function verify(array $statements): array
    {
        $results = [];
        foreach (array_values($statements) as $index => $statement) {
            foreach (self::effects($statement) as $effect) {
                $results[] = ['statement' => $index + 1] + $this->check($effect);
            }
        }
        return $results;
    }

    /**
     * 解析单条语句的效果；无法证明的语句返回一条 unsupported。
     * @return list<array<string, mixed>>
     */
    public static function effects(string $statement): array
    {
        try {
            $items = iterator_to_array(SqlLexer::items(SqlLexer::scan($statement)), false);
            while ($items !== [] && end($items) === [SqlLexer::SYMBOL, ';']) array_pop($items);
            $first = self::word($items, 0);
            $effects = match (true) {
                $first === 'CREATE' && self::word($items, 1) === 'TABLE' => self::createTable($items),
                $first === 'CREATE' && (self::word($items, 1) === 'INDEX' || (self::word($items, 1) === 'UNIQUE' && self::word($items, 2) === 'INDEX')) => self::createIndex($items),
                $first === 'ALTER' && self::word($items, 1) === 'TABLE' => self::alterTable($items),
                default => null,
            };
        } catch (SqlLexerException $exception) {
            return [self::unsupported($statement, '无法解析：' . $exception->getMessage())];
        }
        return $effects ?? [self::unsupported($statement, '不是可验证的结构新增')];
    }

    private function check(array $effect): array
    {
        $result = ['kind' => $effect['kind'], 'table' => $effect['table'] ?? '', 'target' => $effect['target'], 'status' => self::MISSING, 'detail' => ''];
        if ($effect['kind'] === self::UNSUPPORTED) {
            return array_replace($result, ['status' => self::UNSUPPORTED, 'detail' => $effect['detail']]);
        }
        $table = $this->table($effect['table']);
        if ($table === null) return array_replace($result, ['detail' => '表不存在']);
        if ($effect['kind'] === 'table') return array_replace($result, ['status' => self::PRESENT, 'detail' => '表存在']);
        if ($effect['kind'] === 'column') {
            $column = null;
            foreach ($table['columns'] as $candidate) {
                if (strcasecmp($candidate['name'], $effect['column']) === 0) $column = $candidate;
            }
            if ($column === null) return array_replace($result, ['detail' => '列不存在']);
            if (!$this->compatibleType($effect['type'], (string) $column['type'])) {
                return array_replace($result, ['detail' => '类型不兼容：期望 ' . $effect['type'] . '，实际 ' . $column['type']]);
            }
            if ($effect['nullable'] !== null && $effect['nullable'] !== (bool) $column['nullable']) {
                return array_replace($result, ['detail' => '可空性不同：期望 ' . ($effect['nullable'] ? 'NULL' : 'NOT NULL')]);
            }
            return array_replace($result, ['status' => self::PRESENT, 'detail' => $column['type'] . ($column['nullable'] ? ' null' : ' not null')]);
        }
        $expected = array_map('strtolower', $effect['columns']);
        $sameColumns = [];
        foreach ($table['indexes'] as $index) {
            if (array_map('strtolower', $index['columns']) !== $expected) continue;
            $sameColumns[] = $index['name'];
            $primary = $index['name'] === 'PRIMARY';
            if ($effect['primary'] ? $primary : (!$primary && $index['unique'] === $effect['unique'])) {
                return array_replace($result, ['status' => self::PRESENT, 'detail' => '索引 ' . $index['name']]);
            }
        }
        return array_replace($result, ['detail' => $sameColumns === [] ? '没有相同列序的索引' : '相同列序的索引唯一性不同：' . implode('、', $sameColumns)]);
    }

    private function table(string $physical): ?array
    {
        if (!array_key_exists($physical, $this->tables)) {
            // 只看当前库：getTables() 在 MySQL 上会列出账号可见的所有库，同名表可能来自别的库。
            $reader = clone $this->connection;
            $reader->setTablePrefix('');
            $reader->useDefaultSchemaGrammar();
            $exists = $reader->getSchemaBuilder()->hasTable($physical);
            $this->tables[$physical] = $exists ? $this->inspector->inspect($physical) : null;
        }
        return $this->tables[$physical];
    }

    private function compatibleType(string $expected, string $actual): bool
    {
        $driver = $this->connection->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            $mariaDb = $driver === 'mariadb' || (method_exists($this->connection, 'isMaria') && $this->connection->isMaria());
            return self::normalizeType($expected, $mariaDb) === self::normalizeType($actual, $mariaDb);
        }
        return self::family($expected) === self::family($actual);
    }

    /**
     * MySQL 列类型的规范形式（比较 migration 声明与 information_schema.COLUMN_TYPE）：
     * - 小写，空白折叠为单个空格；括号与逗号两侧的空白去掉，`)` 之后的空格保留（`bigint(20) unsigned` → `bigint unsigned`）；
     * - 整数显示宽度不影响存储与取值范围，一律去掉（MySQL 8.0.19+ 已不再报告，5.7 / MariaDB 仍报告 `int(11)`、`bigint(20)`）；
     *   唯一例外是 `tinyint(1)`：它是 MySQL 的布尔约定（BOOL / BOOLEAN 的别名，8.0 也保留这一宽度），保持原样，
     *   因此布尔列与 `tinyint` 列（5.7 报告为 `tinyint(4)`）不会互相接管；
     * - 别名：integer → int，bool / boolean → tinyint(1)，dec / numeric / fixed → decimal，无长度的 char → char(1)；
     * - MariaDB 的 JSON 是 LONGTEXT 的别名（COLUMN_TYPE 报告 `longtext`），仅在 MariaDB 上 json 规范为 longtext；
     *   MySQL 上二者是不同类型，不互相接管。
     */
    public static function normalizeType(string $type, bool $mariaDb = false): string
    {
        $type = strtolower(trim((string) preg_replace('/\s+/', ' ', $type)));
        $type = (string) preg_replace(['/\s*\(\s*/', '/\s*,\s*/', '/\s*\)/'], ['(', ',', ')'], $type);
        $type = (string) preg_replace(['/^integer\b/', '/^(?:bool|boolean)\b/', '/^(?:dec|numeric|fixed)\b/'], ['int', 'tinyint(1)', 'decimal'], $type);
        $type = (string) preg_replace('/^(tinyint(?!\(1\))|smallint|mediumint|int|bigint)\(\d+\)/', '$1', $type);
        $type = (string) preg_replace('/^char(?=$| )/', 'char(1)', $type);
        return $mariaDb ? (string) preg_replace('/^json$/', 'longtext', $type) : $type;
    }

    private static function family(string $type): string
    {
        $base = strtok(strtolower(trim($type)), ' (') ?: '';
        foreach (self::TYPE_FAMILIES as $family => $types) {
            if (in_array($base, $types, true)) return $family;
        }
        return $base;
    }

    /** CREATE TABLE [IF NOT EXISTS] t ( 定义… ) [表选项]。 */
    private static function createTable(array $items): ?array
    {
        $position = 2;
        if (self::word($items, $position) === 'IF') {
            if (self::word($items, $position + 1) !== 'NOT' || self::word($items, $position + 2) !== 'EXISTS') return null;
            $position += 3;
        }
        $table = self::name($items, $position);
        if ($table === null || !self::isSymbol($items, $position + 1, '(')) return null;
        $close = self::closing($items, $position + 1);
        if ($close === null) return null;
        foreach (array_slice($items, $close + 1) as [$kind, $text]) {
            if ($kind === SqlLexer::WORD && in_array(strtoupper($text), ['SELECT', 'AS', 'LIKE', 'IGNORE', 'REPLACE', 'PARTITION', 'UNION'], true)) return null;
        }
        $effects = [['kind' => 'table', 'table' => $table, 'target' => $table]];
        foreach (self::split(array_slice($items, $position + 2, $close - $position - 2)) as $definition) {
            $parsed = self::definition($table, $definition, false);
            if ($parsed === null) return [self::unsupported($table, '无法验证的表定义：' . self::text($definition))];
            array_push($effects, ...$parsed);
        }
        return $effects;
    }

    /** CREATE [UNIQUE] INDEX 名称 ON t (列…)。 */
    private static function createIndex(array $items): ?array
    {
        $unique = self::word($items, 1) === 'UNIQUE';
        $position = $unique ? 3 : 2;
        $name = self::name($items, $position);
        if ($name === null || self::word($items, $position + 1) !== 'ON') return null;
        $table = self::name($items, $position + 2);
        if ($table === null) return null;
        $columns = self::indexColumns($items, $position + 3);
        return $columns === null ? null : [self::index($table, $name, $columns, $unique, false)];
    }

    /** ALTER TABLE t ADD …[, ADD …]：每个子句都必须是可验证的新增。 */
    private static function alterTable(array $items): ?array
    {
        $table = self::name($items, 2);
        if ($table === null) return null;
        $effects = [];
        foreach (self::split(array_slice($items, 3)) as $clause) {
            $parsed = self::word($clause, 0) === 'ADD' ? self::definition($table, array_slice($clause, 1), true) : null;
            if ($parsed === null) return [self::unsupported($table, '无法验证的 ALTER 子句：' . self::text($clause))];
            array_push($effects, ...$parsed);
        }
        return $effects === [] ? null : $effects;
    }

    /** 列定义或索引定义（ALTER ADD 之后、CREATE TABLE 括号内）。 */
    private static function definition(string $table, array $items, bool $alter): ?array
    {
        $word = self::word($items, 0);
        if ($word === 'PRIMARY') {
            if ($alter || self::word($items, 1) !== 'KEY') return null;
            $columns = self::indexColumns($items, 2);
            return $columns === null ? null : [self::index($table, 'PRIMARY', $columns, true, true)];
        }
        if ($word === 'UNIQUE' || $word === 'INDEX' || $word === 'KEY') {
            $position = 1;
            if ($word === 'UNIQUE' && in_array(self::word($items, 1), ['INDEX', 'KEY'], true)) $position = 2;
            $name = null;
            if (!self::isSymbol($items, $position, '(')) {
                $name = self::name($items, $position);
                if ($name === null) return null;
                $position++;
            }
            $columns = self::indexColumns($items, $position);
            return $columns === null ? null : [self::index($table, $name, $columns, $word === 'UNIQUE', false)];
        }
        if ($word !== null && in_array($word, self::CONSTRAINT_WORDS, true)) return null;
        $position = 0;
        if ($alter && $word === 'COLUMN') $position = 1;
        return self::column($table, array_slice($items, $position));
    }

    /** 列名 类型[(参数)] [UNSIGNED|ZEROFILL] 属性…；生成列、内联外键与 CHECK 不支持。 */
    private static function column(string $table, array $items): ?array
    {
        $column = self::name($items, 0);
        $type = self::word($items, 1);
        if ($column === null || $type === null) return null;
        $position = 2;
        $type = strtolower($type);
        if (self::isSymbol($items, $position, '(')) {
            $close = self::closing($items, $position);
            if ($close === null) return null;
            $type .= '(' . implode('', array_map(static fn (array $item): string => $item[1], array_slice($items, $position + 1, $close - $position - 1))) . ')';
            $position = $close + 1;
        }
        while (in_array(self::word($items, $position), ['UNSIGNED', 'ZEROFILL', 'SIGNED'], true)) {
            $type .= ' ' . strtolower((string) self::word($items, $position));
            $position++;
        }
        $nullable = null;
        $primary = $unique = false;
        $depth = 0;
        for ($index = $position; $index < count($items); $index++) {
            [$kind, $text] = $items[$index];
            if ($kind === SqlLexer::SYMBOL) {
                $depth += $text === '(' ? 1 : ($text === ')' ? -1 : 0);
                continue;
            }
            if ($kind !== SqlLexer::WORD || $depth > 0) continue;
            $word = strtoupper($text);
            if (in_array($word, ['AS', 'GENERATED', 'REFERENCES', 'CHECK', 'CONSTRAINT'], true)) return null;
            if ($word === 'NULL' && self::word($items, $index - 1) !== 'DEFAULT') {
                $nullable = self::word($items, $index - 1) !== 'NOT';
            } elseif ($word === 'PRIMARY' && self::word($items, $index + 1) === 'KEY') {
                $primary = true;
            } elseif ($word === 'UNIQUE') {
                $unique = true;
            }
        }
        if ($primary) $nullable = false;
        $effects = [['kind' => 'column', 'table' => $table, 'target' => $table . '.' . $column, 'column' => $column, 'type' => $type, 'nullable' => $nullable]];
        if ($primary) $effects[] = self::index($table, 'PRIMARY', [$column], true, true);
        if ($unique) $effects[] = self::index($table, null, [$column], true, false);
        return $effects;
    }

    private static function index(string $table, ?string $name, array $columns, bool $unique, bool $primary): array
    {
        return ['kind' => 'index', 'table' => $table, 'target' => $table . '(' . implode(', ', $columns) . ')' . ($name === null ? '' : ' ' . $name),
            'name' => $name, 'columns' => $columns, 'unique' => $unique, 'primary' => $primary];
    }

    private static function unsupported(string $target, string $detail): array
    {
        return ['kind' => self::UNSUPPORTED, 'table' => '', 'target' => mb_strimwidth(trim(preg_replace('/\s+/', ' ', $target)), 0, 120, '…'), 'detail' => $detail];
    }

    /** [USING …] ( 列 [(前缀长度)] [ASC|DESC], … ) [索引选项]；函数索引不支持。 */
    private static function indexColumns(array $items, int $position): ?array
    {
        if (self::word($items, $position) === 'USING') $position += 2;
        if (!self::isSymbol($items, $position, '(')) return null;
        $close = self::closing($items, $position);
        if ($close === null) return null;
        $columns = [];
        foreach (self::split(array_slice($items, $position + 1, $close - $position - 1)) as $part) {
            $name = self::name($part, 0);
            if ($name === null) return null;
            $rest = array_slice($part, 1);
            if ($rest !== [] && self::isSymbol($rest, 0, '(')) {
                $end = self::closing($rest, 0);
                if ($end === null) return null;
                $rest = array_slice($rest, $end + 1);
            }
            if ($rest !== [] && !(count($rest) === 1 && in_array(self::word($rest, 0), ['ASC', 'DESC'], true))) return null;
            $columns[] = $name;
        }
        // 其后只允许索引选项（USING BTREE、COMMENT '…'、KEY_BLOCK_SIZE = n 等）。
        foreach (array_slice($items, $close + 1) as [$kind, $text]) {
            if ($kind === SqlLexer::SYMBOL && $text !== '=') return null;
        }
        return $columns === [] ? null : $columns;
    }

    /** 顶层逗号切分（括号内的逗号不切）。 */
    private static function split(array $items): array
    {
        $parts = [[]];
        $depth = 0;
        foreach ($items as $item) {
            if ($item[0] === SqlLexer::SYMBOL) {
                if ($item[1] === '(') $depth++;
                if ($item[1] === ')') $depth--;
                if ($item[1] === ',' && $depth === 0) {
                    $parts[] = [];
                    continue;
                }
            }
            $parts[array_key_last($parts)][] = $item;
        }
        return array_values(array_filter($parts, static fn (array $part): bool => $part !== []));
    }

    private static function closing(array $items, int $open): ?int
    {
        $depth = 0;
        for ($index = $open; $index < count($items); $index++) {
            if ($items[$index][0] !== SqlLexer::SYMBOL) continue;
            if ($items[$index][1] === '(') $depth++;
            if ($items[$index][1] === ')' && --$depth === 0) return $index;
        }
        return null;
    }

    private static function word(array $items, int $index): ?string
    {
        return ($items[$index][0] ?? null) === SqlLexer::WORD ? strtoupper($items[$index][1]) : null;
    }

    private static function isSymbol(array $items, int $index, string $symbol): bool
    {
        return ($items[$index] ?? null) === [SqlLexer::SYMBOL, $symbol];
    }

    /** 未限定库名的标识符（反引号或裸词）；db.t 形式不支持。 */
    private static function name(array $items, int $index): ?string
    {
        $item = $items[$index] ?? null;
        if ($item === null || !in_array($item[0], [SqlLexer::IDENTIFIER, SqlLexer::WORD], true)) return null;
        if (self::isSymbol($items, $index + 1, '.')) return null;
        return $item[1];
    }

    private static function text(array $items): string
    {
        return implode(' ', array_map(static fn (array $item): string => $item[0] === SqlLexer::IDENTIFIER ? '`' . $item[1] . '`' : $item[1], $items));
    }
}
