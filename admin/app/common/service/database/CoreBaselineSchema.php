<?php

declare(strict_types=1);

namespace app\common\service\database;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * 核心基线结构的唯一来源：权威核心 SQL（database/core/migrations/001_baseline.sql）里的 CREATE TABLE。
 *
 * PHP 迁移链路（SQLite 与测试使用）不再用 Blueprint 重写一遍核心表，而是由本类读取同一份 SQL 建表：
 * - MySQL：原样执行（表前缀按连接改写），只去掉外键约束；
 * - SQLite：把 mysqldump 风格的 MySQL DDL 翻译成等价的 SQLite DDL（类型亲和、自增主键、enum 检查约束、存储生成列、
 *   独立的 CREATE [UNIQUE] INDEX；SQLite 索引名全库唯一，统一命名为「物理表名__原索引名」）。
 * 外键只存在于 MySQL 正式安装（PHP 链路历来不建外键）；列注释、字符集、排序规则、ON UPDATE 不翻译。
 * 已存在的表跳过（与原基线「表不存在才创建」一致）。
 */
final class CoreBaselineSchema
{
    /** SQL 文件中表名使用的模板前缀。 */
    private const TEMPLATE_PREFIX = 'fun_';

    /** @return list<string> 按文件顺序的表名（不含前缀） */
    public static function tables(string $sqlFile): array
    {
        return array_keys(self::parse($sqlFile));
    }

    /** @return list<string> 本次创建的表（不含前缀） */
    public static function create(Connection $connection, string $sqlFile): array
    {
        $driver = $connection->getDriverName();
        if (!in_array($driver, ['mysql', 'sqlite'], true)) {
            throw new RuntimeException('核心基线只支持 MySQL 与 SQLite，当前驱动：' . $driver);
        }
        $schema = $connection->getSchemaBuilder();
        $created = [];
        foreach (self::parse($sqlFile) as $name => $definition) {
            if ($schema->hasTable($name)) continue;
            $physical = $connection->getTablePrefix() . $name;
            $statements = $driver === 'mysql' ? [self::mysql($definition, $physical)] : self::sqlite($definition, $name, $physical);
            foreach ($statements as $statement) $connection->statement($statement);
            $created[] = $name;
        }
        return $created;
    }

    /**
     * @return array<string, array{statement: string, lines: list<string>}>
     */
    private static function parse(string $sqlFile): array
    {
        $sql = file_get_contents($sqlFile);
        if ($sql === false) throw new RuntimeException('核心基线 SQL 不可读：' . $sqlFile);
        preg_match_all('/^CREATE TABLE (?:IF NOT EXISTS )?`' . self::TEMPLATE_PREFIX . '([a-z0-9_]+)` \((.*?)\n\)[^;\n]*;$/ms', $sql, $matches, PREG_SET_ORDER);
        if ($matches === []) throw new RuntimeException('核心基线 SQL 没有建表语句：' . $sqlFile);
        $tables = [];
        foreach ($matches as [$statement, $name, $body]) {
            $tables[$name] = ['statement' => $statement, 'lines' => array_values(array_filter(array_map(
                static fn (string $line): string => rtrim(trim($line), ','),
                explode("\n", trim($body))
            ), static fn (string $line): bool => $line !== ''))];
        }
        return $tables;
    }

    /** @param array{statement: string, lines: list<string>} $definition */
    private static function mysql(array $definition, string $physical): string
    {
        $lines = array_values(array_filter($definition['lines'], static fn (string $line): bool => !str_starts_with($line, 'CONSTRAINT ')));
        $head = substr($definition['statement'], 0, (int) strpos($definition['statement'], '('));
        $tail = substr($definition['statement'], (int) strrpos($definition['statement'], "\n)") + 1);
        $head = preg_replace('/`' . self::TEMPLATE_PREFIX . '[a-z0-9_]+`/', '`' . str_replace('`', '``', $physical) . '`', $head, 1);
        return $head . "(\n  " . implode(",\n  ", $lines) . "\n" . $tail;
    }

