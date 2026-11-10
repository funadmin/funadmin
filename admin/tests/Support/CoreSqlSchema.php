<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use support\Db;

/**
 * 核心库结构的规范化模型（表 → 列、主键、索引），用于 SQL 链路与 PHP 链路的一致性比对。
 *
 * - fromSqlChain()：不连数据库，解析权威核心 SQL（database/core/migrations/*.sql）的 CREATE TABLE，
 *   再按 005_soft_delete.sql 每张表 IF(条件, '子句', NULL) 的语义（缺什么补什么）重放其中的 ADD/DROP 子句。
 *   其余文件只含数据语句，不影响结构。
 * - fromConnection()：读取当前连接（SQLite/MySQL）里的实际结构。
 *
 * 规范化：表名去前缀；列按名称集合比较（SQLite 重建表会改变列序）；索引保留名称、是否唯一与有序列，只剥掉翻译时添加的物理表名前缀——
 * SQLite 的索引名在整个库内全局唯一，MySQL 表内的索引名（如 name、username）无法原样复刻。
 *
 * @phpstan-type Table array{columns: list<string>, primary: list<string>, indexes: list<string>}
 */
final class CoreSqlSchema
{
    /** @return array<string, array{columns: list<string>, primary: list<string>, indexes: list<string>}> */
    public static function fromSqlChain(?string $directory = null): array
    {
        $directory ??= base_path() . '/database/core/migrations';
        $files = glob($directory . '/*.sql') ?: [];
        sort($files);
        if ($files === []) throw new RuntimeException('核心 SQL 目录为空：' . $directory);
        $tables = [];
        foreach ($files as $file) {
            $sql = (string) file_get_contents($file);
            self::applyCreates($tables, $sql);
            self::applySoftDeleteClauses($tables, $sql);
        }
        return self::normalise($tables);
    }

    /** @return array<string, array{columns: list<string>, primary: list<string>, indexes: list<string>}> */
    public static function fromConnection(?string $connection = null): array
    {
        $db = Db::connection($connection);
        $schema = $db->getSchemaBuilder();
        $prefix = $db->getTablePrefix();
        $tables = [];
        foreach ($schema->getTables() as $table) {
            $physical = (string) $table['name'];
            if ($prefix !== '' && !str_starts_with($physical, $prefix)) continue;
            $name = substr($physical, strlen($prefix));
            $definition = ['columns' => array_map(static fn (array $column): string => (string) $column['name'], $schema->getColumns($name)), 'primary' => [], 'indexes' => []];
            foreach ($schema->getIndexes($name) as $index) {
                if ($index['primary']) {
                    $definition['primary'] = array_values($index['columns']);
                    continue;
                }
                $indexName = (string) $index['name'];
                if (str_starts_with($indexName, $physical . '__')) $indexName = substr($indexName, strlen($physical . '__'));
                $definition['indexes'][$indexName] = ['unique' => (bool) $index['unique'], 'columns' => array_values($index['columns'])];
            }
            $tables[$name] = $definition;
        }
        return self::normalise($tables);
    }

    /** @param array<string, array{columns: list<string>, primary: list<string>, indexes: array<string, array{unique: bool, columns: list<string>}>}> $tables */
    private static function applyCreates(array &$tables, string $sql): void
    {
        preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`fun_([a-z0-9_]+)` \((.*?)\n\)[^;]*;/s', $sql, $matches, PREG_SET_ORDER);
        foreach ($matches as [, $name, $body]) {
            if (isset($tables[$name])) continue; // CREATE TABLE IF NOT EXISTS 对已存在的表不生效
            $definition = ['columns' => [], 'primary' => [], 'indexes' => []];
            foreach (preg_split('/\n/', trim($body)) as $line) {
                $line = trim($line);
                if (preg_match('/\A`([a-z0-9_]+)` /', $line, $column) === 1) {
                    $definition['columns'][] = $column[1];
                } elseif (preg_match('/\APRIMARY KEY \(([^)]*)\)/', $line, $primary) === 1) {
                    $definition['primary'] = self::columnList($primary[1]);
                } elseif (preg_match('/\A(UNIQUE )?KEY `([^`]+)` \((.*)\)(?: USING \w+)?,?\z/', $line, $index) === 1) {
                    $definition['indexes'][$index[2]] = ['unique' => $index[1] !== '', 'columns' => self::columnList($index[3])];
                } elseif (!str_starts_with($line, 'CONSTRAINT ')) {
                    throw new RuntimeException('无法识别的核心 SQL 表定义行（' . $name . '）：' . $line);
                }
            }
            $tables[$name] = $definition;
        }
    }

