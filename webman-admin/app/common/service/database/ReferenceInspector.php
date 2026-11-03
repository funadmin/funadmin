<?php

declare(strict_types=1);

namespace app\common\service\database;

use Illuminate\Database\Connection;

final class ReferenceInspector
{
    /** 返回引用目标物理表的外键列，限定当前 schema。 */
    public static function incoming(Connection $connection, string $table, string $column): array
    {
        $reader = clone $connection;
        $reader->setTablePrefix('');
        $reader->useDefaultSchemaGrammar();
        $schema = $reader->getSchemaBuilder();
        $namespace = $connection->getDriverName() === 'pgsql' ? (string) ($connection->getConfig('search_path') ?: 'public') : null;
        $result = [];
        foreach ($schema->getTables($namespace) as $candidate) {
            foreach ($schema->getForeignKeys($candidate['name']) as $foreign) {
                if ($foreign['foreign_table'] !== $table || ($namespace !== null && $foreign['foreign_schema'] !== $namespace)) continue;
                foreach ($foreign['foreign_columns'] as $position => $reference) {
                    if ($reference === $column) $result[] = (object) ['TABLE_NAME' => $candidate['name'], 'COLUMN_NAME' => $foreign['columns'][$position]];
                }
            }
        }
        return $result;
    }
}