    /**
     * @param array{statement: string, lines: list<string>} $definition
     * @return list<string>
     */
    private static function sqlite(array $definition, string $name, string $physical): array
    {
        $columns = $constraints = $indexes = [];
        $primary = null;
        $autoIncrement = null;
        foreach ($definition['lines'] as $line) {
            if (preg_match('/\APRIMARY KEY \((.*)\)\z/', $line, $match) === 1) {
                $primary = self::columnList($match[1]);
            } elseif (preg_match('/\A(UNIQUE )?KEY `([^`]+)` \((.*)\)(?: USING \w+)?\z/', $line, $match) === 1) {
                $indexes[] = 'CREATE ' . ($match[1] !== '' ? 'UNIQUE ' : '') . 'INDEX ' . self::quote($physical . '__' . $match[2])
                    . ' ON ' . self::quote($physical) . ' (' . implode(', ', array_map([self::class, 'quote'], self::columnList($match[3]))) . ')';
            } elseif (str_starts_with($line, 'CONSTRAINT ')) {
                continue;
            } elseif (preg_match('/\A`([a-z0-9_]+)` (.*)\z/s', $line, $match) === 1) {
                [$column, $isAutoIncrement] = self::sqliteColumn($match[1], $match[2], $name);
                if ($isAutoIncrement) $autoIncrement = $match[1];
                $columns[] = $column;
            } else {
                throw new RuntimeException('无法翻译的核心基线定义（' . $name . '）：' . $line);
            }
        }
        if ($autoIncrement !== null) {
            if ($primary !== [$autoIncrement]) throw new RuntimeException('自增列必须是单列主键：' . $name);
            foreach ($columns as $index => $column) {
                if (str_starts_with($column, self::quote($autoIncrement) . ' ')) $columns[$index] = self::quote($autoIncrement) . ' integer primary key autoincrement not null';
            }
        } elseif ($primary !== null) {
            $constraints[] = 'primary key (' . implode(', ', array_map([self::class, 'quote'], $primary)) . ')';
        }
        return ['CREATE TABLE ' . self::quote($physical) . ' (' . implode(', ', [...$columns, ...$constraints]) . ')', ...$indexes];
    }