    /** 005_soft_delete.sql：每张表一组 IF(条件, '子句', NULL)，条件均为「尚未满足才执行」。 */
    private static function applySoftDeleteClauses(array &$tables, string $sql): void
    {
        $blocks = preg_split('/^SET @soft_delete_ddl = CONCAT_WS\(/m', $sql) ?: [];
        array_shift($blocks);
        foreach ($blocks as $block) {
            if (preg_match("/CONCAT\\('ALTER TABLE `fun_([a-z0-9_]+)` '/", $block, $table) !== 1) {
                throw new RuntimeException('005 子句块缺少目标表');
            }
            $name = $table[1];
            if (!isset($tables[$name])) continue;
            preg_match_all("/, '((?:ADD|DROP) [^']*)', NULL\\)/", $block, $clauses);
            $current = $tables[$name];
            $next = $current;
            foreach ($clauses[1] as $clause) {
                if (preg_match('/\AADD COLUMN `([a-z0-9_]+)` .*?( FIRST)?(, DROP PRIMARY KEY, ADD PRIMARY KEY \(([^)]*)\))?\z/', $clause, $add) === 1) {
                    if (in_array($add[1], $current['columns'], true)) continue;
                    if (($add[2] ?? '') !== '') array_unshift($next['columns'], $add[1]); else $next['columns'][] = $add[1];
                    if (($add[3] ?? '') !== '') $next['primary'] = self::columnList($add[4]);
                } elseif (preg_match('/\AADD INDEX `([^`]+)` \(([^)]*)\)\z/', $clause, $add) === 1) {
                    $columns = self::columnList($add[2]);
                    $covered = array_filter([...$current['indexes'], ['columns' => $current['primary']]], static fn (array $index): bool => ($index['columns'][0] ?? null) === $columns[0]);
                    if ($covered === []) $next['indexes'][$add[1]] = ['unique' => false, 'columns' => $columns];
                } elseif (preg_match('/\ADROP INDEX `([^`]+)`, ADD UNIQUE KEY `([^`]+)` \(([^)]*)\)\z/', $clause, $rebuild) === 1) {
                    $index = $current['indexes'][$rebuild[1]] ?? null;
                    if ($index === null || !$index['unique'] || in_array('deleted_token', $index['columns'], true)) continue;
                    unset($next['indexes'][$rebuild[1]]);
                    $next['indexes'][$rebuild[2]] = ['unique' => true, 'columns' => self::columnList($rebuild[3])];
                } elseif (preg_match('/\AADD UNIQUE KEY `([^`]+)` \(([^)]*)\)\z/', $clause, $add) === 1) {
                    if (!isset($current['indexes'][$add[1]])) $next['indexes'][$add[1]] = ['unique' => true, 'columns' => self::columnList($add[2])];
                } elseif (preg_match('/\ADROP INDEX `([^`]+)`\z/', $clause, $drop) === 1) {
                    unset($next['indexes'][$drop[1]]);
                } else {
                    throw new RuntimeException('无法识别的 005 子句（' . $name . '）：' . $clause);
                }
            }
            $tables[$name] = $next;
        }
    }

    /** @return list<string> */
    private static function columnList(string $list): array
    {
        preg_match_all('/`([a-z0-9_]+)`(?:\(\d+\))?/', $list, $columns);
        return $columns[1];
    }

    /**
     * @param array<string, array{columns: list<string>, primary: list<string>, indexes: array<string, array{unique: bool, columns: list<string>}>}> $tables
     * @return array<string, array{columns: list<string>, primary: list<string>, indexes: list<string>}>
     */
    private static function normalise(array $tables): array
    {
        $result = [];
        foreach ($tables as $name => $table) {
            $columns = $table['columns'];
            sort($columns);
            $indexes = [];
            foreach ($table['indexes'] as $indexName => $index) {
                $indexes[] = ($index['unique'] ? 'unique' : 'index') . '(' . implode(',', $index['columns']) . ')@' . $indexName;
            }
            sort($indexes);
            $result[$name] = ['columns' => $columns, 'primary' => $table['primary'], 'indexes' => $indexes];
        }
        ksort($result);
        return $result;
    }
}