    /** @return array{0: string, 1: bool} SQLite 列定义与是否自增 */
    private static function sqliteColumn(string $name, string $definition, string $table): array
    {
        // 先剥掉注释（单引号字符串，内部用 '' 或 \' 转义），其余属性按 mysqldump 固定顺序解析。
        $definition = (string) preg_replace("/ COMMENT '(?:[^'\\\\]|''|\\\\.)*'\\z/s", '', $definition);
        $definition = (string) preg_replace('/ CHARACTER SET \w+| COLLATE \w+/', '', $definition);
        if (preg_match('/\A(\w+)(?:\(([^)]*)\))?( unsigned)?/i', $definition, $type) !== 1) {
            throw new RuntimeException("无法识别列类型：{$table}.{$name}");
        }
        $rest = substr($definition, strlen($type[0]));
        $base = strtolower($type[1]);
        $arguments = $type[2] ?? '';
        $check = null;
        $sqliteType = match ($base) {
            'tinyint' => $arguments === '1' ? 'tinyint(1)' : 'integer',
            'smallint', 'mediumint', 'int', 'integer', 'bigint' => 'integer',
            'decimal', 'numeric' => 'numeric',
            'float' => 'float',
            'double' => 'double',
            'char', 'varchar' => 'varchar',
            'tinytext', 'text', 'mediumtext', 'longtext', 'json' => 'text',
            'enum' => 'varchar',
            'datetime', 'timestamp' => 'datetime',
            'date' => 'date',
            'time' => 'time',
            'blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary' => 'blob',
            default => throw new RuntimeException("不支持的列类型 {$base}：{$table}.{$name}"),
        };
        if ($base === 'enum') {
            $check = 'check (' . self::quote($name) . ' in (' . $arguments . '))';
        }
        $parts = [self::quote($name), $sqliteType];
        $autoIncrement = false;
        $rest = trim($rest);
        if (preg_match('/\AGENERATED ALWAYS AS \((.*)\) (STORED|VIRTUAL)(.*)\z/s', $rest, $generated) === 1) {
            $parts[] = 'generated always as (' . self::sqliteExpression($generated[1], "{$table}.{$name}") . ') ' . strtolower($generated[2]);
            $rest = trim($generated[3]);
        }
        while ($rest !== '') {
            if (preg_match('/\ANOT NULL\b/', $rest, $match) === 1) {
                $parts[] = 'not null';
            } elseif (preg_match('/\ANULL\b/', $rest, $match) === 1) {
                $parts[] = 'null';
            } elseif (preg_match("/\\ADEFAULT ('(?:[^'\\\\]|''|\\\\.)*'|NULL|CURRENT_TIMESTAMP(?:\\(\\))?|-?[0-9.]+)/", $rest, $match) === 1) {
                $value = $match[1];
                if (str_starts_with($value, "'")) $value = "'" . str_replace(["\\'", "''"], ["''", "''"], substr($value, 1, -1)) . "'";
                $parts[] = 'default ' . (str_starts_with($value, 'CURRENT_TIMESTAMP') ? 'CURRENT_TIMESTAMP' : $value);
            } elseif (preg_match('/\AAUTO_INCREMENT\b/', $rest, $match) === 1) {
                $autoIncrement = true;
            } elseif (preg_match('/\A(?:ON UPDATE CURRENT_TIMESTAMP(?:\(\))?|DEFAULT_GENERATED)/', $rest, $match) === 1) {
                // MySQL 专有行为，SQLite 不翻译。
            } else {
                throw new RuntimeException("无法翻译的列属性：{$table}.{$name} {$rest}");
            }
            $rest = ltrim(substr($rest, strlen($match[0])));
        }
        if ($check !== null) $parts[] = $check;
        return [implode(' ', $parts), $autoIncrement];
    }

    /** 生成列表达式：反引号标识符、字符集引导符与 substring_index（仅 1/2 段）翻译成 SQLite 内建函数。 */
    private static function sqliteExpression(string $expression, string $column): string
    {
        $expression = (string) preg_replace('/_utf8mb4\'/', "'", $expression);
        $expression = (string) preg_replace_callback(
            "/substring_index\\(`([a-z0-9_]+)`,'([^']+)',([12])\\)/",
            static function (array $match): string {
                $value = self::quote($match[1]);
                $delimiter = "'" . $match[2] . "'";
                $first = "instr({$value}, {$delimiter})";
                if ($match[3] === '1') {
                    return "(case when {$first} > 0 then substr({$value}, 1, {$first} - 1) else {$value} end)";
                }
                $second = "instr(substr({$value}, {$first} + 1), {$delimiter})";
                return "(case when {$first} > 0 and {$second} > 0 then substr({$value}, 1, {$first} + {$second} - 1) else {$value} end)";
            },
            $expression
        );
        if (preg_match('/[a-z_]+\(/i', (string) preg_replace('/\b(?:instr|substr)\(/', '', $expression)) === 1) {
            throw new RuntimeException('生成列表达式含未翻译的函数：' . $column);
        }
        return (string) preg_replace_callback('/`([^`]+)`/', static fn (array $match): string => self::quote($match[1]), $expression);
    }

    /** @return list<string> 列名（去掉 MySQL 前缀索引长度） */
    private static function columnList(string $list): array
    {
        preg_match_all('/`([a-z0-9_]+)`(?:\(\d+\))?/', $list, $columns);
        return $columns[1];
    }

    private static function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
